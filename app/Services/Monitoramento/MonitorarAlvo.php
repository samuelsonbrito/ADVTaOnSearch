<?php

namespace App\Services\Monitoramento;

use App\Models\Alerta;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use App\Services\Djen\Contracts\ComunicacaoGateway;
use App\Services\Djen\Exceptions\DjenException;
use Carbon\CarbonImmutable;

/**
 * Busca as comunicações dos últimos N dias de um alvo e registra as que
 * ainda não tinham alerta para ele.
 *
 * Deduplicação em dois níveis, ambos garantidos por índice único:
 * - communications.hash: a mesma comunicação é guardada uma vez só;
 * - communication_target(communication_id, monitoring_target_id): no máximo
 *   um alerta por comunicação e alvo.
 */
final class MonitorarAlvo
{
    public function __construct(
        private readonly ComunicacaoGateway $gateway,
    ) {}

    /**
     * @return int Quantidade de alertas novos criados.
     *
     * @throws DjenException
     */
    public function executar(MonitoringTarget $alvo, ?CarbonImmutable $hoje = null): int
    {
        $fim = ($hoje ?? CarbonImmutable::now(config('djen.monitoring.timezone')))->startOfDay();
        $inicio = $fim->subDays(max(0, (int) config('djen.monitoring.lookback_days')));
        $porPagina = (int) config('djen.monitoring.itens_por_pagina');
        $maxPaginas = max(1, (int) config('djen.monitoring.max_paginas'));
        $novos = 0;

        for ($pagina = 1; $pagina <= $maxPaginas; $pagina++) {
            $resultado = $this->gateway->buscar(
                $alvo->tipo->filtro($alvo->valor, $alvo->uf, $inicio, $fim, $pagina, $porPagina),
            );

            foreach ($resultado->comunicacoes as $dados) {
                $comunicacao = Communication::createOrFirst(['hash' => $dados->hash], Communication::atributosDe($dados));

                $alerta = Alerta::createOrFirst([
                    'communication_id' => $comunicacao->id,
                    'monitoring_target_id' => $alvo->id,
                ]);

                if ($alerta->wasRecentlyCreated) {
                    $novos++;
                }
            }

            if ($pagina >= $resultado->ultimaPagina()) {
                break;
            }
        }

        $alvo->forceFill(['ultima_verificacao_em' => now()])->save();

        return $novos;
    }
}
