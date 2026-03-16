<?php

namespace App\Http\Controllers;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    /**
     * Inicia sesión y genera un nuevo token JWT
     */
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user || !Hash::check($request->password, $user->password)) {
            return response()->json(['message' => 'Credenciales incorrectas'], 401);
        }
        // 1. Detectar tipo de dispositivo
        $deviceType = $this->getDeviceType($request);
        $user->tokens()->where('name', $deviceType)->delete();

        // 3. Auditoría de inicio de sesión
        $this->logger()->security("Inicio de sesión exitoso", [
            'device' => $deviceType,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

        return $this->issueToken($user, $deviceType);
    }

    /**
     * Renueva el token JWT
     */
    public function refresh(Request $request)
    {
        $user = $request->user();
        $deviceType = $this->getDeviceType($request);
        $request->user()->currentAccessToken()->delete();
        $this->logger()->security("Token renovado (Refresh)", [
            'device' => $deviceType,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);
        return $this->issueToken($user, $deviceType);
    }

    /**
     * Genera el JWT envolviendo el token de Sanctum
     */
    private function issueToken(User $user, string $deviceType)
    {
        // El nombre del token en la DB será 'android' o 'pc'
        $sanctumToken = $user->createToken($deviceType)->plainTextToken;

        $roles = $user->getRoleNames();
        $payload = [
            'iat' => time(),
            'ts'  => (int)(microtime(true) * 1000),
            'device' => $deviceType,
            'user' => [
                'id' => $user->id,
                'name' => $user->name
            ],
            'sk'  => $sanctumToken,
            'roles' => $roles
        ];

        $jwt = JWT::encode($payload, config('jwt.secret'), 'HS256');

        return response()->json([
            'access_token' => $jwt,
            'roles' => $user->getRoleNames(),
        ]);
    }

    /**
     * Lógica simple para detectar dispositivo
     */
    private function getDeviceType(Request $request): string
    {
        $agent = strtolower($request->userAgent());
        if (str_contains($agent, 'android')) {
            return 'android';
        }
        return 'pc';
    }
}
