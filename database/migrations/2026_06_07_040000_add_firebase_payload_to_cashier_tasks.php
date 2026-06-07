<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cashier_tasks', function (Blueprint $table) {
            // Store the verbatim RTDB payload so MySQL reads return the exact
            // same field shape the cashier portal already expects.
            $table->json('firebase_payload')->nullable()->after('metadata');

            // RTDB source_template_id is a firebase push key (string), not the
            // integer template_id. Indexed for recurring-instance lookups.
            $table->string('source_template_key', 150)->nullable()->after('template_id');

            $table->index(['source_template_key', 'scheduled_date'], 'idx_source_template_date');
        });

        // RTDB uses status 'overdue' which the original enum lacks.
        DB::statement("ALTER TABLE cashier_tasks MODIFY COLUMN status ENUM('pending','in_progress','done','overdue','cancelled','failed') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        Schema::table('cashier_tasks', function (Blueprint $table) {
            $table->dropIndex('idx_source_template_date');
            $table->dropColumn(['firebase_payload', 'source_template_key']);
        });

        DB::statement("ALTER TABLE cashier_tasks MODIFY COLUMN status ENUM('pending','in_progress','done','cancelled','failed') NOT NULL DEFAULT 'pending'");
    }
};
