@extends('admin.layout')

@section('title', '⚙️ Konfigurasi Bonus')

@section('content')
<div class="container">
    <div class="page-header">
        <h2>⚙️ Konfigurasi Bonus</h2>
    </div>

    <form id="configForm" method="POST" action="{{ route('admin.bonus.config.update') }}">
        @csrf
        <div class="card">
            <h3>Pengaturan Umum</h3>
            <div class="form-group">
                <label for="working_days_per_month">Hari Kerja Per Bulan</label>
                <input type="number" id="working_days_per_month" name="working_days_per_month" value="{{ $config['working_days_per_month'] ?? 26 }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="total_bonus_pool">Total Pool Bonus (Rp)</label>
                <input type="number" id="total_bonus_pool" name="total_bonus_pool" value="{{ $config['total_bonus_pool'] ?? 500000 }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="perfect_day_bonus">Bonus Poin Hari Sempurna</label>
                <input type="number" id="perfect_day_bonus" name="perfect_day_bonus" value="{{ $config['perfect_day_bonus'] ?? 5 }}" class="form-control" required>
            </div>
            <div class="form-group">
                <label for="daily_max_points">Maksimal Poin Harian (Total)</label>
                <input type="number" id="daily_max_points" name="daily_max_points" value="{{ $config['daily_max_points'] ?? 20 }}" class="form-control" required readonly>
            </div>
        </div>

        <div class="card mt-4">
            <h3>Kategori Poin Harian</h3>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Kategori</th>
                            <th>Maksimal Poin</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Disiplin</td>
                            <td><input type="number" name="cat_discipline_max" class="form-control cat-input" value="{{ $config['point_categories']['discipline']['max_daily_points'] ?? 5 }}" required></td>
                        </tr>
                        <tr>
                            <td>Operasional</td>
                            <td><input type="number" name="cat_operational_max" class="form-control cat-input" value="{{ $config['point_categories']['operational']['max_daily_points'] ?? 10 }}" required></td>
                        </tr>
                        <tr>
                            <td>Pelayanan</td>
                            <td><input type="number" name="cat_service_max" class="form-control cat-input" value="{{ $config['point_categories']['service']['max_daily_points'] ?? 5 }}" required></td>
                        </tr>
                        <tr>
                            <td>Penjualan</td>
                            <td><input type="number" name="cat_sales_max" class="form-control cat-input" value="{{ $config['point_categories']['sales']['max_daily_points'] ?? 5 }}" required></td>
                        </tr>
                        <tr>
                            <td>Sikap</td>
                            <td><input type="number" name="cat_attitude_max" class="form-control cat-input" value="{{ $config['point_categories']['attitude']['max_daily_points'] ?? 5 }}" required></td>
                        </tr>
                        <tr class="font-weight-bold bg-light">
                            <td>Total Poin Harian</td>
                            <td><span id="totalPointsDisplay">{{ $config['daily_max_points'] ?? 20 }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <h3>Tier Bonus Poin (Persentase)</h3>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Minimal Persentase (%)</th>
                            <th>Bonus (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tier 1 (≥80%)</td>
                            <td><input type="number" name="pt_tier1_min_pct" value="{{ $config['point_bonus_tiers']['tier_1']['min_percentage'] ?? 80 }}" class="form-control" required></td>
                            <td><input type="number" name="pt_tier1_bonus" value="{{ $config['point_bonus_tiers']['tier_1']['bonus_amount'] ?? 300000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 2 (70-79%)</td>
                            <td><input type="number" name="pt_tier2_min_pct" value="{{ $config['point_bonus_tiers']['tier_2']['min_percentage'] ?? 70 }}" class="form-control" required></td>
                            <td><input type="number" name="pt_tier2_bonus" value="{{ $config['point_bonus_tiers']['tier_2']['bonus_amount'] ?? 250000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 3 (60-69%)</td>
                            <td><input type="number" name="pt_tier3_min_pct" value="{{ $config['point_bonus_tiers']['tier_3']['min_percentage'] ?? 60 }}" class="form-control" required></td>
                            <td><input type="number" name="pt_tier3_bonus" value="{{ $config['point_bonus_tiers']['tier_3']['bonus_amount'] ?? 200000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 4 (<60%)</td>
                            <td><input type="number" value="0" class="form-control" readonly></td>
                            <td><input type="number" value="0" class="form-control" readonly></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card mt-4">
            <h3>Tier Bonus Penjualan</h3>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tier</th>
                            <th>Target (%)</th>
                            <th>Bonus (Rp)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>Tier 1 (≥100%)</td>
                            <td><input type="number" name="st_tier1_min_pct" value="{{ $config['sales_bonus_tiers']['tier_1']['min_percentage'] ?? 100 }}" class="form-control" required></td>
                            <td><input type="number" name="st_tier1_bonus" value="{{ $config['sales_bonus_tiers']['tier_1']['bonus_amount'] ?? 200000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 2 (≥80%)</td>
                            <td><input type="number" name="st_tier2_min_pct" value="{{ $config['sales_bonus_tiers']['tier_2']['min_percentage'] ?? 80 }}" class="form-control" required></td>
                            <td><input type="number" name="st_tier2_bonus" value="{{ $config['sales_bonus_tiers']['tier_2']['bonus_amount'] ?? 150000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 3 (≥60%)</td>
                            <td><input type="number" name="st_tier3_min_pct" value="{{ $config['sales_bonus_tiers']['tier_3']['min_percentage'] ?? 60 }}" class="form-control" required></td>
                            <td><input type="number" name="st_tier3_bonus" value="{{ $config['sales_bonus_tiers']['tier_3']['bonus_amount'] ?? 100000 }}" class="form-control" required></td>
                        </tr>
                        <tr>
                            <td>Tier 4 (<60%)</td>
                            <td><input type="number" value="0" class="form-control" readonly></td>
                            <td><input type="number" value="0" class="form-control" readonly></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="card mt-4">
            <h3>Tipe Penalti</h3>
            <div class="table-scroll">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Tipe Penalti</th>
                            <th>Pengurangan Poin</th>
                            <th>Keterangan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><span class="badge badge-danger">Tidak Hadir / No-show</span></td>
                            <td>-15 poin</td>
                            <td>Otomatis dari audit absensi terjadwal</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-danger">Keterlambatan</span></td>
                            <td>-5 poin</td>
                            <td>Otomatis saat status absensi terlambat</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-danger">Tugas Wajib Terlewat</span></td>
                            <td>-10 poin</td>
                            <td>Otomatis saat tugas wajib menjadi overdue</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">Kerja Kurang Teliti</span></td>
                            <td>-10 poin</td>
                            <td>Penalti manual untuk pekerjaan asal-asalan</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-warning">Bukti Foto Kurang</span></td>
                            <td>-5 poin</td>
                            <td>Penalti manual saat bukti foto tidak ada/lengkap</td>
                        </tr>
                        <tr>
                            <td><span class="badge badge-danger">Komplain Valid</span></td>
                            <td>-10 poin</td>
                            <td>Penalti manual untuk komplain pelanggan yang valid</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="mt-4" style="margin-bottom: 2rem;">
            <button type="submit" class="btn btn-primary btn-block">
                <i class="fas fa-save"></i> Simpan Konfigurasi
            </button>
        </div>
    </form>
</div>
@endsection

@push('styles')
<style>
    .page-header {
        margin-bottom: 20px;
    }
    .form-group {
        margin-bottom: 1rem;
    }
    .form-group label {
        display: block;
        margin-bottom: 0.5rem;
        font-weight: 500;
        color: var(--color-text);
    }
    .form-control {
        width: 100%;
        padding: 0.75rem;
        border: 1px solid var(--color-border);
        border-radius: var(--radius-sm);
        font-family: inherit;
        font-size: 1rem;
        transition: border-color 0.2s;
    }
    .form-control:focus {
        outline: none;
        border-color: var(--color-primary);
        box-shadow: 0 0 0 3px var(--color-primary-bg);
    }
    .form-control[readonly] {
        background-color: var(--color-bg);
        color: var(--color-text-muted);
    }
    .mt-4 {
        margin-top: 1.5rem;
    }
    .table th, .table td {
        padding: 0.75rem;
        text-align: left;
        border-bottom: 1px solid var(--color-border);
    }
    .font-weight-bold {
        font-weight: bold;
    }
    .bg-light {
        background-color: var(--color-bg);
    }
    .btn-block {
        display: block;
        width: 100%;
        padding: 1rem;
        font-size: 1.1rem;
    }
</style>
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function() {
    const catInputs = document.querySelectorAll('.cat-input');
    const totalDisplay = document.getElementById('totalPointsDisplay');
    const maxPointsInput = document.getElementById('daily_max_points');
    
    function updateTotal() {
        let total = 0;
        catInputs.forEach(input => {
            total += parseInt(input.value) || 0;
        });
        totalDisplay.textContent = total;
        maxPointsInput.value = total;
    }
    
    catInputs.forEach(input => {
        input.addEventListener('input', updateTotal);
    });
    
    // AJAX Form Submit
    const form = document.getElementById('configForm');
    form.addEventListener('submit', async function(e) {
        e.preventDefault();
        
        const btn = form.querySelector('button[type="submit"]');
        const originalText = btn.innerHTML;
        btn.innerHTML = 'Menyimpan...';
        btn.disabled = true;
        
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        try {
            const response = await fetch(form.action, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
                    'Accept': 'application/json'
                },
                body: JSON.stringify(data)
            });
            
            if (response.ok) {
                alert('Konfigurasi berhasil disimpan!');
            } else {
                alert('Gagal menyimpan konfigurasi.');
            }
        } catch (error) {
            console.error('Error:', error);
            alert('Terjadi kesalahan saat menyimpan.');
        } finally {
            btn.innerHTML = originalText;
            btn.disabled = false;
        }
    });
});
</script>
@endpush
