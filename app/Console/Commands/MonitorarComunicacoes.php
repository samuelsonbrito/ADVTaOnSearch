<?php

namespace App\Console\Commands;

use App\Jobs\MonitorarAlvoJob;
use App\Models\MonitoringTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

/**
 * Agendado diariamente (routes/console.php). Enfileira um MonitorarAlvoJob
 * por alvo ativo; com --sync executa tudo no próprio processo.
 */
#[Signature('djen:monitorar
    {--alvo=* : IDs de alvos específicos (padrão: todos os ativos)}
    {--sync : Executa agora, sem fila}')]
#[Description('Verifica os alvos de monitoramento no DJEN e notifica as comunicações novas')]
class MonitorarComunicacoes extends Command
{
    public function handle(): int
    {
        $ids = array_filter((array) $this->option('alvo'));
        $total = 0;
        $falhas = 0;

        MonitoringTarget::query()
            ->ativos()
            ->when($ids, fn ($q) => $q->whereKey($ids))
            ->orderBy('id')
            ->lazyById()
            ->each(function (MonitoringTarget $alvo) use (&$total, &$falhas): void {
                $total++;

                if (! $this->option('sync')) {
                    MonitorarAlvoJob::dispatch($alvo);

                    return;
                }

                // No modo síncrono, a falha de um alvo (CNJ fora do ar, SMTP...)
                // não pode impedir a verificação dos demais.
                try {
                    MonitorarAlvoJob::dispatchSync($alvo);
                } catch (Throwable $e) {
                    $falhas++;
                    report($e);
                    $this->components->error("{$alvo->descricao()} (#{$alvo->id}): {$e->getMessage()}");
                }
            });

        if (! $this->option('sync')) {
            $this->components->info("{$total} alvo(s) enviado(s) para a fila.");

            return self::SUCCESS;
        }

        $this->components->info("{$total} alvo(s) verificado(s)".($falhas ? ", {$falhas} com erro." : '.'));

        return $falhas ? self::FAILURE : self::SUCCESS;
    }
}
