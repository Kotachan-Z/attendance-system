<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->validateCsrfTokens(except: [
            'api/*',
        ]);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // 未ログイン時のリダイレクト先: 生徒エリアは生徒ログインへ、それ以外は職員ログインへ
        $middleware->redirectGuestsTo(function ($request) {
            return $request->is('student', 'student/*')
                ? route('student.login')
                : route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
