<?php

namespace App\Http\Controllers\Monitoramento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoramento\UpdateNotificacoesRequest;
use App\Models\Communication;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PreferenciasController extends Controller
{
    public function notificacoes(UpdateNotificacoesRequest $request): RedirectResponse
    {
        $request->user()->forceFill(['whatsapp' => $request->validated('whatsapp')])->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferências de notificação salvas.']);

        return back();
    }

    /** LGPD: apaga todos os alvos, alertas e comunicações órfãs do usuário. */
    public function excluirDados(Request $request): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.required' => 'Digite sua senha para confirmar.',
            'password.current_password' => 'Senha incorreta.',
        ]);

        $user = $request->user();

        DB::transaction(function () use ($user): void {
            $user->monitoringTargets()->delete();
            $user->forceFill(['whatsapp' => null])->save();
            Communication::apagarOrfas();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Todos os seus dados de monitoramento foram excluídos.']);

        return to_route('monitoramento.index');
    }
}
