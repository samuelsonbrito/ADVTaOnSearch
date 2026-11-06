<?php

use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenBadRequestException;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Djen\Exceptions\DjenUnavailableException;
use App\Services\Djen\Exceptions\DjenUnexpectedResponseException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

// Os corpos abaixo são JSON genérico de propósito: os campos reais do DJEN
// só entram nos testes quando tivermos a resposta real em tests/Fixtures/djen/.

const BASE = 'https://djen.test/api/v1';

beforeEach(function () {
    config([
        'djen.base_url' => BASE,
        'djen.user_agent' => 'AdvTaOnSearch/teste',
        'djen.timeout' => 7,
        'djen.connect_timeout' => 3,
        'djen.retry.times' => 2,
        'djen.retry.sleep_ms' => 100,
        'djen.retry.max_sleep_ms' => 1000,
        'djen.cache_ttl' => 600,
    ]);
    app()->forgetInstance(DjenClient::class);

    Http::preventStrayRequests();
    Sleep::fake();
});

function djen(): DjenClient
{
    return app(DjenClient::class);
}

describe('requisição', function () {
    it('monta URL, query string e cabeçalhos', function () {
        Http::fake([BASE.'/*' => Http::response(['ok' => true])]);

        $response = djen()->get('/comunicacao', [
            'numeroOab' => ' 12345 ',
            'ufOab' => 'AM',
            'pagina' => 1,
            'nomeParte' => '',
            'numeroProcesso' => null,
        ]);

        expect($response->status)->toBe(200)
            ->and($response->json)->toBe(['ok' => true])
            ->and($response->fromCache)->toBeFalse();

        Http::assertSent(function (Request $request) {
            parse_str(parse_url($request->url(), PHP_URL_QUERY), $query);

            return str_starts_with($request->url(), BASE.'/comunicacao?')
                && $query === ['numeroOab' => '12345', 'ufOab' => 'AM', 'pagina' => '1']
                && $request->hasHeader('Accept', 'application/json')
                && $request->hasHeader('User-Agent', 'AdvTaOnSearch/teste');
        });
    });

    it('aplica timeout e connect timeout configurados', function () {
        $options = null;
        Http::fake(function (Request $request, array $opts) use (&$options) {
            $options = $opts;

            return Http::response(['ok' => true]);
        });

        djen()->get('comunicacao');

        expect($options['timeout'])->toBe(7)
            ->and($options['connect_timeout'])->toBe(3);
    });

    it('expõe os cabeçalhos da resposta sem diferenciar maiúsculas', function () {
        Http::fake([BASE.'/*' => Http::response(['ok' => true], 200, ['X-Qualquer' => 'valor'])]);

        expect(djen()->get('comunicacao')->header('x-qualquer'))->toBe('valor');
    });
});

describe('retry com backoff', function () {
    it('tenta de novo em 5xx e devolve o sucesso', function () {
        Http::fake([BASE.'/*' => Http::sequence()
            ->push('erro', 503)
            ->push('erro', 502)
            ->push(['ok' => true], 200)]);

        expect(djen()->get('comunicacao')->json)->toBe(['ok' => true]);

        Http::assertSentCount(3);
        Sleep::assertSequence([Sleep::for(100)->milliseconds(), Sleep::for(200)->milliseconds()]);
    });

    it('respeita o Retry-After do 429 dentro do teto', function () {
        Http::fake([BASE.'/*' => Http::sequence()
            ->push('limite', 429, ['Retry-After' => '30'])
            ->push(['ok' => true], 200)]);

        djen()->get('comunicacao');

        Http::assertSentCount(2);
        Sleep::assertSequence([Sleep::for(1000)->milliseconds()]);
    });

    it('não tenta de novo em 4xx', function () {
        Http::fake([BASE.'/*' => Http::response(['message' => 'inválido'], 422)]);

        expect(fn () => djen()->get('comunicacao'))->toThrow(DjenBadRequestException::class);

        Http::assertSentCount(1);
    });

    it('tenta de novo em falha de conexão/timeout', function () {
        Http::fake([BASE.'/*' => Http::sequence()
            ->pushFailedConnection()
            ->push(['ok' => true], 200)]);

        expect(djen()->get('comunicacao')->json)->toBe(['ok' => true]);
    });
});

describe('erros depois de esgotar as tentativas', function () {
    it('converte 5xx persistente em DjenUnavailableException', function () {
        Http::fake([BASE.'/*' => Http::response('fora do ar', 500)]);

        try {
            djen()->get('comunicacao');
            $this->fail('deveria lançar exceção');
        } catch (DjenUnavailableException $e) {
            expect($e->status)->toBe(500);
        }

        Http::assertSentCount(3); // 1 + 2 retries
    });

    it('converte timeout persistente em DjenUnavailableException', function () {
        Http::fake(fn () => throw new ConnectionException('cURL error 28: Operation timed out'));

        expect(fn () => djen()->get('comunicacao'))
            ->toThrow(DjenUnavailableException::class, 'Não foi possível conectar à API do CNJ');
    });

    it('converte 429 persistente em DjenRateLimitedException com Retry-After', function () {
        Http::fake([BASE.'/*' => Http::response('limite', 429, ['Retry-After' => '60'])]);

        try {
            djen()->get('comunicacao');
            $this->fail('deveria lançar exceção');
        } catch (DjenRateLimitedException $e) {
            expect($e->status)->toBe(429)->and($e->retryAfterSeconds)->toBe(60);
        }
    });

    it('converte 403 (bloqueio geográfico) em DjenBlockedException sem retry', function () {
        Http::fake([BASE.'/*' => Http::response('<HTML>Request blocked</HTML>', 403, ['Content-Type' => 'text/html'])]);

        expect(fn () => djen()->get('comunicacao'))->toThrow(DjenBlockedException::class, 'fora do Brasil');

        Http::assertSentCount(1);
    });

    it('rejeita 200 que não é JSON', function () {
        Http::fake([BASE.'/*' => Http::response('<html></html>', 200, ['Content-Type' => 'text/html'])]);

        expect(fn () => djen()->get('comunicacao'))->toThrow(DjenUnexpectedResponseException::class);
    });

    it('todas as falhas herdam de DjenException', function (string $class) {
        expect(is_subclass_of($class, DjenException::class))->toBeTrue();
    })->with([
        DjenUnavailableException::class,
        DjenRateLimitedException::class,
        DjenBlockedException::class,
        DjenBadRequestException::class,
        DjenUnexpectedResponseException::class,
    ]);
});

describe('cache', function () {
    it('guarda a resposta e não chama a API de novo', function () {
        Http::fake([BASE.'/*' => Http::response(['ok' => true])]);

        $primeira = djen()->getCached('comunicacao', ['ufOab' => 'AM', 'numeroOab' => '1']);
        $segunda = djen()->getCached('comunicacao', ['numeroOab' => '1', 'ufOab' => 'AM', 'nomeParte' => '']);

        expect($primeira->fromCache)->toBeFalse()
            ->and($segunda->fromCache)->toBeTrue()
            ->and($segunda->json)->toBe(['ok' => true]);

        Http::assertSentCount(1);
    });

    it('expira depois do TTL configurado (600 s)', function () {
        Http::fake([BASE.'/*' => Http::response(['ok' => true])]);

        djen()->getCached('comunicacao', ['numeroOab' => '1']);

        $this->travel(599)->seconds();
        expect(djen()->getCached('comunicacao', ['numeroOab' => '1'])->fromCache)->toBeTrue();

        $this->travel(2)->seconds();
        expect(djen()->getCached('comunicacao', ['numeroOab' => '1'])->fromCache)->toBeFalse();

        Http::assertSentCount(2);
    });

    it('não guarda erros', function () {
        Http::fake([BASE.'/*' => Http::sequence()
            ->push('erro', 500)->push('erro', 500)->push('erro', 500)
            ->push(['ok' => true], 200)]);

        expect(fn () => djen()->getCached('comunicacao', ['numeroOab' => '1']))->toThrow(DjenUnavailableException::class);
        expect(djen()->getCached('comunicacao', ['numeroOab' => '1'])->fromCache)->toBeFalse();
    });

    it('gera chaves diferentes para filtros diferentes', function () {
        expect(DjenClient::cacheKey('comunicacao', ['numeroOab' => '1']))
            ->not->toBe(DjenClient::cacheKey('comunicacao', ['numeroOab' => '2']))
            ->and(DjenClient::cacheKey('comunicacao', ['a' => '1', 'b' => '2']))
            ->toBe(DjenClient::cacheKey('/comunicacao', ['b' => '2', 'a' => ' 1 ', 'c' => null]));
    });
});
