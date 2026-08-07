<?php

use App\Http\Middleware\EnsureUserIsAdmin;
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

        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Pages d'erreur maison (page Inertia « Error »), à la place des écrans
        // par défaut de Laravel. En debug local on laisse passer la trace
        // détaillée, bien plus utile pour développer.
        $exceptions->respond(function (Response $response, Throwable $exception, Request $request) {
            $status = $response->getStatusCode();

            // Session/CSRF expirée : renvoyer l'utilisateur sur sa page avec un
            // message est plus doux qu'un écran d'erreur en pleine face.
            if ($status === 419) {
                return back()->with('error', 'Votre session a expiré, merci de réessayer.');
            }

            if (config('app.debug') || $request->expectsJson()) {
                return $response;
            }

            if (! in_array($status, [401, 403, 404, 429, 500, 503], true)) {
                return $response;
            }

            return Inertia::render('Error', [
                'status' => $status,
                'message' => $exception->getMessage(),
            ])
                ->toResponse($request)
                ->setStatusCode($status);
        });
    })->create();
