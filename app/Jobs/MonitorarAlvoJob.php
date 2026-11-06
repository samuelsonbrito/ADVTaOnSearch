<?php

namespace App\Jobs;

use App\Models\Alerta;
use App\Models\MonitoringTarget;
use App\Notifications\NovasComunicacoes;
use App\Services\Djen\Exceptions\DjenBadRequestException;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Monitoramento\MonitorarAlvo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\RateLimited;
use Illuminate\Queue\Middleware\WithoutOverlapping;

/**
 * Verifica um alvo e notifica o dono sobre os alertas ainda não notificados.
 *
 * Notifica pelos alertas com notificada_em nulo (e não só pelos criados
 * nesta execução): se o envio falhar, a próxima execução tenta de novo, e
 * o que já foi avisado nunca é avisado outra vez.
 */
class MonitorarAlvoJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 900];

    public function __construct(
        public MonitoringTarget $alvo,
    ) {}

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            new RateLimited('djen'),
            (new WithoutOverlapping("monitorar-alvo:{$this->alvo->id}"))->releaseAfter(60)->expireAfter(900),
        ];
    }

    public function handle(MonitorarAlvo $monitorar): void
    {
        $this->alvo->refresh();

        if (! $this->alvo->ativo) {
            return;
        }

        try {
            $monitorar->executar($this->alvo);
        } catch (DjenRateLimitedException $e) {
            $this->release($e->retryAfterSeconds ?? 60);

            return;
        } catch (DjenBlockedException|DjenBadRequestException $e) {
            // Não adianta tentar de novo agora.
            $this->fail($e);

            return;
        }

        $this->notificarPendentes();
    }

    private function notificarPendentes(): void
    {
        $pendentes = $this->alvo->alertas()
            ->whereNull('notificada_em')
            ->with('communication')
            ->join('communications', 'communications.id', '=', 'communication_target.communication_id')
            ->orderByDesc('communications.data_disponibilizacao')
            ->select('communication_target.*')
            ->get();

        if ($pendentes->isEmpty()) {
            return;
        }

        $this->alvo->user->notify(new NovasComunicacoes($this->alvo, $pendentes));

        Alerta::whereKey($pendentes->modelKeys())->update(['notificada_em' => now()]);
    }
}
