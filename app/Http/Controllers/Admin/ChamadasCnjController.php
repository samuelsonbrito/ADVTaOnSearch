<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ChamadaDjen;
use App\Services\Admin\MetricasAdmin;
use App\Support\Admin\Periodo;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Cada requisição real à Comunica API: saúde, latência e consumo do limite. */
class ChamadasCnjController extends Controller
{
    use Concerns;

    public const POR_PAGINA = 50;

    public function __invoke(Request $request, MetricasAdmin $metricas): Response
    {
        $periodo = Periodo::deRequest($request);
        $f = $request->validate([
            'resultado' => ['nullable', Rule::in(array_keys(MetricasAdmin::ROTULOS_RESULTADO_CNJ))],
            'origem' => ['nullable', Rule::in(array_keys(MetricasAdmin::ROTULOS_ORIGEM_CNJ))],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $chamadas = $metricas->chamadas($periodo)
            ->when($f['resultado'] ?? null, fn ($q, $v) => $q->where('resultado', $v))
            ->when($f['origem'] ?? null, fn ($q, $v) => $q->where('origem', $v))
            ->latest('id')
            ->paginate(self::POR_PAGINA, pageName: 'pagina')
            ->withQueryString();

        return Inertia::render('Admin/ChamadasCnj', [
            'periodo' => $periodo->toArray(),
            'presets' => self::presets(),
            'filtros' => ['resultado' => $f['resultado'] ?? null, 'origem' => $f['origem'] ?? null],
            'resultados' => MetricasAdmin::ROTULOS_RESULTADO_CNJ,
            'origens' => MetricasAdmin::ROTULOS_ORIGEM_CNJ,
            'resumo' => [
                'total' => $metricas->chamadas($periodo)->count(),
                ...$metricas->cnj($periodo),
            ],
            'chamadas' => [
                'data' => $chamadas->getCollection()->map(fn (ChamadaDjen $c) => [
                    'id' => $c->id,
                    'quando' => self::quando($c->created_at),
                    'origem' => $c->origem,
                    'endpoint' => $c->endpoint,
                    'parametros' => $c->parametros === [] ? (object) [] : $c->parametros,
                    'status' => $c->status,
                    'resultado' => $c->resultado,
                    'duracaoMs' => $c->duracao_ms,
                    'itens' => $c->itens,
                    'ratelimitRestante' => $c->ratelimit_restante,
                    'erro' => $c->erro,
                ])->all(),
                'meta' => self::meta($chamadas),
            ],
        ]);
    }
}
