<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->unique();

            $table->string('name', 200);
            $table->string('clock_in_time', 10)->nullable();
            $table->string('clock_out_time', 10)->nullable();
            $table->integer('late_tolerance_minutes')->default(0);
            $table->boolean('is_active')->default(true);
            $table->string('retail_tag', 50)->nullable();

            $table->unsignedBigInteger('event_created_at')->nullable();
            $table->unsignedBigInteger('event_updated_at')->nullable();
            $table->timestamps();

            $table->index('retail_tag', 'idx_retail_tag');
            $table->index('is_active', 'idx_ws_active');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_shifts');
    }
};
