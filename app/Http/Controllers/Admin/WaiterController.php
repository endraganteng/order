<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWaiterRequest;
use App\Http\Requests\UpdateWaiterRequest;
use App\Services\FirebaseService;
use Illuminate\Support\Facades\Hash;

class WaiterController extends Controller
{
    protected $firebase;

    public function __construct(FirebaseService $firebase)
    {
        $this->firebase = $firebase;
    }

    public function index()
    {
        $waiters = $this->firebase->getAllowedEmails();

        return view('admin.waiters.index', compact('waiters'));
    }

    public function create()
    {
        $shifts = $this->firebase->getActiveShifts();

        return view('admin.waiters.create', compact('shifts'));
    }

    public function store(StoreWaiterRequest $request)
    {
        $email = strtolower(trim((string) $request->email));
        if ($this->isEmailAlreadyUsed($email)) {
            return back()
                ->withErrors(['email' => 'Email waiter sudah terdaftar. Gunakan email lain.'])
                ->withInput();
        }

        $passwordHash = $request->filled('password')
            ? Hash::make($request->password)
            : null;

        $shiftId = $request->shift_id ?: null;
        $phone = $request->phone ?: null;
        $this->firebase->addAllowedEmailWithPassword($email, $request->name, $passwordHash, $request->waiter_role, $shiftId, $phone);

        $this->firebase->logAuditAction('create', 'waiter', null, ['email' => $email, 'name' => $request->name, 'role' => $request->waiter_role]);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil ditambahkan');
    }

    public function edit($id)
    {
        $waiters = $this->firebase->getAllowedEmails();
        $waiter = collect($waiters)->firstWhere('id', $id);

        if (! $waiter) {
            abort(404);
        }

        $shifts = $this->firebase->getActiveShifts();

        return view('admin.waiters.edit', compact('waiter', 'shifts'));
    }

    public function update(UpdateWaiterRequest $request, $id)
    {
        $email = strtolower(trim((string) $request->email));
        if ($this->isEmailAlreadyUsed($email, (string) $id)) {
            return back()
                ->withErrors(['email' => 'Email waiter sudah digunakan akun lain.'])
                ->withInput();
        }

        $payload = [
            'email' => $email,
            'name' => $request->name,
            'waiter_role' => $request->waiter_role,
            'is_active' => (bool) $request->is_active,
            'shift_id' => $request->shift_id ?: null,
            'phone' => $request->phone ?: null,
            'attendance_exempt' => (bool) $request->attendance_exempt,
        ];

        if ($request->filled('password')) {
            $payload['password_hash'] = Hash::make($request->password);
        }

        $this->firebase->updateAllowedEmail($id, $payload);

        $this->firebase->logAuditAction('update', 'waiter', $id, ['email' => $email, 'name' => $request->name]);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil diupdate');
    }

    public function destroy($id)
    {
        $this->firebase->deleteAllowedEmail($id);

        $this->firebase->logAuditAction('delete', 'waiter', $id, []);

        return redirect()->route('admin.waiters.index')
            ->with('success', 'Waiter berhasil dihapus');
    }

    protected function isEmailAlreadyUsed(string $email, ?string $excludeId = null): bool
    {
        $email = strtolower(trim($email));

        foreach ($this->firebase->getAllowedEmails() as $waiter) {
            $currentId = (string) ($waiter['id'] ?? '');
            if ($excludeId !== null && $currentId === $excludeId) {
                continue;
            }

            $currentEmail = strtolower(trim((string) ($waiter['email'] ?? '')));
            if ($currentEmail !== '' && $currentEmail === $email) {
                return true;
            }
        }

        return false;
    }
}
