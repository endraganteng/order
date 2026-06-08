<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_task_templates', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            $table->string('title', 300);
            $table->string('name', 300)->nullable();
            $table->text('description')->nullable();
            $table->enum('task_type', ['general', 'rack_check'])->default('general');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Assignment
            $table->string('assignment_type', 50)->default('role'); // all, single, role
            $table->string('assignment_strategy', 50)->default('manual_planning');
            $table->string('assigned_waiter_id', 100)->nullable();
            $table->string('assigned_waiter_name', 200)->nullable();
            $table->string('assigned_waiter_role', 100)->nullable();
            $table->json('selected_waiter_ids')->nullable();
            $table->boolean('simple_lowest_load_enabled')->default(false);
            $table->boolean('rolling_enabled')->default(false);
            $table->json('rolling_waiter_ids')->nullable();
            $table->string('rolling_period', 20)->nullable();
            $table->string('rolling_anchor_date', 20)->nullable();

            // Schedule
            $table->string('schedule_mode', 30)->default('shift_relative');
            $table->string('schedule_time', 10)->default('08:00');
            $table->unsignedInteger('time_limit_minutes')->default(480);
            $table->string('deadline_mode', 50)->nullable();
            $table->unsignedInteger('deadline_before_end_minutes')->default(0);
            $table->unsignedInteger('shift_offset_minutes')->default(0);
            $table->string('target_shift_id', 100)->nullable();

            // Recurrence
            $table->string('recurrence_type', 20)->default('daily'); // daily, weekly, every_n_days
            $table->unsignedTinyInteger('weekly_day')->nullable(); // 1-7
            $table->unsignedInteger('interval_days')->nullable();
            $table->string('recurrence_anchor_date', 20)->nullable();

            // Rack (primary/backward-compat)
            $table->string('rack_id', 100)->nullable();
            $table->string('rack_name', 200)->nullable();
            $table->string('rack_location', 300)->nullable();
            $table->string('rack_barcode_value', 200)->nullable();
            $table->string('rack_type', 50)->default('storage');
            $table->string('rack_target_scope', 20)->nullable(); // single, multi, all

            // Multi-rak (JSON array of rack objects)
            $table->json('racks')->nullable();

            // Flags
            $table->boolean('is_active')->default(true);
            $table->boolean('requires_barcode_scan')->default(true);
            $table->boolean('requires_photo_before')->default(true);
            $table->boolean('requires_photo_proof')->default(true);
            $table->boolean('allow_note')->default(true);
            $table->boolean('enable_empty_product_report')->default(true);
            $table->boolean('skip_when_no_eligible_waiter')->default(true);
            $table->string('daily_cap_mode', 30)->default('shift_aware');
            $table->unsignedInteger('full_shift_daily_cap')->nullable();
            $table->unsignedInteger('partial_shift_daily_cap')->nullable();

            // Category
            $table->string('category_id', 100)->nullable();
            $table->string('category_name', 200)->nullable();

            $table->string('assigned_by', 100)->nullable();
            $table->timestamps();

            $table->index('task_type');
            $table->index('is_active');
            $table->index(['task_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_task_templates');
    }
};
