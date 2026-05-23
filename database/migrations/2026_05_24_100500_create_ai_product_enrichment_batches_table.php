<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_enrichment_batches', function (Blueprint $table) {
            $table->id();
            $table->string('mode', 30);                // enrichment | vector_sync | full_pipeline
            $table->string('status', 20)->index();     // queued | running | completed | failed | cancelled
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('processed_items')->default(0);
            $table->unsignedInteger('success_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('skipped_count')->default(0);
            $table->boolean('auto_approve')->default(false);
            $table->boolean('auto_sync')->default(false);
            $table->string('current_product_id', 64)->nullable();
            $table->string('current_product_name', 255)->nullable();
            $table->text('last_message')->nullable();
            $table->json('options')->nullable();        // raw options dipass dari UI
            $table->json('summary')->nullable();        // ringkasan akhir
            $table->string('initiated_by', 100)->nullable();
            $table->unsignedBigInteger('pid')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamp('heartbeat_at')->nullable()->index();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_enrichment_batches');
    }
};
