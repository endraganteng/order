<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_product_trackings', function (Blueprint $table) {
            $table->decimal('modal_kemarin_qty', 12, 2)->default(0)->after('product_name');
            $table->decimal('harga_per_unit', 15, 2)->default(0)->after('stok_masuk_total');
            $table->decimal('terjual_qty', 12, 2)->default(0)->after('sisa_stok_qty');
            $table->decimal('hpp', 15, 2)->default(0)->after('terjual_qty');
        });

        // Generated columns are MySQL-only; sqlite (tests) skips the recompute.
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE daily_product_trackings DROP COLUMN profit');
            DB::statement('ALTER TABLE daily_product_trackings ADD COLUMN profit DECIMAL(15,2) GENERATED ALWAYS AS (penjualan_nominal - hpp) STORED AFTER hpp');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE daily_product_trackings DROP COLUMN profit');
        }

        Schema::table('daily_product_trackings', function (Blueprint $table) {
            $table->dropColumn(['modal_kemarin_qty', 'harga_per_unit', 'terjual_qty', 'hpp']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE daily_product_trackings ADD COLUMN profit DECIMAL(15,2) GENERATED ALWAYS AS (penjualan_nominal - stok_masuk_total) STORED');
        }
    }
};