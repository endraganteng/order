<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSettingsRequest;
use App\Services\FirebaseService;
use App\Services\FonnteService;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    protected $firebase;
    protected $fonnte;

    public function __construct(FirebaseService $firebase, FonnteService $fonnte)
    {
        $this->firebase = $firebase;
        $this->fonnte = $fonnte;
    }

    public function show()
    {
        $settings = $this->firebase->getSettings();
        $settings['supervisor_pin'] = app(\App\Services\FinanceService::class)->getSetting('supervisor_pin');

        $featureFlags = $this->resolveFeatureFlags();

        return view('admin.settings', compact('settings', 'featureFlags'));
    }

    /**
     * Resolve current value of UI-toggleable feature flags (DB override
     * already applied to config at boot, so config() reflects effective value).
     */
    private function resolveFeatureFlags(): array
    {
        $keys = [
            'mysql_cashier_tasks',
            'legacy_write_cashier_tasks',
            'legacy_write_waiter_tasks',
            'legacy_write_attendance',
            'legacy_write_bonus_summary',
            'legacy_write_penalties',
        ];

        $flags = [];
        foreach ($keys as $key) {
            $flags[$key] = (bool) config('features.'.$key);
        }

        return $flags;
    }

    public function updateFeatureFlags(Request $request)
    {
        $svc = app(\App\Services\FeatureFlagService::class);
        $allowed = [
            'mysql_cashier_tasks',
            'legacy_write_cashier_tasks',
            'legacy_write_waiter_tasks',
            'legacy_write_attendance',
            'legacy_write_bonus_summary',
            'legacy_write_penalties',
        ];

        foreach ($allowed as $key) {
            $svc->set($key, $request->boolean($key));
        }

        $this->firebase->logAuditAction('update', 'feature_flags', null, $request->only($allowed));

        return back()->with('success', 'Feature flags berhasil diupdate');
    }

    public function update(UpdateSettingsRequest $request)
    {
        $this->firebase->updateSettings([
            'order_timeout_minutes' => (int) $request->order_timeout_minutes,
            'fonnte_api_token' => $request->fonnte_api_token ?: '',
            'fonnte_enabled' => (bool) $request->fonnte_enabled,
            'report_phone' => $request->report_phone ?: '',
            'auto_report_enabled' => (bool) $request->auto_report_enabled,
            'clock_out_enabled' => (bool) $request->clock_out_enabled,
            'attendance_use_global_qr' => (bool) $request->attendance_use_global_qr,
        ]);

        // Save supervisor PIN to finance_settings (MySQL) if provided
        if ($request->filled('supervisor_pin')) {
            $pin = $request->supervisor_pin;
            if (strlen($pin) >= 4 && strlen($pin) <= 6 && ctype_digit($pin)) {
                app(\App\Services\FinanceService::class)->setSetting('supervisor_pin', bcrypt($pin));
            }
        }

        $this->firebase->logAuditAction('update', 'settings', null, ['timeout' => (int) $request->order_timeout_minutes]);

        return back()->with('success', 'Settings berhasil diupdate');
    }

    public function testFonnte(Request $request)
    {
        $request->validate([
            'test_phone' => 'required|string|max:20',
            'test_message' => 'nullable|string|max:500',
        ]);

        if (!$this->fonnte->isEnabled()) {
            return back()->with('fonnte_error', 'Fonnte belum aktif. Aktifkan dan isi API token terlebih dahulu, lalu simpan.');
        }

        $phone = $request->test_phone;
        $message = $request->test_message ?: "✅ *TEST BERHASIL*\n\nIntegrasi Fonnte dengan sistem berhasil. Notifikasi WhatsApp aktif.";

        $result = $this->fonnte->sendMessage($phone, $message);

        if ($result && ($result['status'] ?? false)) {
            return back()->with('fonnte_success', "Pesan test berhasil dikirim ke {$phone}");
        }

        $reason = $result['reason'] ?? 'Tidak ada respons dari Fonnte';
        return back()->with('fonnte_error', "Gagal kirim: {$reason}");
    }
}
