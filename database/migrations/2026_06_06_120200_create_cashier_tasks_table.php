<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cashier_tasks', function (Blueprint $table) {
            $table->id();

            $table->string('firebase_legacy_key', 150)->nullable()->unique();
            $table->string('deterministic_key', 150)->unique();

            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('title', 300);
            $table->text('description')->nullable();

            $table->string('assigned_cashier_id', 100)->nullable();
            $table->date('scheduled_date');
            $table->time('scheduled_time')->nullable();

            $table->enum('status', ['pending', 'in_progress', 'done', 'cancelled', 'failed'])->default('pending');

            $table->boolean('is_recurring')->default(false);
            $table->string('recurrence_pattern', 100)->nullable();
            $table->json('metadata')->nullable();

            $table->timestamp('completed_at')->nullable();
            $table->text('notes')->nullable();

            $table->timestamps();

            $table->index(['assigned_cashier_id', 'scheduled_date'], 'idx_cashier_date');
            $table->index(['scheduled_date', 'status'], 'idx_cashier_date_status');
            $table->index(['template_id', 'scheduled_date'], 'idx_template_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cashier_tasks');
    }
};
