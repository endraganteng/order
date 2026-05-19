<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        // Default Categories
        $categories = [
            ['name' => 'Gaji & Operasional', 'type' => 'expense', 'sort_order' => 1],
            ['name' => 'Pemilik', 'type' => 'expense', 'sort_order' => 2],
            ['name' => 'Restok / Modal Barang', 'type' => 'expense', 'sort_order' => 3],
            ['name' => 'Penjualan Toko', 'type' => 'income', 'sort_order' => 1],
            ['name' => 'Pemasukan Lain', 'type' => 'income', 'sort_order' => 2],
        ];

        foreach ($categories as $cat) {
            DB::table('finance_categories')->insertOrIgnore(array_merge($cat, ['is_active' => true, 'created_at' => now(), 'updated_at' => now()]));
        }

        // Default Cash Accounts
        $accounts = [
            ['name' => 'Kas Toko', 'code' => 'kas_toko', 'sort_order' => 1],
            ['name' => 'Kas Kecil', 'code' => 'kas_kecil', 'sort_order' => 2],
            ['name' => 'Rekening Bank', 'code' => 'rekening_bank', 'sort_order' => 3],
            ['name' => 'QRIS', 'code' => 'qris', 'sort_order' => 4],
            ['name' => 'Kas Operasional', 'code' => 'kas_operasional', 'sort_order' => 5],
            ['name' => 'Kas Restok', 'code' => 'kas_restok', 'sort_order' => 6],
            ['name' => 'Dana Pemilik', 'code' => 'dana_pemilik', 'sort_order' => 7],
        ];

        foreach ($accounts as $acc) {
            DB::table('cash_accounts')->insertOrIgnore(array_merge($acc, ['balance' => 0, 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]));
        }

        // Default Allocations
        $catIds = DB::table('finance_categories')->where('type', 'expense')->pluck('id', 'name');
        $allocations = [
            ['name' => 'Gaji & Operasional', 'percentage' => 7.5],
            ['name' => 'Pemilik', 'percentage' => 18.6],
            ['name' => 'Restok / Modal Barang', 'percentage' => 73.9],
        ];

        foreach ($allocations as $alloc) {
            if (isset($catIds[$alloc['name']])) {
                DB::table('finance_allocations')->insertOrIgnore([
                    'finance_category_id' => $catIds[$alloc['name']],
                    'percentage' => $alloc['percentage'],
                    'effective_date' => date('Y-m-01'),
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Default Category Mappings
        $mappings = [
            ['api_key' => 'line_type', 'api_value' => 'product', 'target' => 'Restok / Modal Barang'],
            ['api_key' => 'line_type', 'api_value' => 'kasbon', 'target' => 'Gaji & Operasional'],
            ['api_key' => 'line_type', 'api_value' => 'custom', 'target' => null], // need_review
        ];

        foreach ($mappings as $m) {
            if ($m['target'] === null) continue; // custom → no mapping = need_review by default
            if (isset($catIds[$m['target']])) {
                DB::table('finance_api_mappings')->insertOrIgnore([
                    'mapping_type' => 'category',
                    'api_key' => $m['api_key'],
                    'api_value' => $m['api_value'],
                    'target_id' => $catIds[$m['target']],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        // Default Account Mappings
        $accIds = DB::table('cash_accounts')->pluck('id', 'code');
        $accMappings = [
            ['api_key' => 'source', 'api_value' => 'penjualan_tunai', 'target' => 'kas_toko'],
            ['api_key' => 'source', 'api_value' => 'penjualan_qris', 'target' => 'qris'],
            ['api_key' => 'source', 'api_value' => 'pengeluaran_shift', 'target' => 'kas_toko'],
        ];

        foreach ($accMappings as $m) {
            if (isset($accIds[$m['target']])) {
                DB::table('finance_api_mappings')->insertOrIgnore([
                    'mapping_type' => 'cash_account',
                    'api_key' => $m['api_key'],
                    'api_value' => $m['api_value'],
                    'target_id' => $accIds[$m['target']],
                    'is_active' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }
}
