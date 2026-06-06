<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_tasks', function (Blueprint $table) {
            $table->id();

            $table->string('firebase_legacy_key', 150)->nullable()->unique();
            $table->string('firebase_active_path', 500)->nullable();
            $table->string('deterministic_key', 150)->unique();

            $table->unsignedBigInteger('template_id')->nullable();
            $table->string('template_legacy_key', 150)->nullable();

            $table->enum('task_type', ['general', 'rack_check'])->default('general');
            $table->string('title', 300);
            $table->text('description')->nullable();

            $table->string('assigned_waiter_id', 100);
            $table->string('assigned_waiter_name', 200)->nullable();

            $table->date('scheduled_for_date');
            $table->time('scheduled_time')->nullable();

            // status pekerjaan; publish_status = status publish ke Firebase;
            // sync_status = status sinkronisasi (revisi 3 plan)
            $table->enum('status', ['pending', 'in_progress', 'done', 'cancelled', 'rescheduled', 'ignored', 'failed'])->default('pending');
            $table->enum('publish_status', ['draft', 'published', 'failed'])->default('draft');
            $table->enum('sync_status', ['pending', 'synced', 'failed'])->default('pending');

            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            $table->string('rack_id', 100)->nullable();
            $table->string('rack_code', 100)->nullable();
            $table->string('rack_name', 200)->nullable();

            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('completed_by', 100)->nullable();

            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancelled_by', 100)->nullable();
            $table->string('cancel_reason', 500)->nullable();

            $table->timestamp('ignored_at')->nullable();
            $table->string('ignored_by', 100)->nullable();
            $table->string('ignore_reason', 500)->nullable();

            $table->string('photo_url', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();

            $table->text('sync_error')->nullable();
            $table->timestamp('synced_at')->nullable();

            $table->string('created_by', 100)->nullable();
            $table->timestamps();

            $table->index(['assigned_waiter_id', 'scheduled_for_date'], 'idx_waiter_date');
            $table->index(['scheduled_for_date', 'status'], 'idx_date_status');
            $table->index('sync_status', 'idx_sync_status');
            $table->index('publish_status', 'idx_publish_status');
            $table->index(['task_type', 'scheduled_for_date'], 'idx_task_type_date');
            $table->index(['rack_id', 'scheduled_for_date'], 'idx_rack_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_tasks');
    }
};
