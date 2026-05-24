@extends('admin.layout')

@section('title', 'Dashboard Keuangan')

@push('styles')
<link rel="stylesheet" href="{{ asset('css/finance.css') }}">
<script src="{{ asset('js/finance-rupiah.js') }}" defer></script>
@endpush

@section('content')
<div class="finance-module">
    <div class="fm-page-header">
        <div>
            <h1 class="fm-page-title">📊 Dashboard Keuangan</h1>
            <p class="fm-page-subtitle">Ringkasan keuangan dari data shift kasir</p>
        </div>
        <button class="fm-btn fm-btn-primary" id="btnSyncToday">🔄 Refresh Data Hari Ini</button>
        <a href="{{ route('admin.finance.mutations') }}" class="fm-btn fm-btn-outline">📒 Mutasi Kas</a>
    </div>

    {{-- Status Sync --}}
    <div class="fm-alert fm-alert-info" id="syncStatus">
        @if($lastSync)
            Sync terakhir: {{ \Carbon\Carbon::parse($lastSync['created_at'])->format('d M Y H:i') }}
            — <span class="fm-badge fm-badge-{{ $lastSync['status'] }}">{{ $lastSync['status'] }}</span>
        @else
            Belum pernah sync. Klik "Refresh Data Hari Ini" untuk memulai.
        @endif
    </div>

    {{-- Alert Hutang Jatuh Tempo --}}
    @if(($debtSummary['jatuh_tempo_minggu_ini'] ?? 0) > 0)
    <div class="fm-alert fm-alert-warning" style="margin-top:8px;">
        ⚠️ <strong>{{ $debtSummary['jatuh_tempo_minggu_ini'] }} hutang jatuh tempo minggu ini</strong>
        — Total hutang aktif: Rp {{ number_format($debtSummary['total_hutang'] ?? 0, 0, ',', '.') }}
        <a href="{{ route('admin.finance.debts') }}" style="margin-left:8px; font-weight:600;">Lihat →</a>
    </div>
    @endif

    {{-- Saldo Kas per Akun (Cards - paling atas) --}}
    @if(count($accounts) > 0)
    @php $totalSemuaKas = collect($accounts)->sum('balance'); @endphp
    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <h3 style="font-size:15px;font-weight:700;">💰 Saldo Kas per Akun</h3>
        <button class="fm-btn fm-btn-sm fm-btn-outline" onclick="openAddAccount()">+ Tambah Akun</button>
    </div>
    <div style="background:linear-gradient(135deg,#1e40af,#3b82f6);border-radius:12px;padding:20px 24px;margin-bottom:16px;color:white;display:flex;justify-content:space-between;align-items:center;">
        <div>
            <div style="font-size:13px;opacity:0.85;">Total Semua Akun Kas</div>
            <div style="font-size:28px;font-weight:800;margin-top:4px;">Rp {{ number_format($totalSemuaKas, 0, ',', '.') }}</div>
        </div>
        <div style="font-size:13px;opacity:0.85;text-align:right;">
            <div>{{ count($accounts) }} akun aktif</div>
        </div>
    </div>
    <div class="fm-cards">
        @foreach($accounts as $acc)
        <div class="fm-card {{ $acc['balance'] > 0 ? 'green' : ($acc['balance'] < 0 ? 'red' : '') }}">
            <div style="display:flex;justify-content:space-between;align-items:start;">
                <div class="fm-card-icon">🏦</div>
                <div style="position:relative;" class="fm-kebab-wrap">
                    <button class="fm-btn fm-btn-sm fm-btn-outline" style="font-size:14px;padding:2px 8px;line-height:1;" onclick="toggleKebab(this)">⋮</button>
                    <div class="fm-kebab-menu" style="display:none;position:absolute;right:0;top:100%;background:white;border:1px solid #e2e8f0;border-radius:6px;box-shadow:0 4px 12px rgba(0,0,0,0.1);z-index:100;min-width:140px;padding:4px 0;">
                        <a href="#" onclick="openEditAccount({{ json_encode($acc) }});closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#334155;text-decoration:none;">✏️ Edit Akun</a>
                        <a href="#" onclick="resetAccount({{ $acc['id'] }},'{{ $acc['name'] }}');closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#dc2626;text-decoration:none;">🔄 Reset Saldo</a>
                        <a href="#" onclick="toggleAccount({{ $acc['id'] }});closeKebabs();return false;" style="display:block;padding:8px 12px;font-size:12px;color:#64748b;text-decoration:none;">{{ $acc['is_active'] ? '🚫 Nonaktifkan' : '✅ Aktifkan' }}</a>
                    </div>
                </div>
            </div>
            <div class="fm-card-value fm-money {{ $acc['balance'] >= 0 ? 'income' : 'expense' }}">Rp {{ number_format($acc['balance'], 0, ',', '.') }}</div>
            <div class="fm-card-label">{{ $acc['name'] }}</div>
            <div style="display:flex;gap:4px;margin-top:8px;flex-wrap:wrap;">
                <button class="fm-btn fm-btn-sm fm-btn-success" style="font-size:11px;flex:1;" onclick="openDeposit({{ $acc['id'] }}, '{{ $acc['name'] }}')">+ Tambah</button>
                <button class="fm-btn fm-btn-sm fm-btn-outline" style="font-size:11px;flex:1;" onclick="openTransfer({{ $acc['id'] }}, '{{ $acc['name'] }}', {{ $acc['balance'] }})">↔️ Transfer</button>
                <button class="fm-btn fm-btn-sm fm-btn-danger" style="font-size:11px;flex:1;" onclick="openExpense({{ $acc['id'] }}, '{{ $acc['name'] }}')">💸 Bayar</button>
                <button class="fm-btn fm-btn-sm fm-btn-outline" style="font-size:11px;flex:1;" onclick="openCorrection({{ $acc['id'] }}, '{{ $acc['name'] }}', {{ $acc['balance'] }})">🔧 Koreksi</button>
            </div>
        </div>
        @endforeach
    </div>
    @else
    <div class="fm-empty" style="margin-bottom:20px;">
        <div class="fm-empty-icon">🏦</div>
        <div class="fm-empty-text">Belum ada akun kas. <button class="fm-btn fm-btn-sm fm-btn-primary" onclick="openAddAccount()">+ Tambah Akun</button></div>
    </div>
    @endif

    {{-- Modal Tambah/Edit Akun Kas --}}
    <div class="fm-modal-backdrop" id="accountModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="accModalTitle">Tambah Akun Kas</span>
                <button class="fm-modal-close" onclick="closeAccountModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="accountForm">
                    <input type="hidden" name="acc_id" id="accId">
                    <div class="fm-form-group">
                        <label class="fm-label">Nama Akun</label>
                        <input type="text" class="fm-input" name="name" id="accName" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Kode</label>
                        <input type="text" class="fm-input" name="code" id="accCode" placeholder="kas_laci, brankas, dll" required>
                    </div>
                    <div class="fm-form-group" id="accBalanceGroup">
                        <label class="fm-label">Saldo Awal (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="balance" id="accBalance" value="0">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeAccountModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="saveAccount()">Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal Transfer Cepat --}}
    <div class="fm-modal-backdrop" id="transferModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">↔️ Transfer Kas</span>
                <button class="fm-modal-close" onclick="closeTransfer()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="quickTransferForm">
                    <div class="fm-form-group">
                        <label class="fm-label">Dari Akun</label>
                        <select class="fm-select" name="from_account_id" id="tfFrom" required>
                            @foreach($accounts as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }} (Rp {{ number_format($a['balance'],0,',','.') }})</option>@endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Ke Akun</label>
                        <select class="fm-select" name="to_account_id" id="tfTo" required>
                            @foreach($accounts as $a)<option value="{{ $a['id'] }}">{{ $a['name'] }}</option>@endforeach
                        </select>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="amount" id="tfAmount" min="1" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Catatan (opsional)</label>
                        <input type="text" class="fm-input" name="notes" placeholder="Keterangan transfer">
                    </div>
                    <input type="hidden" name="status" value="pending">
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeTransfer()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitTransfer()">↔️ Transfer</button>
            </div>
        </div>
    </div>

    {{-- Modal Pengeluaran Cepat --}}
    <div class="fm-modal-backdrop" id="expenseModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">💸 Catat Pengeluaran</span>
                <button class="fm-modal-close" onclick="closeExpense()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="quickExpenseForm">
                    <input type="hidden" name="cash_account_id" id="expAccId">
                    <div class="fm-alert fm-alert-info" id="expAccLabel" style="margin-bottom:12px;"></div>
                    <div class="fm-form-group">
                        <label class="fm-label">Kategori Pengeluaran</label>
                        <select class="fm-select" name="finance_category_id" id="expModalCat" required onchange="toggleExpSupplier(this);checkExpBudget(this.value)">
                            @foreach($categories as $c)
                            <option value="{{ $c['id'] }}" data-name="{{ strtolower($c['name']) }}">{{ $c['name'] }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div id="expBudgetWarn" style="display:none;border-radius:6px;padding:10px;margin-bottom:12px;font-size:13px;"></div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="total_amount" id="expTotal" required oninput="calcDebtDisplay()">
                    </div>
                    <div class="fm-form-group">
                        <label style="cursor:pointer;font-size:13px;display:flex;align-items:center;gap:8px;">
                            <input type="checkbox" id="expHasDebt" onchange="toggleDebtFields()"> Ada hutang tempo (belum bayar penuh)
                        </label>
                    </div>
                    <div id="expDebtFields" style="display:none;background:#fefce8;border:1px solid #fde68a;border-radius:8px;padding:12px;margin-bottom:12px;">
                        <div class="fm-form-group" style="margin-bottom:10px;">
                            <label class="fm-label">Bayar Cash Sekarang (Rp)</label>
                            <input type="number" class="fm-input fm-rupiah" name="cash_amount" id="expCash" placeholder="Kosongkan jika belum bayar sama sekali" oninput="calcDebtDisplay()">
                        </div>
                        <div id="expDebtDisplay" style="display:none;background:#fff;border:1px solid #fde68a;border-radius:6px;padding:8px 12px;margin-bottom:10px;font-size:13px;font-weight:600;color:#92400e;">
                            ⏳ Hutang: <span id="expDebtAmount">Rp 0</span>
                        </div>
                        <div class="fm-form-group" style="margin-bottom:10px;">
                            <label class="fm-label">Nama Supplier</label>
                            <input type="text" class="fm-input" name="supplier_name" placeholder="Nama supplier">
                        </div>
                        <div class="fm-form-group" style="margin-bottom:0;">
                            <label class="fm-label">Jatuh Tempo</label>
                            <input type="date" class="fm-input" name="due_date">
                        </div>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan</label>
                        <input type="text" class="fm-input" name="description" placeholder="Beli pakan, bayar listrik, dll" required>
                    </div>
                    <input type="hidden" name="transaction_date" value="{{ date('Y-m-d') }}">
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeExpense()">Batal</button>
                <button class="fm-btn fm-btn-danger" onclick="submitExpense()">💸 Simpan</button>
            </div>
        </div>
    </div>

    {{-- Modal Deposit / Tambah Saldo --}}
    <div class="fm-modal-backdrop" id="depositModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">+ Tambah Saldo</span>
                <button class="fm-modal-close" onclick="closeDeposit()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="depositForm">
                    <input type="hidden" name="cash_account_id" id="depAccId">
                    <div class="fm-alert fm-alert-info" id="depAccLabel" style="margin-bottom:12px;"></div>
                    <div class="fm-form-group">
                        <label class="fm-label">Jumlah (Rp)</label>
                        <input type="number" class="fm-input fm-rupiah" name="amount" required>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan</label>
                        <input type="text" class="fm-input" name="description" placeholder="Setor modal, terima pembayaran, koreksi saldo, dll" required>
                    </div>
                    <input type="hidden" name="transaction_date" value="{{ date('Y-m-d') }}">
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeDeposit()">Batal</button>
                <button class="fm-btn fm-btn-success" onclick="submitDeposit()">+ Tambah Saldo</button>
            </div>
        </div>
    </div>

    {{-- Modal Konfirmasi (pengganti confirm()) --}}
    <div class="fm-modal-backdrop" id="confirmModal">
        <div class="fm-modal" style="max-width:380px;">
            <div class="fm-modal-header">
                <span class="fm-modal-title" id="confirmTitle">Konfirmasi</span>
                <button class="fm-modal-close" onclick="closeConfirmModal()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <p id="confirmMessage" style="font-size:14px;color:#334155;white-space:pre-line;"></p>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeConfirmModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" id="confirmOkBtn" onclick="doConfirmOk()">Ya, Lanjutkan</button>
            </div>
        </div>
    </div>

    {{-- Modal PIN Supervisor --}}
    <div class="fm-modal-backdrop" id="pinModal" style="z-index:2100;">
        <div class="fm-modal" style="max-width:320px;">
            <div class="fm-modal-header">
                <span class="fm-modal-title">🔐 PIN Supervisor</span>
                <button class="fm-modal-close" onclick="closePinModal()">&times;</button>
            </div>
            <div class="fm-modal-body" style="text-align:center;">
                <p style="font-size:13px;color:#64748b;margin-bottom:16px;">Masukkan PIN supervisor untuk melanjutkan aksi ini.</p>
                <input type="password" id="pinInput" maxlength="6" placeholder="••••••"
                    style="width:160px;text-align:center;font-size:24px;letter-spacing:8px;padding:12px;border:2px solid #e2e8f0;border-radius:8px;"
                    onkeydown="if(event.key==='Enter')submitPin()">
                <p id="pinError" style="display:none;color:#dc2626;font-size:12px;margin-top:8px;">PIN salah</p>
            </div>
            <div class="fm-modal-footer" style="justify-content:center;">
                <button class="fm-btn fm-btn-outline" onclick="closePinModal()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitPin()">Konfirmasi</button>
            </div>
        </div>
    </div>

    {{-- Modal Koreksi Saldo --}}
    <div class="fm-modal-backdrop" id="correctionModal">
        <div class="fm-modal">
            <div class="fm-modal-header">
                <span class="fm-modal-title">🔧 Koreksi Saldo</span>
                <button class="fm-modal-close" onclick="closeCorrection()">&times;</button>
            </div>
            <div class="fm-modal-body">
                <form id="correctionForm">
                    <input type="hidden" name="cash_account_id" id="corAccId">
                    <div class="fm-alert fm-alert-info" id="corAccLabel" style="margin-bottom:12px;"></div>
                    <div class="fm-form-group">
                        <label class="fm-label">Saldo Riil Saat Ini (hitung fisik)</label>
                        <input type="number" class="fm-input fm-rupiah" name="actual_balance" id="corActual" min="0" required oninput="calcCorrectionDiff()">
                    </div>
                    <div id="corDiffInfo" style="display:none;background:#f8fafc;border:1px solid #e2e8f0;border-radius:6px;padding:10px;margin-bottom:12px;font-size:13px;">
                        Selisih: <strong id="corDiffAmount">Rp 0</strong> <span id="corDiffLabel"></span>
                    </div>
                    <div class="fm-form-group">
                        <label class="fm-label">Keterangan (opsional)</label>
                        <input type="text" class="fm-input" name="description" placeholder="Koreksi pengeluaran tidak tercatat Mei 2026">
                    </div>
                </form>
            </div>
            <div class="fm-modal-footer">
                <button class="fm-btn fm-btn-outline" onclick="closeCorrection()">Batal</button>
                <button class="fm-btn fm-btn-primary" onclick="submitCorrection()">🔧 Koreksi Saldo</button>
            </div>
        </div>
    </div>

    <div id="toast" class="fm-toast"></div>

    {{-- Pending Transfers - Approval --}}
    @if(count($pendingTransfers) > 0)
    <div class="fm-alert fm-alert-warning" style="margin-bottom:16px;">
        ⏳ <strong>{{ count($pendingTransfers) }} transfer menunggu approval</strong>
    </div>
    <div class="fm-table-wrap" style="margin-bottom:20px;">
        <table class="fm-table">
            <thead><tr><th>Dari</th><th>Ke</th><th style="text-align:right">Jumlah</th><th>Catatan</th><th>Aksi</th></tr></thead>
            <tbody>
                @foreach($pendingTransfers as $t)
                <tr data-id="{{ $t['id'] }}">
                    <td>{{ $t['from_name'] }}</td>
                    <td>{{ $t['to_name'] }}</td>
                    <td style="text-align:right" class="fm-money">Rp {{ number_format($t['amount'], 0, ',', '.') }}</td>
                    <td style="font-size:12px;">{{ $t['notes'] ?? '—' }}</td>
                    <td style="white-space:nowrap;">
                        <button class="fm-btn fm-btn-sm fm-btn-success" onclick="approveTransfer({{ $t['id'] }})">✅ Approve</button>
                        <button class="fm-btn fm-btn-sm fm-btn-danger" onclick="rejectTransfer({{ $t['id'] }})">❌ Reject</button>
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @endif

    {{-- Summary Hari Ini & Bulan Ini - Compact --}}
    <div style="display:grid; grid-template-columns:1fr 1fr; gap:16px; margin-top:20px;">
        {{-- Hari Ini --}}
        <div class="fm-card" style="padding:0; overflow:hidden;">
            <div style="background:linear-gradient(135deg,#059669,#10b981); color:white; padding:12px 16px; font-weight:700; font-size:14px;">
                📅 Hari Ini — {{ date('d M Y') }}
            </div>
            <table style="width:100%; font-size:13px; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Tunai</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600;" class="fm-money income" id="todayTunai">Rp {{ number_format($today['penjualan_tunai'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">QRIS</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600;" class="fm-money income" id="todayQris">Rp {{ number_format($today['penjualan_qris'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Total Pendapatan</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:700; color:#059669;" id="todayPendapatan">Rp {{ number_format($today['total_pendapatan'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Pengeluaran</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600; color:#dc2626;" id="todayPengeluaran">Rp {{ number_format($today['total_pengeluaran'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Bersih</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:700; color:#059669;" id="todayBersih">Rp {{ number_format($today['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr>
                    <td style="padding:10px 16px; color:#64748b;">Shift</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600;" id="todayShift">{{ $today['jumlah_shift'] ?? 0 }}</td>
                </tr>
            </table>
        </div>

        {{-- Bulan Ini --}}
        <div class="fm-card" style="padding:0; overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e40af,#3b82f6); color:white; padding:12px 16px; font-weight:700; font-size:14px;">
                📊 Bulan Ini — {{ date('F Y') }}
            </div>
            <table style="width:100%; font-size:13px; border-collapse:collapse;">
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Total Pendapatan</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:700; color:#059669;">Rp {{ number_format($month['total_pendapatan'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Total Pengeluaran</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600; color:#dc2626;">Rp {{ number_format($month['total_pengeluaran'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Pendapatan Bersih</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:700; color:#059669;">Rp {{ number_format($month['pendapatan_bersih'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Selisih Kas</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600; color:{{ ($month['total_selisih'] ?? 0) < 0 ? '#dc2626' : '#059669' }};">Rp {{ number_format($month['total_selisih'] ?? 0, 0, ',', '.') }}</td>
                </tr>
                <tr style="border-bottom:1px solid #f1f5f9;">
                    <td style="padding:10px 16px; color:#64748b;">Hari Tersync</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600;">{{ $month['days_synced'] ?? 0 }} hari</td>
                </tr>
                <tr>
                    <td style="padding:10px 16px; color:#64748b;">Total Shift</td>
                    <td style="padding:10px 16px; text-align:right; font-weight:600;">{{ $month['jumlah_shift'] ?? 0 }}</td>
                </tr>
            </table>
        </div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const csrf = document.querySelector('meta[name=csrf-token]').content;

function showToast(msg, type='success') {
    const t = document.getElementById('toast');
    t.textContent = msg;
    t.className = 'fm-toast ' + type + ' show';
    setTimeout(() => t.classList.remove('show'), 3000);
}

// Confirm modal (pengganti confirm())
let confirmCallback = null;

function showConfirm(message, callback, title = 'Konfirmasi', btnText = 'Ya, Lanjutkan') {
    document.getElementById('confirmTitle').textContent = title;
    document.getElementById('confirmMessage').textContent = message;
    document.getElementById('confirmOkBtn').textContent = btnText;
    confirmCallback = callback;
    document.getElementById('confirmModal').classList.add('active');
}

function closeConfirmModal() {
    document.getElementById('confirmModal').classList.remove('active');
    confirmCallback = null;
}

function doConfirmOk() {
    const cb = confirmCallback;
    closeConfirmModal();
    if (cb) cb();
}

// PIN prompt helper — custom modal
let pinCallback = null;

function promptPin() { return null; } // unused, replaced by async version

function requestPin(callback) {
    pinCallback = callback;
    document.getElementById('pinInput').value = '';
    document.getElementById('pinError').style.display = 'none';
    document.getElementById('pinModal').classList.add('active');
    setTimeout(() => document.getElementById('pinInput').focus(), 100);
}

function closePinModal() {
    document.getElementById('pinModal').classList.remove('active');
    pinCallback = null;
}

function submitPin() {
    const pin = document.getElementById('pinInput').value.trim();
    if (pin.length < 4) {
        document.getElementById('pinError').style.display = '';
        document.getElementById('pinError').textContent = 'PIN minimal 4 digit';
        return;
    }
    const cb = pinCallback;
    closePinModal();
    if (cb) cb(pin);
}

// Transfer
function openTransfer(fromId, fromName, balance) {
    document.getElementById('tfFrom').value = fromId;
    document.getElementById('transferModal').classList.add('active');
}

function closeTransfer() {
    document.getElementById('transferModal').classList.remove('active');
    document.getElementById('quickTransferForm').reset();
}

async function submitTransfer() {
    const fd = new FormData(document.getElementById('quickTransferForm'));
    const body = Object.fromEntries(fd);

    if (body.from_account_id === body.to_account_id) {
        showToast('Akun sumber dan tujuan tidak boleh sama', 'error');
        return;
    }

    try {
        const res = await fetch('{{ route("admin.finance.transfers.store") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) {
            showToast('Transfer dibuat! Status: pending (perlu approval)');
            closeTransfer();
            setTimeout(() => location.reload(), 1000);
        } else {
            showToast(data.message || 'Gagal', 'error');
        }
    } catch (e) {
        showToast(e.message, 'error');
    }
}

async function approveTransfer(id) {
    showConfirm('Approve transfer ini? Saldo akan langsung berubah.', async function() {
        try {
            const res = await fetch('{{ url("admin/finance/transfers") }}/' + id + '/approve', {
                method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
            });
            const data = await res.json();
            if (data.success) { showToast('Transfer approved!'); setTimeout(() => location.reload(), 800); }
            else showToast(data.message || 'Gagal (saldo tidak cukup?)', 'error');
        } catch (e) { showToast(e.message, 'error'); }
    }, '↔️ Approve Transfer', 'Approve');
}

async function rejectTransfer(id) {
    showConfirm('Reject transfer ini?', async function() {
        try {
            const res = await fetch('{{ url("admin/finance/transfers") }}/' + id + '/reject', {
                method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json'}
            });
            const data = await res.json();
            if (data.success) { showToast('Transfer rejected.'); document.querySelector(`tr[data-id="${id}"]`).remove(); }
        } catch (e) { showToast(e.message, 'error'); }
    }, '❌ Reject Transfer', 'Reject');
}

// Deposit
function openDeposit(accId, accName) {
    document.getElementById('depAccId').value = accId;
    document.getElementById('depAccLabel').innerHTML = '💰 Ke: <strong>' + accName + '</strong>';
    document.getElementById('depositModal').classList.add('active');
}

function closeDeposit() {
    document.getElementById('depositModal').classList.remove('active');
    document.getElementById('depositForm').reset();
}

async function submitDeposit() {
    const fd = new FormData(document.getElementById('depositForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    body.amount = parseInt((body.amount+'').replace(/\./g,'')) || 0;

    if (body.amount < 1) { showToast('Jumlah harus diisi', 'error'); return; }

    try {
        const res = await fetch('{{ route("admin.finance.deposit") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { showToast('Saldo ditambahkan!'); closeDeposit(); setTimeout(() => location.reload(), 600); }
        else showToast(data.message || 'Gagal', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

// Kebab Menu
function toggleKebab(btn) {
    closeKebabs();
    const menu = btn.nextElementSibling;
    menu.style.display = menu.style.display === 'none' ? 'block' : 'none';
}

function closeKebabs() {
    document.querySelectorAll('.fm-kebab-menu').forEach(m => m.style.display = 'none');
}

document.addEventListener('click', function(e) {
    if (!e.target.closest('.fm-kebab-wrap')) closeKebabs();
});

async function resetAccount(id, name) {
    showConfirm('Reset saldo "' + name + '" ke Rp 0 dan hapus semua mutasi?\n\nAksi ini tidak bisa dibatalkan!', function() {
        requestPin(async function(pin) {
            try {
                const res = await fetch('{{ url("admin/finance/cash-accounts") }}/' + id + '/reset', {
                    method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                    body: JSON.stringify({supervisor_pin: pin})
                });
                const data = await res.json();
                if (data.pin_required) { showToast('PIN supervisor salah', 'error'); return; }
                if (data.success) { showToast('Saldo direset!'); setTimeout(() => location.reload(), 600); }
                else showToast(data.message || 'Gagal', 'error');
            } catch (e) { showToast(e.message, 'error'); }
        });
    }, '🔄 Reset Saldo', 'Ya, Reset');
}

async function toggleAccount(id) {
    requestPin(async function(pin) {
        try {
            const res = await fetch('{{ url("admin/finance/cash-accounts") }}/' + id + '/toggle', {
                method: 'POST', headers: {'X-CSRF-TOKEN': csrf, 'Accept': 'application/json', 'Content-Type': 'application/json'},
                body: JSON.stringify({supervisor_pin: pin})
            });
            const data = await res.json();
            if (data.pin_required) { showToast('PIN supervisor salah', 'error'); return; }
            if (data.success) { showToast('Status diubah!'); setTimeout(() => location.reload(), 600); }
        } catch (e) { showToast(e.message, 'error'); }
    });
}

// Account CRUD
function openAddAccount() {
    document.getElementById('accModalTitle').textContent = 'Tambah Akun Kas';
    document.getElementById('accId').value = '';
    document.getElementById('accBalanceGroup').style.display = '';
    document.getElementById('accountForm').reset();
    document.getElementById('accountModal').classList.add('active');
}

function openEditAccount(acc) {
    document.getElementById('accModalTitle').textContent = 'Edit Akun Kas';
    document.getElementById('accId').value = acc.id;
    document.getElementById('accName').value = acc.name;
    document.getElementById('accCode').value = acc.code;
    document.getElementById('accBalanceGroup').style.display = 'none';
    document.getElementById('accountModal').classList.add('active');
}

function closeAccountModal() {
    document.getElementById('accountModal').classList.remove('active');
}

async function saveAccount() {
    const fd = new FormData(document.getElementById('accountForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    const id = body.acc_id; delete body.acc_id;
    if (body.balance) body.balance = parseInt((body.balance+'').replace(/\./g,'')) || 0;

    const url = id ? '{{ url("admin/finance/cash-accounts") }}/' + id : '{{ route("admin.finance.cash_accounts.store") }}';
    try {
        const res = await fetch(url, {
            method: id ? 'PUT' : 'POST',
            headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
            body: JSON.stringify(body)
        });
        const data = await res.json();
        if (data.success) { showToast(id ? 'Akun diupdate!' : 'Akun ditambahkan!'); closeAccountModal(); setTimeout(() => location.reload(), 600); }
        else showToast(data.message || 'Gagal', 'error');
    } catch (e) { showToast(e.message, 'error'); }
}

// Expense
function toggleExpSupplier(select) {}

function toggleDebtFields() {
    const show = document.getElementById('expHasDebt').checked;
    document.getElementById('expDebtFields').style.display = show ? '' : 'none';
    if (!show) {
        document.getElementById('expCash').value = '';
        document.getElementById('expDebtDisplay').style.display = 'none';
    }
}

function calcDebtDisplay() {
    const total = parseInt((document.getElementById('expTotal').value+'').replace(/\./g,'')) || 0;
    const cash = parseInt((document.getElementById('expCash').value+'').replace(/\./g,'')) || 0;
    const debt = Math.max(0, total - cash);
    const el = document.getElementById('expDebtDisplay');
    if (debt > 0 && total > 0) {
        el.style.display = '';
        document.getElementById('expDebtAmount').textContent = 'Rp ' + debt.toLocaleString('id');
    } else {
        el.style.display = 'none';
    }
}

function calcExpDebt() {}

function openExpense(accId, accName) {
    document.getElementById('expAccId').value = accId;
    document.getElementById('expAccLabel').innerHTML = '💰 Dari: <strong>' + accName + '</strong>';
    document.getElementById('expBudgetWarn').style.display = 'none';
    document.getElementById('expenseModal').classList.add('active');
    checkExpBudget(document.getElementById('expModalCat').value);
}

async function checkExpBudget(categoryId) {
    const el = document.getElementById('expBudgetWarn');
    if (!categoryId) { el.style.display = 'none'; return; }
    try {
        const res = await fetch('{{ route("admin.finance.check_budget") }}?finance_category_id=' + categoryId, {
            headers: {'Accept': 'application/json'}
        });
        const data = await res.json();
        if (!data.has_budget) { el.style.display = 'none'; return; }

        const pct = parseFloat(data.pct_used);
        el.style.display = '';
        if (pct >= 100) {
            el.style.background = '#fef2f2';
            el.style.border = '1px solid #fca5a5';
            el.innerHTML = '🚫 <strong>OVER BUDGET!</strong> ' + data.category_name + ' sudah terpakai ' + pct + '% (Rp ' + data.realisasi.toLocaleString('id') + ' / Rp ' + data.budget.toLocaleString('id') + '). Perlu PIN supervisor untuk lanjut.';
        } else if (pct >= 80) {
            el.style.background = '#fef9c3';
            el.style.border = '1px solid #fde68a';
            el.innerHTML = '⚠️ <strong>Hampir penuh!</strong> ' + data.category_name + ': ' + pct + '% terpakai. Sisa Rp ' + data.sisa.toLocaleString('id');
        } else {
            el.style.background = '#d1fae5';
            el.style.border = '1px solid #6ee7b7';
            el.innerHTML = '✅ ' + data.category_name + ': ' + pct + '% terpakai. Sisa Rp ' + data.sisa.toLocaleString('id');
        }
    } catch (e) { el.style.display = 'none'; }
}

function closeExpense() {
    document.getElementById('expenseModal').classList.remove('active');
    document.getElementById('quickExpenseForm').reset();
}

async function submitExpense() {
    const fd = new FormData(document.getElementById('quickExpenseForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    body.total_amount = parseInt((body.total_amount+'').replace(/\./g,'')) || 0;

    const hasDebt = document.getElementById('expHasDebt').checked;
    if (hasDebt) {
        body.cash_amount = parseInt((body.cash_amount+'').replace(/\./g,'')) || 0;
    } else {
        body.cash_amount = body.total_amount;
    }

    if (body.total_amount < 1) { showToast('Jumlah harus diisi', 'error'); return; }

    // Check if over budget — require PIN
    const warnEl = document.getElementById('expBudgetWarn');
    const isOverBudget = warnEl.style.display !== 'none' && warnEl.innerHTML.includes('OVER BUDGET');

    async function doSubmitExpense(pinOverride) {
        if (pinOverride) body.supervisor_pin = pinOverride;
        try {
            const res = await fetch('{{ route("admin.finance.expenses.store") }}', {
                method: 'POST',
                headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                body: JSON.stringify(body)
            });
            const data = await res.json();
            if (data.success) {
                const debt = body.total_amount - body.cash_amount;
                showToast('Pengeluaran dicatat!' + (debt > 0 ? ' Hutang Rp ' + debt.toLocaleString('id') : ''));
                closeExpense();
                setTimeout(() => location.reload(), 800);
            } else {
                showToast(data.message || 'Gagal', 'error');
            }
        } catch (e) { showToast(e.message, 'error'); }
    }

    if (isOverBudget) {
        showConfirm('Kategori ini sudah OVER BUDGET. Tetap lanjutkan pengeluaran?\n\nDiperlukan PIN supervisor.', function() {
            requestPin(function(pin) { doSubmitExpense(pin); });
        }, '🚫 Over Budget', 'Ya, Lanjutkan');
    } else {
        doSubmitExpense();
    }
}

// Correction
let corCurrentBalance = 0;

function openCorrection(accId, accName, balance) {
    corCurrentBalance = balance;
    document.getElementById('corAccId').value = accId;
    document.getElementById('corAccLabel').innerHTML = '🏦 <strong>' + accName + '</strong> — Saldo sistem: Rp ' + balance.toLocaleString('id');
    document.getElementById('corActual').value = '';
    document.getElementById('corDiffInfo').style.display = 'none';
    document.getElementById('correctionModal').classList.add('active');
}

function closeCorrection() {
    document.getElementById('correctionModal').classList.remove('active');
    document.getElementById('correctionForm').reset();
}

function calcCorrectionDiff() {
    const actual = parseInt((document.getElementById('corActual').value+'').replace(/\./g,'')) || 0;
    const diff = actual - corCurrentBalance;
    if (diff === 0) {
        document.getElementById('corDiffInfo').style.display = 'none';
        return;
    }
    document.getElementById('corDiffInfo').style.display = '';
    document.getElementById('corDiffAmount').textContent = (diff > 0 ? '+' : '') + 'Rp ' + diff.toLocaleString('id');
    document.getElementById('corDiffLabel').textContent = diff > 0 ? '(uang lebih dari sistem)' : '(uang kurang dari sistem — ada pengeluaran tidak tercatat)';
    document.getElementById('corDiffInfo').style.background = diff > 0 ? '#d1fae5' : '#fef2f2';
    document.getElementById('corDiffInfo').style.borderColor = diff > 0 ? '#6ee7b7' : '#fca5a5';
}

async function submitCorrection() {
    const fd = new FormData(document.getElementById('correctionForm'));
    const body = {};
    fd.forEach((v, k) => body[k] = v);
    body.actual_balance = parseInt((body.actual_balance+'').replace(/\./g,'')) || 0;

    const diff = body.actual_balance - corCurrentBalance;
    if (diff === 0) { showToast('Saldo sudah sesuai'); closeCorrection(); return; }

    closeCorrection();
    const msg = 'Koreksi saldo?\n\nSaldo sistem: Rp ' + corCurrentBalance.toLocaleString('id') + '\nSaldo riil: Rp ' + body.actual_balance.toLocaleString('id') + '\nSelisih: ' + (diff > 0 ? '+' : '') + 'Rp ' + diff.toLocaleString('id');
    showConfirm(msg, function() {
        requestPin(async function(pin) {
            body.supervisor_pin = pin;
            try {
                const res = await fetch('{{ route("admin.finance.correct_balance") }}', {
                    method: 'POST',
                    headers: {'X-CSRF-TOKEN': csrf, 'Content-Type': 'application/json', 'Accept': 'application/json'},
                    body: JSON.stringify(body)
                });
                const data = await res.json();
                if (data.pin_required) { showToast('PIN supervisor salah', 'error'); return; }
                if (data.success) {
                    showToast('Saldo dikoreksi! Selisih ' + (diff > 0 ? '+' : '') + 'Rp ' + diff.toLocaleString('id') + ' tercatat.');
                    closeCorrection();
                    setTimeout(() => location.reload(), 800);
                } else {
                    showToast(data.message || 'Gagal', 'error');
                }
            } catch (e) { showToast(e.message, 'error'); }
        });
    }, '🔧 Koreksi Saldo', 'Ya, Koreksi');
}

// Sync Today
document.getElementById('btnSyncToday').addEventListener('click', async function() {
    const btn = this;
    btn.disabled = true;
    btn.textContent = '⏳ Syncing...';

    try {
        const res = await fetch('{{ route("admin.finance.sync.today") }}', {
            method: 'POST',
            headers: {'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content, 'Accept': 'application/json'}
        });
        const data = await res.json();

        if (data.success) {
            document.getElementById('syncStatus').className = 'fm-alert fm-alert-success';
            document.getElementById('syncStatus').innerHTML = '✅ Sync berhasil! ' + data.synced + ' record tersinkronisasi. <a href="" style="margin-left:8px" onclick="location.reload()">Refresh halaman</a>';
        } else {
            document.getElementById('syncStatus').className = 'fm-alert fm-alert-error';
            document.getElementById('syncStatus').textContent = '❌ Sync gagal: ' + (data.message || data.status);
        }
    } catch (e) {
        document.getElementById('syncStatus').className = 'fm-alert fm-alert-error';
        document.getElementById('syncStatus').textContent = '❌ Error: ' + e.message;
    }

    btn.disabled = false;
    btn.textContent = '🔄 Refresh Data Hari Ini';
});
</script>
@endpush
