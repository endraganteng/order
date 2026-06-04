<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('daily_product_trackings', function (Blueprint $table) {
            $table->id();
            $table->date('tracking_date');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->decimal('stok_masuk_qty', 12, 2)->default(0);
            $table->decimal('stok_masuk_total', 15, 2)->default(0);
            $table->decimal('sisa_stok_qty', 12, 2)->default(0);
            $table->decimal('penjualan_nominal', 15, 2)->default(0);
            $table->decimal('profit', 15, 2)->storedAs('penjualan_nominal - stok_masuk_total');
            $table->timestamps();
            $table->unique(['tracking_date', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_product_trackings');
    }
};
