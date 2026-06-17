<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        commands: __DIR__.'/../routes/console.php',
        using: function () {
            $namespace = 'App\Http\Controllers';

            Route::middleware('web')
                ->namespace($namespace)
                ->group(base_path('routes/web.php'));

            Route::middleware('api')
                ->prefix('api')
                ->namespace($namespace)
                ->group(base_path('routes/api.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->use([
            \App\Http\Middleware\TrustProxies::class,
            \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
            \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
            \App\Http\Middleware\TrimStrings::class,
            \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
        ]);

        $middleware->alias([
            'auth' => \App\Http\Middleware\Authenticate::class,
            'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
            'language' => \App\Http\Middleware\Language::class,
            'timezone' => \App\Http\Middleware\Timezone::class,
            'SetSessionData' => \App\Http\Middleware\SetSessionData::class,
            'setData' => \App\Http\Middleware\IsInstalled::class,
            'authh' => \App\Http\Middleware\IsInstalled::class,
            'EcomApi' => \App\Http\Middleware\EcomApi::class,
            'AdminSidebarMenu' => \App\Http\Middleware\AdminSidebarMenu::class,
            'superadmin' => \App\Http\Middleware\Superadmin::class,
            'CheckUserLogin' => \App\Http\Middleware\CheckUserLogin::class,
            'allow_registration' => \App\Http\Middleware\AllowRegistration::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })
    ->create();
