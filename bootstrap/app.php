<?php

use App\Http\Middleware\EnsureCompanyMember;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RedirectIfNotInstalled;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // SaaS-only routes. The file doesn't exist in the Picalica package.
            if (config('edition.name') === 'saas' && file_exists($saas = base_path('routes/saas.php'))) {
                Route::middleware('web')->group($saas);
            }
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Every response, including errors and the installer.
        $middleware->prepend(SecurityHeaders::class);

        $middleware->web(append: [
            RedirectIfNotInstalled::class, // after the session starts: the installer keeps its progress there
            HandleInertiaRequests::class,
        ]);

        // Before "auth" (which would send a fresh copy to /login), after the session has started.
        $middleware->prependToPriorityList(AuthenticatesRequests::class, RedirectIfNotInstalled::class);

        $middleware->alias([
            'member' => EnsureCompanyMember::class,
        ]);

        $middleware->redirectGuestsTo('/login');
        $middleware->redirectUsersTo('/');
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Inertia visits get a React error page instead of a raw HTML modal.
        $exceptions->respond(function (Response $response, Throwable $e, Request $request) {
            if (! $request->header('X-Inertia')) {
                return $response;
            }

            $status = $response->getStatusCode();

            if ($status === 419) {
                return back()->with('error', __('ui.errors.419.message'));
            }

            if (in_array($status, [403, 404, 429, 503], true) || ($status === 500 && ! app()->hasDebugModeEnabled())) {
                return Inertia::render('Error', ['status' => $status])
                    ->toResponse($request)
                    ->setStatusCode($status);
            }

            return $response;
        });
    })->create();
