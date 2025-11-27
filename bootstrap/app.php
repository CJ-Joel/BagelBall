<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\DebugSessionMiddleware;
use App\Http\Middleware\DebugSessionPayload;
use App\Http\Middleware\ValidateJwtToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Webhook routes with NO middleware (no CSRF, no sessions, no throttle)
            Route::withoutMiddleware(['web', 'api'])
                ->group(base_path('routes/webhooks.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'eventbrite/sync/run',
            'pregames/validate-order',
            'api/*',
        ]);
        
        // Register JWT middleware
        $middleware->alias([
            'jwt' => ValidateJwtToken::class,
        ]);
        
        // Explicitly configure web middleware - ensure sessions are properly initialized
        $middleware->web([
            DebugSessionPayload::class,  // DEBUG: Log all session data
            \Illuminate\Cookie\Middleware\EncryptCookies::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
