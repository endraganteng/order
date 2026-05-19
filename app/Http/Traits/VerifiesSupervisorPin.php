<?php

namespace App\Http\Traits;

use App\Services\FinanceService;
use Illuminate\Support\Facades\Hash;

trait VerifiesSupervisorPin
{
    protected function verifySupervisorPin(?string $pin): bool
    {
        if (session('admin_role') === 'supervisor') {
            return true; // supervisor bypass
        }

        $stored = app(FinanceService::class)->getSetting('supervisor_pin');
        if (! $stored) {
            return true; // PIN belum diset, allow
        }

        return $pin && Hash::check($pin, $stored);
    }

    protected function pinRequired(): bool
    {
        if (session('admin_role') === 'supervisor') {
            return false;
        }

        $stored = app(FinanceService::class)->getSetting('supervisor_pin');
        return ! empty($stored);
    }
}
