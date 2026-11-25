<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'role' => \App\Http\Middleware\Role::class,
        ]);

    })
    ->withExceptions(function (Exceptions $exceptions) {
        // An expired CSRF token (419) happens when a form was loaded before the
        // session changed: logging in/out in another tab, pressing Back after
        // logging out, or the sessions table being reset by a re-seed. Instead
        // of a bare "Page Expired" screen, send the user back to the form with
        // their input and a fresh token so they can simply submit again.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() !== 419 || $request->expectsJson() || $request->isMethod('GET')) {
                return null;
            }

            return redirect()->back()
                ->withInput($request->except(['_token', 'password', 'password_confirmation', 'current_password']))
                ->with('error', 'Your session expired, so that form was not submitted. Please try again.');
        });
    })->create();
