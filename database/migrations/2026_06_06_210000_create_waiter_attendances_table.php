<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_attendances', function (Blueprint $table) {
            $table->id();

            $table->string('waiter_id', 100);
            $table->date('date');

            $table->string('status', 30)->nullable();        // present | late | absent | ...
            $table->integer('late_minutes')->default(0);
            $table->string('clock_in', 20)->nullable();       // HH:MM
            $table->string('clock_out', 20)->nullable();

            $table->json('data')->nullable();                 // full attendance record mirror

            $table->timestamps();

            $table->unique(['waiter_id', 'date'], 'uniq_waiter_date');
            $table->index('date', 'idx_wa_date');
            $table->index('status', 'idx_wa_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_attendances');
    }
};
