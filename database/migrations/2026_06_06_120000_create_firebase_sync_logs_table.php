<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('firebase_sync_logs', function (Blueprint $table) {
            $table->id();

            $table->string('entity_type', 100);                 // 'waiter_task', 'reconcile_run', dll
            $table->string('entity_id', 100);                   // mysql id atау identifier logis

            $table->string('firebase_path', 500);               // path RTDB yang ditulis
            $table->enum('action', ['set', 'update', 'remove', 'reconcile']);

            $table->enum('status', ['pending', 'success', 'failed'])->default('pending');

            $table->json('payload')->nullable();                // snapshot data yang disync
            $table->text('error_message')->nullable();

            $table->integer('attempt_count')->default(0);
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('next_retry_at')->nullable();

            $table->timestamps();

            $table->index(['entity_type', 'entity_id'], 'idx_sync_entity');
            $table->index(['status', 'next_retry_at'], 'idx_status_retry');
            $table->index('created_at', 'idx_created_at');
        });

        // firebase_path 500 char > index limit utf8mb4 -> pakai prefix index (191).
        // Prefix-length indexes are MySQL-only; sqlite (tests) indexes the full column.
        if (DB::getDriverName() === 'mysql') {
            DB::statement(
                'ALTER TABLE firebase_sync_logs ADD INDEX idx_firebase_path (firebase_path(191))'
            );
        } else {
            Schema::table('firebase_sync_logs', function (Blueprint $table) {
                $table->index('firebase_path', 'idx_firebase_path');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('firebase_sync_logs');
    }
};
