<?php

namespace App\Http\Middleware;

use App\Services\RolePermissionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckRolePermission
{
    public function __construct(
        private RolePermissionService $permissionService
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $role = session('admin_role', 'supervisor');

        // Supervisor always has full access
        if ($role === 'supervisor') {
            return $next($request);
        }

        $routeName = $request->route()?->getName();

        if (!$this->permissionService->canAccessRoute($role, $routeName)) {
            if ($request->expectsJson()) {
                return response()->json(['error' => 'Akses ditolak'], 403);
            }

            return redirect()
                ->route('admin.finance.dashboard')
                ->with('error', 'Anda tidak memiliki akses ke halaman tersebut.');
        }

        return $next($request);
    }
}
