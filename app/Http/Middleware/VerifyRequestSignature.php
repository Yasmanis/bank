<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyRequestSignature
{
    public function handle(Request $request, Closure $next)
    {
        // 1. Extraer los headers que envía el frontend
        $timestamp = $request->header('X-Request-Timestamp');
        $signature = $request->header('X-Request-Signature');

        if (!$timestamp || !$signature) {
            return response()->json(['success' => false, 'message' => 'Falta firma de seguridad.'], 403);
        }

        // 3. Ventana de tiempo (Anti-Replay)
        // Evita que alguien capture la petición y la use 10 horas después
        $serverTime = (int) (microtime(true) * 1000); // ms
        if (abs($serverTime - (int)$timestamp) > 300000) { // 5 minutos de margen
            return response()->json(['success' => false, 'message' => 'Petición expirada (Reloj desincronizado).'], 403);
        }

        if ($request->isJson()) {
            $bodyToSign = $request->getContent();
        } else {
            $bodyToSign = $request->input('client_uuid', '');
        }

        $secret = config('jwt.sig_secret');
        $computedSignature = hash_hmac('sha256', $timestamp . $bodyToSign, $secret);

        if (!hash_equals($computedSignature, $signature)) {
            Log::warning("Firma inválida", [
                'user' => auth()->id(),
                'ip' => $request->ip(),
                'sent_sig' => $signature,
                'computed_sig' => $computedSignature,
                'payload' => $timestamp . $bodyToSign
            ]);

            return response()->json(['success' => false, 'message' => 'Firma de seguridad inválida.'], 403);
        }

        return $next($request);
    }
}
