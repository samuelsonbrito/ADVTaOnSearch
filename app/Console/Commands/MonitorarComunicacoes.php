<?php

namespace App\Console\Commands;

use App\Jobs\MonitorarAlvoJob;
use App\Models\MonitoringTarget;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

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

        MonitoringTarget::query()
            ->ativos()
            ->when($ids, fn ($q) => $q->whereKey($ids))
            ->orderBy('id')
            ->lazyById()
            ->each(function (MonitoringTarget $alvo) use (&$total): void {
                $this->option('sync')
                    ? MonitorarAlvoJob::dispatchSync($alvo)
                    : MonitorarAlvoJob::dispatch($alvo);
                $total++;
            });

        $this->components->info($this->option('sync')
            ? "{$total} alvo(s) verificado(s)."
            : "{$total} alvo(s) enviado(s) para a fila.");

        return self::SUCCESS;
    }
}
