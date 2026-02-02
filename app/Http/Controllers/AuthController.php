<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request) {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);
        $user = User::where('email', $request->email)->first();
        if (! $user || ! Hash::check($request->password, $user->password)) {
            return response()->json([
                'message' => 'Las credenciales son incorrectas.',
            ], 401);
        }
        //TODO Esto borra todos los tokens previos del usuario (Cierra sesiones en otros dispositivos)
        $user->tokens()->delete();
        $token = $user->createToken('MiLaravelApiToken:' . $request->email)->plainTextToken;
        return response()->json([
            'access_token' => $token,
            'roles' => $user->roles->pluck('name')->toArray(),
        ]);
    }
}
