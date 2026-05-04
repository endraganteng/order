<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (session()->has('admin_authenticated')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    public function login(LoginRequest $request)
    {
        if ($request->password === config('admin.password')) {
            session()->put('admin_authenticated', true);

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['password' => 'Password salah']);
    }

    public function logout()
    {
        session()->forget('admin_authenticated');

        return redirect()->route('admin.login');
    }
}
