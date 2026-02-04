<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->redirectGuestsTo(function (Request $request) {
            if ($request->is('api/*')) {
                return null;
            }
        });

        $middleware->alias([
            'role' => \Spatie\Permission\Middleware\RoleMiddleware::class,
            'permission' => \Spatie\Permission\Middleware\PermissionMiddleware::class,
            'role_or_permission' => \Spatie\Permission\Middleware\RoleOrPermissionMiddleware::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {

        // 1. Error de Autenticación (Token inválido o falta de Token)
        // Esto evita el error "Route [login] not defined"
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. El token es inválido o ha expirado.',
                    'errors'  => null
                ], 401);
            }
        });

        // 2. Error de Validación (Form Requests)
        $exceptions->render(function (\Illuminate\Validation\ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Los datos proporcionados no son válidos.',
                    'errors'  => $e->errors(),
                ], 422);
            }
        });

        // 3. Error de Spatie (Falta de permisos o roles)
        $exceptions->render(function (\Spatie\Permission\Exceptions\UnauthorizedException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'No tienes los permisos necesarios para realizar esta acción.',
                    'errors'  => null
                ], 403);
            }
        });

        // 4. Error 404 (Recurso o Ruta no encontrada)
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'El recurso solicitado no existe.',
                    'errors'  => null
                ], 404);
            }
        });

        // 5. Error genérico (Cualquier otro error inesperado)
        // Opcional: Solo activarlo si quieres que errores 500 también tengan formato JSON

        $exceptions->render(function (\Throwable $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Ocurrió un error inesperado en el servidor.',
                    'errors'  => config('app.debug') ? $e->getMessage() : null
                ], 500);
            }
        });

    })->create();
