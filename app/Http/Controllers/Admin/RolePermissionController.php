<?php

namespace App\Http\Controllers\Admin;

use App\Services\RolePermissionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class RolePermissionController extends Controller
{
    public function __construct(
        private RolePermissionService $permissionService
    ) {}

    /**
     * Show the permissions management page.
     */
    public function index()
    {
        // Only supervisor can access
        if (session('admin_role', 'supervisor') !== 'supervisor') {
            abort(403);
        }

        $roles = $this->permissionService->getConfiguredRoles();
        $groups = RolePermissionService::GROUPS;

        $permissions = [];
        foreach ($roles as $role) {
            $permissions[$role] = $this->permissionService->getPermissionsForRole($role);
        }

        return view('admin.role_permissions', compact('roles', 'groups', 'permissions'));
    }

    /**
     * Save permissions via AJAX.
     */
    public function save(Request $request): JsonResponse
    {
        // Only supervisor can save
        if (session('admin_role', 'supervisor') !== 'supervisor') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'role' => 'required|string|in:' . implode(',', $this->permissionService->getConfiguredRoles()),
            'permissions' => 'required|array',
        ]);

        $this->permissionService->savePermissions(
            $validated['role'],
            $validated['permissions']
        );

        return response()->json([
            'success' => true,
            'message' => 'Permissions berhasil disimpan untuk role: ' . $validated['role'],
        ]);
    }
}
