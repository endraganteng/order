<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\FirebaseService;
use App\Services\KasbonService;
use Illuminate\Http\Request;

class KasbonController extends Controller
{
    protected KasbonService $kasbon;

    protected FirebaseService $firebase;

    public function __construct(KasbonService $kasbon, FirebaseService $firebase)
    {
        $this->kasbon = $kasbon;
        $this->firebase = $firebase;
    }

    public function index(Request $request)
    {
        $filters = [
            'status' => $request->get('status'),
            'waiter_id' => $request->get('waiter_id'),
            'from' => $request->get('from'),
            'to' => $request->get('to'),
        ];

        $result = $this->kasbon->listAll($filters, 50);
        $stats = $this->kasbon->getStats();

        // Waiter list for filter dropdown + create modal
        $waiters = collect($this->firebase->getAllowedEmails())
            ->filter(fn($w) => ! empty($w['id']) && ! empty($w['is_active']))
            ->map(fn($w) => [
                'id' => (string) $w['id'],
                'name' => (string) ($w['name'] ?? ''),
                'kasbon_enabled' => (bool) ($w['kasbon_enabled'] ?? false),
            ])
            ->sortBy('name')
            ->values()
            ->toArray();

        return view('admin.kasbon.index', [
            'kasbons' => $result['items'],
            'total' => $result['total'],
            'stats' => $stats,
            'waiters' => $waiters,
            'filters' => $filters,
        ]);
    }

    public function show(int $id)
    {
        $kasbon = $this->kasbon->getById($id);
        if (! $kasbon) {
            return redirect()->route('admin.kasbon.index')->withErrors(['kasbon' => 'Kasbon tidak ditemukan']);
        }

        $payments = $this->kasbon->getPayments($id);
        $limitInfo = $this->kasbon->calculateAvailableLimit($kasbon['waiter_id']);

        return view('admin.kasbon.show', compact('kasbon', 'payments', 'limitInfo'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'waiter_id' => 'required|string',
            'amount' => 'required|integer|min:1',
            'reason' => 'nullable|string|max:500',
        ]);

        $role = session('admin_role', '');
        if ($role !== 'finance' && $role !== 'supervisor') {
            return back()->withErrors(['auth' => 'Hanya Finance yang bisa membuat kasbon.']);
        }

        $createdBy = session('admin_name', $role);
        $result = $this->kasbon->create($data['waiter_id'], (int) $data['amount'], $data['reason'] ?? '', $createdBy);

        if (! $result['success']) {
            return back()->withErrors(['kasbon' => $result['message']])->withInput();
        }

        return redirect()->route('admin.kasbon.index')->with('success', $result['message']);
    }

    public function cancel(int $id)
    {
        $role = session('admin_role', '');
        $cancelledBy = session('admin_name', $role);

        $result = $this->kasbon->cancel($id, $cancelledBy);

        if (! $result['success']) {
            return back()->withErrors(['kasbon' => $result['message']]);
        }

        return redirect()->route('admin.kasbon.show', $id)->with('success', $result['message']);
    }

    public function writeOff(Request $request, int $id)
    {
        $data = $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $role = session('admin_role', '');
        if ($role !== 'supervisor') {
            return back()->withErrors(['auth' => 'Hanya Supervisor yang bisa write-off kasbon.']);
        }

        $writtenOffBy = session('admin_name', $role);
        $result = $this->kasbon->writeOff($id, $data['reason'], $writtenOffBy);

        if (! $result['success']) {
            return back()->withErrors(['kasbon' => $result['message']]);
        }

        return redirect()->route('admin.kasbon.show', $id)->with('success', $result['message']);
    }

    public function settings()
    {
        $config = $this->kasbon->getConfig();
        return view('admin.kasbon.settings', compact('config'));
    }

    public function updateSettings(Request $request)
    {
        $data = $request->validate([
            'default_limit_percent' => 'required|integer|min:1|max:100',
            'kasbon_limit_fixed' => 'required|integer|min:0',
            'min_kasbon_amount' => 'required|integer|min:0',
            'max_active_kasbon' => 'required|integer|min:1|max:10',
            'auto_deduct_enabled' => 'nullable|boolean',
        ]);

        $data['auto_deduct_enabled'] = $request->has('auto_deduct_enabled') ? '1' : '0';
        $this->kasbon->updateConfig($data);

        return redirect()->route('admin.kasbon.settings')->with('success', 'Pengaturan kasbon disimpan.');
    }

    public function updateWaiterSettings(Request $request, string $waiterId)
    {
        $data = $request->validate([
            'kasbon_enabled' => 'nullable|boolean',
            'kasbon_limit_percent' => 'nullable|integer|min:0|max:100',
        ]);

        $data['kasbon_enabled'] = $request->has('kasbon_enabled');
        $this->kasbon->updateWaiterKasbonSettings($waiterId, $data);

        return back()->with('success', 'Pengaturan kasbon karyawan disimpan.');
    }

    public function getLimitInfo(string $waiterId)
    {
        $limitInfo = $this->kasbon->calculateAvailableLimit($waiterId);
        return response()->json($limitInfo);
    }
}
