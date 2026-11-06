<?php

namespace App\Http\Controllers\Monitoramento;

use App\Enums\TipoAlvo;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConsultaRequest;
use App\Http\Resources\Monitoramento\AlertaResource;
use App\Http\Resources\Monitoramento\MonitoringTargetResource;
use App\Models\Alerta;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Aba Monitoramento: painel de alvos e alertas do usuário. */
class MonitoramentoController extends Controller
{
    public const POR_PAGINA = 20;

    public function __invoke(Request $request): Response
    {
        $user = $request->user();

        $filtros = $request->validate([
            'alvo' => ['nullable', 'integer', Rule::exists('monitoring_targets', 'id')->where('user_id', $user->id)],
            'status' => ['nullable', Rule::in(['nao_lidas', 'lidas', 'todas'])],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);
        $alvoId = isset($filtros['alvo']) ? (int) $filtros['alvo'] : null;
        $status = $filtros['status'] ?? 'nao_lidas';

        $alvos = $user->monitoringTargets()
            ->withCount(['alertas', 'alertas as nao_lidas_count' => fn ($q) => $q->whereNull('lida_em')])
            ->orderBy('created_at')
            ->get();

        $alertas = Alerta::query()
            ->doUsuario($user)
            ->when($alvoId, fn ($q) => $q->where('monitoring_target_id', $alvoId))
            ->when($status === 'nao_lidas', fn ($q) => $q->whereNull('lida_em'))
            ->when($status === 'lidas', fn ($q) => $q->whereNotNull('lida_em'))
            ->join('communications', 'communications.id', '=', 'communication_target.communication_id')
            ->select('communication_target.*')
            ->orderByDesc('communications.data_disponibilizacao')
            ->orderByDesc('communication_target.id')
            ->with(['communication', 'target'])
            ->paginate(self::POR_PAGINA, pageName: 'pagina')
            ->withQueryString();

        return Inertia::render('Monitoramento/Index', [
            'alvos' => MonitoringTargetResource::collection($alvos)->resolve(),
            'alertas' => [
                'data' => AlertaResource::collection($alertas->items())->resolve(),
                'meta' => [
                    'pagina' => $alertas->currentPage(),
                    'porPagina' => $alertas->perPage(),
                    'total' => $alertas->total(),
                    'ultimaPagina' => $alertas->lastPage(),
                ],
            ],
            'filtros' => ['alvo' => $alvoId, 'status' => $status],
            'totalNaoLidas' => (int) $alvos->sum('nao_lidas_count'),
            'notificacoes' => [
                'email' => $user->email,
                'whatsapp' => $user->whatsapp,
                'whatsappDriver' => (string) config('djen.whatsapp.driver'),
                'horario' => (string) config('djen.monitoring.time'),
                'diasAnteriores' => (int) config('djen.monitoring.lookback_days'),
            ],
            'tiposAlvo' => array_map(fn (TipoAlvo $t): array => ['valor' => $t->value, 'rotulo' => $t->rotulo()], TipoAlvo::cases()),
            'ufs' => ConsultaRequest::UFS,
            'limiteAlvos' => (int) config('djen.monitoring.max_alvos'),
        ]);
    }
}
