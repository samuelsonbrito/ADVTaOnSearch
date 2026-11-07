<?php

namespace App\Http\Controllers\Monitoramento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoramento\UpdateNotificacoesRequest;
use App\Models\Communication;
use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class PreferenciasController extends Controller
{
    public function notificacoes(UpdateNotificacoesRequest $request, Rastreador $rastreador): RedirectResponse
    {
        $request->user()->forceFill(['whatsapp' => $request->validated('whatsapp')])->save();
        $rastreador->evento(TipoEvento::NOTIFICACOES_ATUALIZADAS, ['whatsapp' => $request->validated('whatsapp') !== null], request: $request);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Preferências de notificação salvas.']);

        return back();
    }

    /** LGPD: apaga todos os alvos, alertas e comunicações órfãs do usuário. */
    public function excluirDados(Request $request, Rastreador $rastreador): RedirectResponse
    {
        $request->validate(['password' => ['required', 'current_password']], [
            'password.required' => 'Digite sua senha para confirmar.',
            'password.current_password' => 'Senha incorreta.',
        ]);

        $user = $request->user();
        $rastreador->evento(TipoEvento::DADOS_EXCLUIDOS, ['alvos' => $user->monitoringTargets()->count()], request: $request);

        DB::transaction(function () use ($user): void {
            $user->monitoringTargets()->delete();
            $user->forceFill(['whatsapp' => null])->save();
            Communication::apagarOrfas();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Todos os seus dados de monitoramento foram excluídos.']);

        return to_route('monitoramento.index');
    }
}
