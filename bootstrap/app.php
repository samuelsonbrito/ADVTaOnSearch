<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // Páginas de erro próprias (em português) no lugar das telas padrão.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            $status = $response->getStatusCode();

            if ($request->expectsJson() || ! in_array($status, [403, 404, 419, 429, 500, 503], true)) {
                return $response;
            }

            // Em desenvolvimento, 500 continua mostrando o detalhe do erro.
            if ($status === 500 && config('app.debug')) {
                return $response;
            }

            // Sessão/CSRF expirada: volta para a página com um aviso.
            if ($status === 419) {
                Inertia::flash('toast', ['type' => 'error', 'message' => 'Sua sessão expirou. Tente de novo.']);

                return back();
            }

            $retryAfter = $response->headers->get('Retry-After');

            return Inertia::render('Erro', [
                'status' => $status,
                'tenteEmSegundos' => is_numeric($retryAfter) ? (int) $retryAfter : null,
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
