<?php

namespace App\Services\Djen;

use App\Events\ChamadaDjenConcluida;
use App\Services\Djen\Exceptions\DjenBadRequestException;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Exceptions\DjenUnexpectedResponseException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Throwable;

/**
 * Único ponto de contato com a Comunica API do PJe (DJEN/CNJ).
 *
 * Responsabilidades: URL base, cabeçalhos, timeout, retry com backoff
 * exponencial (conexão, 429 e 5xx), tradução de falhas em exceções de
 * domínio e cache das consultas com chave baseada nos filtros.
 *
 * Cada requisição real (não as do cache) dispara ChamadaDjenConcluida, que
 * alimenta o rastreio da área administrativa.
 *
 * Não conhece os campos da resposta: a tradução para DTOs fica em
 * Mapping\ComunicacaoApiMapper.
 */
class DjenClient
{
    /**
     * @param  array{
     *     base_url: string,
     *     user_agent: string,
     *     timeout: int,
     *     connect_timeout: int,
     *     retry: array{times: int, sleep_ms: int, max_sleep_ms?: int},
     *     cache_ttl: int,
     * }  $config
     */
    public function __construct(
        private readonly HttpFactory $http,
        private readonly CacheRepository $cache,
        private readonly array $config,
        private readonly ?Dispatcher $events = null,
    ) {}

    /**
     * GET sem cache.
     *
     * @param  array<string, scalar|null>  $query
     *
     * @throws DjenException
     */
    public function get(string $path, array $query = []): DjenResponse
    {
        $query = self::normalizeQuery($query);
        $inicio = hrtime(true);

        try {
            $response = $this->request()->get(ltrim($path, '/'), $query);
        } catch (ConnectionException $e) {
            $this->notificar($path, $query, $inicio, null, 'indisponivel', erro: $e->getMessage());

            throw new DjenUnavailableException(
                'Não foi possível conectar à API do CNJ (timeout ou falha de rede).',
                previous: $e,
            );
        }

        $restante = $response->header('x-ratelimit-remaining');
        $restante = is_numeric($restante) ? (int) $restante : null;

        try {
            $resposta = $this->toDjenResponse($response);
        } catch (DjenException $e) {
            $this->notificar($path, $query, $inicio, $response->status(), self::resultadoDe($e), null, $restante, $e->getMessage());

            throw $e;
        }

        $itens = $resposta->json['items'] ?? null;
        $this->notificar($path, $query, $inicio, $response->status(), 'sucesso', is_array($itens) ? count($itens) : null, $restante);

        return $resposta;
    }

    /**
     * @param  array<string, scalar>  $query
     */
    private function notificar(string $path, array $query, int $inicio, ?int $status, string $resultado, ?int $itens = null, ?int $restante = null, ?string $erro = null): void
    {
        $this->events?->dispatch(new ChamadaDjenConcluida(
            ltrim($path, '/'), $query, $status, $resultado,
            (int) round((hrtime(true) - $inicio) / 1_000_000), $itens, $restante, $erro,
        ));
    }

    private static function resultadoDe(DjenException $e): string
    {
        return match (true) {
            $e instanceof DjenRateLimitedException => 'limitado',
            $e instanceof DjenBlockedException => 'bloqueado',
            $e instanceof DjenUnavailableException => 'indisponivel',
            $e instanceof DjenBadRequestException => 'recusado',
            default => 'invalido',
        };
    }

    /**
     * GET com cache: respostas de sucesso ficam guardadas por `cache_ttl`
     * segundos. Erros nunca são guardados.
     *
     * @param  array<string, scalar|null>  $query
     *
     * @throws DjenException
     */
    public function getCached(string $path, array $query = []): DjenResponse
    {
        $key = self::cacheKey($path, $query);

        /** @var array{status: int, json: array<array-key, mixed>, headers: array<string, list<string>>}|null $cached */
        $cached = $this->cache->get($key);

        if (is_array($cached)) {
            return (new DjenResponse($cached['status'], $cached['json'], $cached['headers']))->withFromCache();
        }

        $response = $this->get($path, $query);

        $this->cache->put($key, [
            'status' => $response->status,
            'json' => $response->json,
            'headers' => $response->headers,
        ], $this->config['cache_ttl']);

        return $response;
    }

    /**
     * Chave estável: ignora ordem dos parâmetros, valores vazios e espaços.
     *
     * @param  array<string, scalar|null>  $query
     */
    public static function cacheKey(string $path, array $query): string
    {
        $query = self::normalizeQuery($query);
        ksort($query);

        return 'djen:'.sha1(ltrim($path, '/').'?'.http_build_query($query));
    }

    /**
     * Remove nulos e strings vazias; apara strings.
     *
     * @param  array<string, scalar|null>  $query
     * @return array<string, scalar>
     */
    public static function normalizeQuery(array $query): array
    {
        $normalized = [];

        foreach ($query as $key => $value) {
            if (is_string($value)) {
                $value = trim($value);
            }

            if ($value === null || $value === '') {
                continue;
            }

            $normalized[$key] = $value;
        }

        return $normalized;
    }

    private function request(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->config['base_url'])
            ->acceptJson()
            ->withUserAgent($this->config['user_agent'])
            ->timeout($this->config['timeout'])
            ->connectTimeout($this->config['connect_timeout'])
            ->retry(
                times: max(1, $this->config['retry']['times'] + 1),
                sleepMilliseconds: fn (int $attempt, ?Throwable $e): int => $this->retryDelay($attempt, $e),
                when: fn (Throwable $e): bool => self::shouldRetry($e),
                throw: false,
            );
    }

    private static function shouldRetry(Throwable $e): bool
    {
        if ($e instanceof ConnectionException) {
            return true;
        }

        if ($e instanceof RequestException) {
            return $e->response->status() === 429 || $e->response->serverError();
        }

        return false;
    }

    /**
     * Backoff exponencial (sleep_ms, 2x, 4x...). Em 429, respeita o
     * Retry-After quando ele cabe no teto configurado.
     */
    private function retryDelay(int $attempt, ?Throwable $e): int
    {
        $base = $this->config['retry']['sleep_ms'];
        $max = $this->config['retry']['max_sleep_ms'] ?? 10_000;

        if ($e instanceof RequestException && $e->response->status() === 429) {
            $retryAfter = self::retryAfterSeconds($e->response);

            if ($retryAfter !== null) {
                return min($retryAfter * 1000, $max);
            }
        }

        return (int) min($base * (2 ** ($attempt - 1)), $max);
    }

    private static function retryAfterSeconds(Response $response): ?int
    {
        $value = $response->header('Retry-After');

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    /**
     * @throws DjenException
     */
    private function toDjenResponse(Response $response): DjenResponse
    {
        $status = $response->status();

        if ($status === 429) {
            throw new DjenRateLimitedException(
                'A API do CNJ limitou as requisições. Tente novamente em instantes.',
                self::retryAfterSeconds($response),
            );
        }

        if ($response->serverError()) {
            throw new DjenUnavailableException("A API do CNJ respondeu com erro {$status}.", $status);
        }

        if ($status === 403) {
            throw new DjenBlockedException(
                'Acesso à API do CNJ recusado (403). A API bloqueia requisições de fora do Brasil.',
                $status,
            );
        }

        if ($response->clientError()) {
            throw new DjenBadRequestException(
                "A API do CNJ recusou a requisição ({$status}): ".mb_strimwidth(trim($response->body()), 0, 300, '…'),
                $status,
            );
        }

        $json = $response->json();

        if (! is_array($json)) {
            throw new DjenUnexpectedResponseException(
                'A API do CNJ devolveu uma resposta que não é JSON.',
                $status,
            );
        }

        return new DjenResponse($status, $json, $response->headers());
    }
}
