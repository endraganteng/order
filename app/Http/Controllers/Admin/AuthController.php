<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function showLogin()
    {
        if (session()->has('admin_authenticated') && session()->has('admin_id')) {
            return redirect()->route('admin.dashboard');
        }

        return view('admin.login');
    }

    /**
     * Legacy password login (fallback, uses ADMIN_PASSWORD from .env).
     */
    public function login(Request $request)
    {
        $request->validate([
            'password' => 'required|string',
        ]);

        $configPassword = config('admin.password');
        if ($configPassword && $request->password === $configPassword) {
            $request->session()->regenerate();
            session()->put('admin_authenticated', true);
            session()->put('admin_id', 'legacy_admin');
            session()->put('admin_name', 'Admin');
            session()->put('admin_email', '');
            session()->put('admin_firebase_token', null);

            return redirect()->route('admin.dashboard');
        }

        return back()->withErrors(['password' => 'Password salah']);
    }

    /**
     * Google OAuth login for supervisor accounts.
     */
    public function loginWithGoogle(Request $request)
    {
        $request->validate([
            'id_token' => 'required|string',
        ]);

        $waiter = $this->firebase->verifyWaiterGoogleToken($request->id_token);
        if (! $waiter) {
            return response()->json([
                'success' => false,
                'message' => 'Google login gagal. Pastikan email Google terdaftar sebagai akun aktif.',
            ], 422);
        }

        $role = $waiter['waiter_role'] ?? 'pelayan';
        if ($role !== 'supervisor') {
            return response()->json([
                'success' => false,
                'message' => 'Akun ini bukan supervisor. Hanya akun dengan role supervisor yang bisa login ke panel admin.',
            ], 403);
        }

        $request->session()->regenerate();
        session()->put('admin_authenticated', true);
        session()->put('admin_id', $waiter['id']);
        session()->put('admin_name', $waiter['name'] ?? 'Supervisor');
        session()->put('admin_email', $waiter['email'] ?? '');
        session()->put('admin_firebase_token', $request->id_token);

        return response()->json([
            'success' => true,
            'redirect' => route('admin.dashboard'),
        ]);
    }

    public function logout()
    {
        session()->forget(['admin_authenticated', 'admin_id', 'admin_name', 'admin_email', 'admin_firebase_token']);

        return redirect()->route('admin.login');
    }
}
