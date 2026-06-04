<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('daily_product_trackings', function (Blueprint $table) {
            $table->dropUnique(['tracking_date', 'product_id']);
            $table->unique(['tracking_date', 'product_name']);
        });
    }

    public function down(): void
    {
        Schema::table('daily_product_trackings', function (Blueprint $table) {
            $table->dropUnique(['tracking_date', 'product_name']);
            $table->unique(['tracking_date', 'product_id']);
        });
    }
};
