<?php

namespace App\Services;

use App\Models\RolePermission;
use Illuminate\Support\Facades\Cache;

class RolePermissionService
{
    /**
     * All available permission groups with labels.
     */
    public const GROUPS = [
        'ringkasan' => 'Ringkasan (Dashboard, Current Order, Test Order)',
        'tim_area' => 'Tim & Area (Waiters, Racks, Produk, Kategori, Shift, Jadwal)',
        'operasional' => 'Operasional (Tasks, Cek Rak, Restock, Supplier, Absensi, dll)',
        'bonus' => 'Bonus (Konfigurasi, Penilaian, Penalti, Target, Rekap, Leaderboard)',
        'keuangan' => 'Keuangan (Dashboard, Payroll, Mutasi Kas, Pengeluaran, Hutang)',
        'laporan_keuangan' => 'Laporan Keuangan (Budget, Laporan Bulanan, Saldo, Laba Rugi)',
        'setting_keuangan' => 'Setting Keuangan (Sync, Mapping, Kategori, Alokasi Dana)',
        'sistem' => 'Sistem (Audit Log, Settings)',
        'ai' => 'AI (Products, Chat)',
    ];

    /**
     * Default permissions for finance role (matches current hardcoded behavior).
     */
    public const FINANCE_DEFAULTS = [
        'keuangan',
        'laporan_keuangan',
        'setting_keuangan',
    ];

    /**
     * Route pattern mapping: permission_group => array of route patterns.
     */
    public const ROUTE_MAP = [
        'ringkasan' => ['admin.dashboard', 'admin.current_order.*', 'admin.test_order'],
        'tim_area' => ['admin.waiters.*', 'admin.racks.*', 'admin.products.*', 'admin.product_categories.*', 'admin.shifts.*', 'admin.schedules.*'],
        'operasional' => ['admin.tasks.*', 'admin.restock.*', 'admin.suppliers.*', 'admin.attendance.*', 'admin.reconciliation.*', 'admin.dana_payments.*', 'admin.cleanup'],
        'bonus' => ['admin.bonus.*'],
        'keuangan' => ['admin.finance.dashboard', 'admin.finance_dashboard', 'admin.payroll.*', 'admin.finance.mutations', 'admin.finance.expenses', 'admin.finance.debts', 'admin.finance.shifts', 'admin.finance.transfers', 'admin.finance.cash_accounts', 'admin.finance.need_review', 'admin.finance.deposit', 'admin.finance.correct_balance', 'admin.finance.check_budget'],
        'laporan_keuangan' => ['admin.finance.budget', 'admin.finance.report.*', 'admin.finance.laba_rugi', 'admin.finance.tutup_buku', 'admin.finance.tutup_buku.*', 'admin.finance.audit_log'],
        'setting_keuangan' => ['admin.finance.sync', 'admin.finance.sync.*', 'admin.finance.settings', 'admin.finance.settings.*', 'admin.finance.sync_logs', 'admin.finance.mappings.*', 'admin.finance.categories', 'admin.finance.categories.*', 'admin.finance.allocations', 'admin.finance.allocations.*', 'admin.finance.test_connection'],
        'sistem' => ['admin.audit_log.*', 'admin.settings', 'admin.settings.*'],
        'ai' => ['admin.ai_products.*', 'admin.ai_chat.*'],
    ];

    /**
     * Get cache key for a role.
     */
    private function cacheKey(string $role): string
    {
        return "role_permissions:{$role}";
    }

    /**
     * Get all allowed permission groups for a role.
     *
     * @return array<string>
     */
    public function getAllowedGroups(string $role): array
    {
        // Supervisor always has full access
        if ($role === 'supervisor') {
            return array_keys(self::GROUPS);
        }

        return Cache::remember($this->cacheKey($role), 300, function () use ($role) {
            $permissions = RolePermission::where('role', $role)
                ->where('is_allowed', true)
                ->pluck('permission_group')
                ->toArray();

            // If no permissions configured yet, use defaults
            if (empty($permissions) && $role === 'finance') {
                return self::FINANCE_DEFAULTS;
            }

            return $permissions;
        });
    }

    /**
     * Check if a role has access to a specific permission group.
     */
    public function hasAccess(string $role, string $group): bool
    {
        if ($role === 'supervisor') {
            return true;
        }

        return in_array($group, $this->getAllowedGroups($role));
    }

    /**
     * Check if a role can access a given route name.
     */
    public function canAccessRoute(string $role, ?string $routeName): bool
    {
        if ($role === 'supervisor' || !$routeName) {
            return true;
        }

        // Permission management is supervisor-only
        if (str_starts_with($routeName, 'admin.permissions')) {
            return false;
        }

        $allowedGroups = $this->getAllowedGroups($role);

        foreach (self::ROUTE_MAP as $group => $patterns) {
            foreach ($patterns as $pattern) {
                if ($this->routeMatches($routeName, $pattern)) {
                    return in_array($group, $allowedGroups);
                }
            }
        }

        // Routes not mapped to any group: allow by default (e.g. logout)
        return true;
    }

    /**
     * Simple route pattern matching (supports trailing wildcard *).
     */
    private function routeMatches(string $routeName, string $pattern): bool
    {
        if ($pattern === $routeName) {
            return true;
        }

        if (str_ends_with($pattern, '.*')) {
            $prefix = substr($pattern, 0, -1); // e.g. "admin.tasks."
            return str_starts_with($routeName, $prefix);
        }

        return false;
    }

    /**
     * Get all permissions for a role (for the settings UI).
     *
     * @return array<string, bool>
     */
    public function getPermissionsForRole(string $role): array
    {
        $allowed = $this->getAllowedGroups($role);
        $result = [];

        foreach (self::GROUPS as $group => $label) {
            $result[$group] = in_array($group, $allowed);
        }

        return $result;
    }

    /**
     * Save permissions for a role.
     *
     * @param array<string, bool> $permissions
     */
    public function savePermissions(string $role, array $permissions): void
    {
        foreach (self::GROUPS as $group => $label) {
            RolePermission::updateOrCreate(
                ['role' => $role, 'permission_group' => $group],
                ['is_allowed' => !empty($permissions[$group])]
            );
        }

        Cache::forget($this->cacheKey($role));
    }

    /**
     * Get all roles that have permissions configured.
     *
     * @return array<string>
     */
    public function getConfiguredRoles(): array
    {
        return ['finance']; // Currently only finance; extend as needed
    }
}
