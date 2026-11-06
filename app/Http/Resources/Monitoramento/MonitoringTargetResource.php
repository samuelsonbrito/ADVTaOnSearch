<?php

namespace App\Http\Resources\Monitoramento;

use App\Enums\TipoAlvo;
use App\Models\MonitoringTarget;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/monitoramento.ts (AlvoMonitoramento).
 *
 * @mixin MonitoringTarget
 */
class MonitoringTargetResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var MonitoringTarget $alvo */
        $alvo = $this->resource;

        return [
            'id' => $alvo->id,
            'tipo' => $alvo->tipo->value,
            'tipoRotulo' => $alvo->tipo->rotulo(),
            'valor' => $alvo->tipo === TipoAlvo::Processo ? MonitoringTarget::formatarCnj($alvo->valor) : $alvo->valor,
            'uf' => $alvo->uf,
            'apelido' => $alvo->apelido,
            'descricao' => $alvo->descricao(),
            'ativo' => $alvo->ativo,
            'ultimaVerificacaoEm' => $alvo->ultima_verificacao_em?->toIso8601String(),
            'naoLidas' => (int) ($alvo->nao_lidas_count ?? 0),
            'total' => (int) ($alvo->alertas_count ?? 0),
        ];
    }
}
