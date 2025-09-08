<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    public function showLoginForm()
    {
        return view('auth.login'); // resources/views/auth/login.blade.php
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'username' => ['required', 'alpha_dash'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials)) {
            $request->session()->regenerate();

            // Redirect by role
            $user = Auth::user(); 

            return match ($user->role) {
                'admin' => redirect()->route('admin.employees.index'),
                'user'  => redirect()->route('user.employees.index'),
                default => redirect('/'),
            };
        }

        return back()->withErrors([
            'username' => 'Username atau password salah.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
