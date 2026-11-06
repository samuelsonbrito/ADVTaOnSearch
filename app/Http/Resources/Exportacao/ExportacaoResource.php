<?php

namespace App\Http\Resources\Exportacao;

use App\Enums\StatusExportacao;
use App\Models\Exportacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Espelhado em resources/js/types/exportacao.ts (Exportacao).
 *
 * @mixin Exportacao
 */
class ExportacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Exportacao $e */
        $e = $this->resource;

        return [
            'id' => $e->id,
            'origem' => $e->origem->value,
            'origemRotulo' => $e->origem->rotulo(),
            'formato' => $e->formato->value,
            'formatoRotulo' => $e->formato->rotulo(),
            'status' => $e->status->value,
            'statusRotulo' => $e->status->rotulo(),
            'todos' => (bool) ($e->parametros['todos'] ?? false),
            'totalItens' => $e->total_itens,
            'erro' => $e->erro,
            'criadaEm' => $e->created_at->toIso8601String(),
            'concluidaEm' => $e->concluida_em?->toIso8601String(),
            'expiraEm' => $e->expiraEm()->toIso8601String(),
            'downloadUrl' => $e->status === StatusExportacao::Concluida ? route('exportacoes.download', $e) : null,
        ];
    }
}
