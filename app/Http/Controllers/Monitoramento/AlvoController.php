<?php

namespace App\Http\Controllers\Monitoramento;

use App\Http\Controllers\Controller;
use App\Http\Requests\Monitoramento\StoreMonitoringTargetRequest;
use App\Http\Requests\Monitoramento\UpdateMonitoringTargetRequest;
use App\Jobs\MonitorarAlvoJob;
use App\Models\Communication;
use App\Models\MonitoringTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class AlvoController extends Controller
{
    public function store(StoreMonitoringTargetRequest $request): RedirectResponse
    {
        $alvo = $request->user()->monitoringTargets()->create($request->validated());

        // Primeira verificação já em segundo plano.
        MonitorarAlvoJob::dispatch($alvo);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Monitorando {$alvo->descricao()}. A primeira verificação já foi iniciada."]);

        return back();
    }

    public function update(UpdateMonitoringTargetRequest $request, MonitoringTarget $alvo): RedirectResponse
    {
        $alvo->update($request->validated());

        if ($request->has('ativo')) {
            Inertia::flash('toast', ['type' => 'success', 'message' => $alvo->ativo ? 'Monitoramento retomado.' : 'Monitoramento pausado.']);
        }

        return back();
    }

    /** LGPD: exclui o alvo, seus alertas e as comunicações que ficarem órfãs. */
    public function destroy(Request $request, MonitoringTarget $alvo): RedirectResponse
    {
        Gate::authorize('delete', $alvo);

        DB::transaction(function () use ($alvo): void {
            $alvo->delete();
            Communication::apagarOrfas();
        });

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Alvo e dados relacionados excluídos.']);

        return to_route('monitoramento.index');
    }

    public function verificar(Request $request, MonitoringTarget $alvo): RedirectResponse
    {
        Gate::authorize('update', $alvo);

        if (! $alvo->ativo) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Reative o alvo antes de verificar.']);

            return back();
        }

        MonitorarAlvoJob::dispatch($alvo);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Verificação iniciada. Você será avisado se houver novidades.']);

        return back();
    }
}
