<?php

namespace App\Http\Resources\Monitoramento;

use App\Http\Resources\Djen\ComunicacaoResource;
use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/monitoramento.ts (Alerta).
 *
 * @mixin Alerta
 */
class AlertaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Alerta $alerta */
        $alerta = $this->resource;

        return [
            'id' => $alerta->id,
            'lida' => $alerta->lida_em !== null,
            'recebidaEm' => $alerta->created_at->toIso8601String(),
            'alvo' => [
                'id' => $alerta->target->id,
                'descricao' => $alerta->target->descricao(),
            ],
            'comunicacao' => (new ComunicacaoResource($alerta->communication->toData()))->resolve($request),
        ];
    }
}
