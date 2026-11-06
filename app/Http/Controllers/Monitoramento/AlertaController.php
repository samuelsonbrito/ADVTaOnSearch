<?php

namespace App\Http\Controllers\Monitoramento;

use App\Http\Controllers\Controller;
use App\Models\Alerta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AlertaController extends Controller
{
    public function update(Request $request, Alerta $alerta): RedirectResponse
    {
        Gate::authorize('update', $alerta);

        $dados = $request->validate(['lida' => ['required', 'boolean']]);

        $alerta->update(['lida_em' => $dados['lida'] ? ($alerta->lida_em ?? now()) : null]);

        return back();
    }

    public function marcarTodasLidas(Request $request): RedirectResponse
    {
        $user = $request->user();
        $dados = $request->validate([
            'alvo' => ['nullable', 'integer', Rule::exists('monitoring_targets', 'id')->where('user_id', $user->id)],
        ]);

        Alerta::query()
            ->doUsuario($user)
            ->whereNull('lida_em')
            ->when($dados['alvo'] ?? null, fn ($q, $alvo) => $q->where('monitoring_target_id', $alvo))
            ->update(['lida_em' => now()]);

        return back();
    }
}
