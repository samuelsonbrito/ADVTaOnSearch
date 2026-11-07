<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/** Contas, com o uso de cada uma e o último acesso. */
class UsuariosController extends Controller
{
    use Concerns;

    public const POR_PAGINA = 30;

    public function __invoke(Request $request): Response
    {
        $f = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $usuarios = User::query()
            ->select(['id', 'name', 'email', 'whatsapp', 'is_admin', 'email_verified_at', 'created_at'])
            ->withCount(['monitoringTargets as alvos', 'exportacoes'])
            ->addSelect([
                'buscas' => DB::table('search_logs')->selectRaw('count(*)')->whereColumn('search_logs.user_id', 'users.id'),
                'ultimo_acesso' => DB::table('activity_logs')->selectRaw('max(created_at)')->whereColumn('activity_logs.user_id', 'users.id'),
                'alertas' => DB::table('communication_target')
                    ->join('monitoring_targets', 'monitoring_targets.id', '=', 'communication_target.monitoring_target_id')
                    ->selectRaw('count(*)')
                    ->whereColumn('monitoring_targets.user_id', 'users.id'),
            ])
            ->when($f['q'] ?? null, function ($q, string $busca) {
                $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $busca).'%';
                $q->where(fn ($w) => $w->where('name', 'ilike', $like)->orWhere('email', 'ilike', $like));
            })
            ->orderByRaw('ultimo_acesso desc nulls last')
            ->orderByDesc('id')
            ->paginate(self::POR_PAGINA, pageName: 'pagina')
            ->withQueryString();

        return Inertia::render('Admin/Usuarios', [
            'filtros' => ['q' => $f['q'] ?? null],
            'totais' => [
                'usuarios' => User::count(),
                'verificados' => User::whereNotNull('email_verified_at')->count(),
                'admins' => User::where('is_admin', true)->count(),
                'comWhatsapp' => User::whereNotNull('whatsapp')->count(),
            ],
            'usuarios' => [
                'data' => $usuarios->getCollection()->map(fn (User $u) => [
                    'id' => $u->id,
                    'nome' => $u->name,
                    'email' => $u->email,
                    'admin' => $u->is_admin,
                    'verificado' => $u->email_verified_at !== null,
                    'whatsapp' => $u->whatsapp !== null,
                    'cadastro' => self::quando($u->created_at),
                    'ultimoAcesso' => $u->ultimo_acesso ? self::quando(Carbon::parse($u->ultimo_acesso, 'UTC')) : null,
                    'alvos' => (int) $u->alvos,
                    'alertas' => (int) $u->alertas,
                    'buscas' => (int) $u->buscas,
                    'exportacoes' => (int) $u->exportacoes_count,
                ])->all(),
                'meta' => self::meta($usuarios),
            ],
        ]);
    }
}
