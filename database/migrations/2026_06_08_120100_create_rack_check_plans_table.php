<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_check_plans', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_legacy_key', 150)->nullable();

            // Planning period
            $table->date('plan_date');
            $table->string('plan_period', 20)->default('daily'); // daily, weekly

            // Waiter assignment
            $table->string('waiter_id', 100);
            $table->string('waiter_name', 200)->nullable();

            // Rack assignment
            $table->string('rack_id', 100);
            $table->string('rack_name', 200)->nullable();
            $table->string('rack_location', 300)->nullable();

            // Status
            $table->enum('status', ['planned', 'generated', 'skipped', 'cancelled'])->default('planned');
            $table->string('skip_reason', 300)->nullable();

            // Metadata
            $table->string('assigned_by', 100)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['plan_date', 'waiter_id']);
            $table->index(['plan_date', 'rack_id']);
            $table->index(['template_id', 'plan_date']);
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_check_plans');
    }
};
