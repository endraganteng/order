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

        return view('admin.settings', compact('settings'));
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
