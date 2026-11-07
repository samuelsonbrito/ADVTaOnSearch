<?php

namespace App\Http\Controllers\Monitoramento;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function update(Request $request, Alerta $alerta, Rastreador $rastreador): RedirectResponse
    {
        Gate::authorize('update', $alerta);

        $dados = $request->validate(['lida' => ['required', 'boolean']]);

        $alerta->update(['lida_em' => $dados['lida'] ? ($alerta->lida_em ?? now()) : null]);
        $rastreador->evento($dados['lida'] ? TipoEvento::ALERTA_LIDO : TipoEvento::ALERTA_NAO_LIDO, ['alerta_id' => $alerta->id], request: $request);

        return back();
    }

    public function marcarTodasLidas(Request $request, Rastreador $rastreador): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validate([
            'alvo' => ['nullable', 'integer', Rule::exists('monitoring_targets', 'id')->where('user_id', $user->id)],
        ]);

        $quantidade = Alerta::query()
            ->doUsuario($user)
            ->whereNull('lida_em')
            ->when($dados['alvo'] ?? null, fn ($q, $alvo) => $q->where('monitoring_target_id', $alvo))
            ->update(['lida_em' => now()]);

        $rastreador->evento(TipoEvento::ALERTAS_TODOS_LIDOS, ['quantidade' => $quantidade], request: $request);

        return back();
    }
}
