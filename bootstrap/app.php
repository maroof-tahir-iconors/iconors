<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Hostinger serves the site through its CDN/reverse proxy. Without this
        // Laravel sees plain HTTP and generates http:// asset and redirect URLs.
        $middleware->trustProxies(at: '*');

        $middleware->alias([
            'contributor.approved' => \App\Http\Middleware\EnsureContributorApproved::class,
            'role'                 => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission'           => \Spatie\Permission\Middleware\PermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();

/*
 * Shared-hosting layout: the document root is a sibling `public_html`, not
 * this project's own `public/`. Without this, public_path() still points at
 * `<base>/public` and the Vite manifest lookup fails with a 500 on every
 * page that extends the app layout.
 *
 * Guarded by is_dir() so local development, which does serve `public/`,
 * keeps Laravel's default.
 */
if (is_dir($publicHtml = dirname(dirname(__DIR__)).'/public_html')) {
    $app->usePublicPath($publicHtml);
}

return $app;
