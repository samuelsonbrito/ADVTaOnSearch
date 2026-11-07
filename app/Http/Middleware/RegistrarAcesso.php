<?php

namespace App\Http\Middleware;

use App\Services\Rastreio\Rastreador;
use App\Support\Rastreio\TipoEvento;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra cada página vista (visita completa ou navegação Inertia).
 * Ignora recargas parciais (polling, paginação de props), prefetch, a
 * própria área administrativa, downloads e respostas que não são página.
 * O caminho é gravado sem a query string, que pode ter dados de busca.
 */
class RegistrarAcesso
{
    public function __construct(
        private readonly Rastreador $rastreador,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->deveRegistrar($request, $response)) {
            $this->rastreador->evento(TipoEvento::PAGINA_VISTA, request: $request);
        }

        return $response;
    }

    private function deveRegistrar(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || ! $response->isSuccessful()) {
            return false;
        }

        if ($request->headers->has('X-Inertia-Partial-Component') || $request->headers->get('Purpose') === 'prefetch') {
            return false;
        }

        if ($request->routeIs('admin.*', 'exportacoes.download')) {
            return false;
        }

        $tipo = (string) $response->headers->get('Content-Type');

        return $response->headers->has('X-Inertia') || str_contains($tipo, 'text/html');
    }
}
