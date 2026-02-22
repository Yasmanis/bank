<?php

namespace App\Http\Middleware;

use Closure;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class DecryptSanctumToken
{
    public function handle(Request $request, Closure $next)
    {
        $header = $request->header('Authorization');

        if ($header && str_starts_with($header, 'Bearer ')) {
            try {
                $jwt = str_replace('Bearer ', '', $header);
                $secretKey = config('jwt.secret');

                // Decodifica y VERIFICA la firma
                $decoded = JWT::decode($jwt, new Key($secretKey, 'HS256'));

                if (isset($decoded->device)) {
                    $currentDevice = str_contains(strtolower($request->userAgent()), 'android') ? 'android' : 'pc';
                    if ($decoded->device !== $currentDevice) {
                        return response()->json(['message' => 'No autenticado. El token es inválido o ha expirado.'], 401);
                    }
                }

                if (isset($decoded->sk)) {
                    $request->headers->set('Authorization', 'Bearer ' . $decoded->sk);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'success' => false,
                    'message' => 'No autenticado. El token es inválido o ha expirado.',
                    'errors'  => null
                ], 401);
            }
        }
        return $next($request);
    }
}
