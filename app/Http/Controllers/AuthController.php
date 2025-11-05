<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class AuthController extends Controller
{
    /**
     * Показать форму входа
     */
    public function showLogin()
    {
        return Inertia::render('Auth/Login');
    }

    /**
     * Обработать вход
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            $user = Auth::user();

            // Супер-админа перенаправляем на страницу тенантов
            if ($user->isSuperAdmin()) {
                return redirect()->intended('/admin/tenants');
            }

            // Остальных на календарь
            return redirect()->intended('/');
        }

        return back()->withErrors([
            'email' => 'Неверные учётные данные.',
        ])->onlyInput('email');
    }

    /**
     * Выйти из системы
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
