<?php

namespace App\Jobs;

use App\Models\Alerta;
use App\Models\MonitoringTarget;
use App\Notifications\NovasComunicacoes;
use App\Services\Djen\Exceptions\DjenBadRequestException;
use App\Services\Djen\Exceptions\DjenBlockedException;
use App\Services\Djen\Exceptions\DjenException;
use App\Services\Djen\Exceptions\DjenRateLimitedException;
use App\Services\Monitoramento\MonitorarAlvo;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
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
        $rastreador = app(Rastreador::class);
        $this->alvo->refresh();

        if (! $this->alvo->ativo) {
            return;
        }

        Rastreador::origem('monitoramento');
        $inicio = hrtime(true);

        try {
            $novos = $monitorar->executar($this->alvo);
        } catch (DjenException $e) {
            $rastreador->evento(TipoEvento::MONITORAMENTO_FALHOU, [
                'alvo_id' => $this->alvo->id,
                'erro' => class_basename($e),
                'tentativa' => $this->attempts(),
            ], $this->alvo->user);

            match (true) {
                $e instanceof DjenRateLimitedException => $this->release($e->retryAfterSeconds ?? 60),
                // Não adianta tentar de novo agora.
                $e instanceof DjenBlockedException, $e instanceof DjenBadRequestException => $this->fail($e),
                default => throw $e,
            };

            return;
        }

        $rastreador->evento(TipoEvento::MONITORAMENTO_EXECUTADO, [
            'alvo_id' => $this->alvo->id,
            'novos' => $novos,
            'duracao_ms' => (int) round((hrtime(true) - $inicio) / 1_000_000),
        ], $this->alvo->user);

        $this->notificarPendentes($rastreador);
    }

    private function notificarPendentes(Rastreador $rastreador): void
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

        $rastreador->evento(TipoEvento::NOTIFICACAO_ENVIADA, [
            'alvo_id' => $this->alvo->id,
            'quantidade' => $pendentes->count(),
            'canais' => $this->alvo->user->whatsapp ? ['email', 'whatsapp'] : ['email'],
        ], $this->alvo->user);
    }
}
