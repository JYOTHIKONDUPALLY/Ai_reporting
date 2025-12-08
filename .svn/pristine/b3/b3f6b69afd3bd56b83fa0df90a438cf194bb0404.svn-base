<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Session;

class AuthController extends Controller
{
    public function showLogin()
    {
        // If already authenticated, redirect to reports
        if (session()->has('authenticated') && session('authenticated') === true) {
            return redirect()->route('reports.index');
        }

        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $username = $request->input('username');
        $password = $request->input('password');

        // Check credentials
        if ($username === 'admin' && $password === 'admin@123!') {
            session(['authenticated' => true]);
            return redirect()->intended(route('reports.index'));
        }

        return back()->withErrors([
            'credentials' => 'Invalid username or password.',
        ])->withInput($request->only('username'));
    }

    public function logout()
    {
        session()->forget('authenticated');
        return redirect()->route('login');
    }
}

