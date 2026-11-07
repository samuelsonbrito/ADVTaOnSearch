<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Atividade;
use App\Models\User;
use App\Support\Admin\Periodo;
use App\Support\Rastreio\TipoEvento;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** Trilha de atividade: páginas vistas, login, alvos, alertas, exportações... */
class AtividadeController extends Controller
{
    use Concerns;

    public const POR_PAGINA = 50;

    public function __invoke(Request $request): Response
    {
        $periodo = Periodo::deRequest($request);
        $f = $request->validate([
            'tipo' => ['nullable', Rule::in(array_keys(TipoEvento::ROTULOS))],
            'usuario' => ['nullable', 'integer'],
            'robos' => ['nullable', 'boolean'],
            'pagina' => ['nullable', 'integer', 'min:1'],
        ]);

        $base = Atividade::query()
            ->whereBetween('created_at', [$periodo->inicioUtc(), $periodo->fimUtc()])
            ->when(! ($f['robos'] ?? false), fn ($q) => $q->where('bot', false))
            ->when($f['usuario'] ?? null, fn ($q, $v) => $q->where('user_id', $v));

        $porTipo = (clone $base)->toBase()
            ->selectRaw('tipo, count(*) as total')
            ->groupBy('tipo')
            ->orderByDesc('total')
            ->get()
            ->map(fn ($r) => ['chave' => $r->tipo, 'rotulo' => TipoEvento::rotulo($r->tipo), 'total' => (int) $r->total])
            ->all();

        $eventos = (clone $base)
            ->with('user:id,name,email')
            ->when($f['tipo'] ?? null, fn ($q, $v) => $q->where('tipo', $v))
            ->latest('id')
            ->paginate(self::POR_PAGINA, pageName: 'pagina')
            ->withQueryString();

        $usuario = isset($f['usuario']) ? User::find($f['usuario'], ['id', 'name', 'email']) : null;

        return Inertia::render('Admin/Atividade', [
            'periodo' => $periodo->toArray(),
            'presets' => self::presets(),
            'filtros' => [
                'tipo' => $f['tipo'] ?? null,
                'usuario' => $usuario ? ['id' => $usuario->id, 'nome' => $usuario->name, 'email' => $usuario->email] : null,
                'robos' => (bool) ($f['robos'] ?? false),
            ],
            'tipos' => TipoEvento::ROTULOS,
            'porTipo' => $porTipo,
            'eventos' => [
                'data' => $eventos->getCollection()->map(fn (Atividade $e) => [
                    'id' => $e->id,
                    'quando' => self::quando($e->created_at),
                    'tipo' => $e->tipo,
                    'rotulo' => TipoEvento::rotulo($e->tipo),
                    'dados' => $e->dados ?? (object) [],
                    'usuario' => $e->user ? ['id' => $e->user->id, 'nome' => $e->user->name, 'email' => $e->user->email] : null,
                    'visitante' => $e->visitante,
                    'caminho' => $e->caminho,
                    'dispositivo' => $e->dispositivo,
                    'navegador' => $e->navegador,
                    'sistema' => $e->sistema,
                    'ip' => $e->ip_mascarado,
                    'referencia' => $e->referer_host,
                ])->all(),
                'meta' => self::meta($eventos),
            ],
        ]);
    }
}
