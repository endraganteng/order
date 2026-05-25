<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class WaiterAuthMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! session()->has('waiter_authenticated') || ! session('waiter_id')) {
            if ($request->expectsJson() || $request->ajax()) {
                return response()->json(['error' => 'Sesi habis, silakan login ulang.'], 401);
            }

            return redirect()->to(route('waiter.login', [], false));
        }

        return $next($request);
    }
}
