<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_manual_bonuses', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            $table->string('waiter_id', 100);
            $table->string('waiter_name', 200)->nullable();

            $table->string('month', 7)->nullable();          // Y-m
            $table->date('date');
            $table->integer('points')->default(0);           // bisa negatif (deduction)
            $table->string('reason', 500)->nullable();
            $table->string('category', 50)->nullable();      // manual_bonus | manual_deduction
            $table->string('created_by', 100)->nullable();

            $table->unsignedBigInteger('event_created_at')->nullable();
            $table->timestamps();

            $table->index(['waiter_id', 'date'], 'idx_wmb_waiter_date');
            $table->index('month', 'idx_wmb_month');
            $table->index('date', 'idx_wmb_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_manual_bonuses');
    }
};
