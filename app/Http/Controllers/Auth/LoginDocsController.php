<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginDocsController extends Controller
{
    // Muestra el formulario
    public function show()
    {
        return view('auth.login-docs');
    }

    // Procesa el ingreso
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials)) {
            // Verificamos si es super-admin
            if (Auth::user()->hasRole('super-admin')) {
                $request->session()->regenerate();
                return redirect()->intended('/docs/api');
            }

            // Si no es admin, lo sacamos
            Auth::logout();
            return back()->withErrors(['email' => 'No tienes permisos de Super Admin.']);
        }

        return back()->withErrors(['email' => 'Las credenciales no coinciden.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login-docs');
    }
}
