<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\MetricasAdmin;
use App\Support\Admin\Periodo;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** Visão geral: uso da Consulta, visitantes, contas, monitoramento e saúde do CNJ. */
class DashboardController extends Controller
{
    use Concerns;

    public function __invoke(Request $request, MetricasAdmin $metricas): Response
    {
        $periodo = Periodo::deRequest($request);

        return Inertia::render('Admin/Dashboard', [
            'periodo' => $periodo->toArray(),
            'presets' => self::presets(),
            ...$metricas->dashboard($periodo),
        ]);
    }
}
