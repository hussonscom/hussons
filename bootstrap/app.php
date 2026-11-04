<?php

use App\Http\Middleware\ApplyLocale;
use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureOrganizationModule;
use App\Http\Middleware\EnsureOrganizationRole;
use App\Http\Middleware\ResolveOrganizationContext;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [ApplyLocale::class, SecurityHeaders::class]);

        $middleware->alias([
            'active' => EnsureActiveUser::class,
            'organization' => ResolveOrganizationContext::class,
            'organization.module' => EnsureOrganizationModule::class,
            'organization.role' => EnsureOrganizationRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
