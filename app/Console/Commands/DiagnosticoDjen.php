<?php

namespace App\Console\Commands;

use App\Services\Djen\DjenClient;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Checagem pós-deploy: banco, cache, fila, e-mail e acesso à Comunica API a
 * partir DESTE servidor (a API bloqueia IPs fora do Brasil).
 */
#[Signature('djen:diagnostico {--sem-api : Não chama a API do CNJ}')]
#[Description('Verifica se o ambiente está pronto para consultar e monitorar o DJEN')]
class DiagnosticoDjen extends Command
{
    private bool $falhou = false;

    private ?string $limiteRestante = null;

    public function handle(DjenClient $client): int
    {
        $this->checar('Banco de dados', function (): string {
            DB::select('select 1');

            return DB::connection()->getDriverName();
        });

        $this->checar('Cache', function (): string {
            Cache::put('djen:diagnostico', 'ok', 10);
            throw_unless(Cache::get('djen:diagnostico') === 'ok', new \RuntimeException('leitura falhou'));

            return (string) config('cache.default');
        });

        $this->aviso('Fila', (string) config('queue.default'), config('queue.default') === 'sync'
            ? 'fila "sync": exportações grandes e monitoramento rodam na requisição'
            : null);

        $this->aviso('E-mail', (string) config('mail.default'), in_array(config('mail.default'), ['log', 'array'], true)
            ? 'e-mails não serão entregues (driver de teste)'
            : null);

        $this->aviso('Fonte das comunicações', (string) config('djen.gateway'), config('djen.gateway') === 'fake'
            ? 'DJEN_GATEWAY=fake: dados fictícios'
            : null);

        $this->aviso('Monitoramento diário', config('djen.monitoring.time').' ('.config('djen.monitoring.timezone').')', null);

        if (! $this->option('sem-api')) {
            $this->checar('Comunica API ('.config('djen.base_url').')', function () use ($client): string {
                try {
                    $inicio = microtime(true);
                    $r = $client->get('comunicacao', ['pagina' => 1, 'itensPorPagina' => 5]);
                    $ms = (int) ((microtime(true) - $inicio) * 1000);
                    $this->limiteRestante = $r->header('x-ratelimit-remaining');

                    return "HTTP {$r->status} em {$ms} ms";
                } catch (DjenBlockedException $e) {
                    throw new \RuntimeException('403: a API recusou este servidor. Use um servidor com IP no Brasil.', previous: $e);
                } catch (DjenException $e) {
                    throw new \RuntimeException($e->getMessage(), previous: $e);
                }
            });

            if ($this->limiteRestante !== null) {
                $this->components->twoColumnDetail('Rate limit restante (x-ratelimit-remaining)', $this->limiteRestante);
            }
        }

        $this->newLine();

        if ($this->falhou) {
            $this->components->error('Há problemas no ambiente.');

            return self::FAILURE;
        }

        $this->components->info('Ambiente pronto.');

        return self::SUCCESS;
    }

    private function checar(string $nome, callable $teste): void
    {
        try {
            $this->components->twoColumnDetail($nome, '<fg=green>OK</> '.$teste());
        } catch (Throwable $e) {
            $this->falhou = true;
            $this->components->twoColumnDetail($nome, '<fg=red>FALHOU</> '.$e->getMessage());
        }
    }

    private function aviso(string $nome, string $valor, ?string $alerta): void
    {
        $this->components->twoColumnDetail($nome, $alerta ? "<fg=yellow>{$valor}</> · {$alerta}" : $valor);
    }
}
