<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_penalties', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            $table->string('waiter_id', 100);
            $table->string('waiter_name', 200)->nullable();

            $table->string('penalty_type', 100)->nullable();
            $table->string('penalty_label', 200)->nullable();
            $table->integer('points_deducted')->default(0);

            $table->date('date');
            $table->string('month', 7)->nullable();          // Y-m
            $table->string('reason', 500)->nullable();
            $table->string('evidence_photo_url', 500)->nullable();
            $table->string('related_task_id', 150)->nullable();

            $table->unsignedBigInteger('event_created_at')->nullable();
            $table->timestamps();

            $table->index(['waiter_id', 'date'], 'idx_wp_waiter_date');
            $table->index('month', 'idx_wp_month');
            $table->index('date', 'idx_wp_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_penalties');
    }
};
