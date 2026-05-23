<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_enrichment_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_id')
                ->nullable()
                ->constrained('ai_product_enrichment_jobs')
                ->nullOnDelete();
            $table->string('product_id', 64)->index();
            $table->string('action', 50);                          // 'started'|'gemini_called'|'inherited'|'saved'|'approved'|'rejected'|'synced'|'failed'
            $table->text('message')->nullable();
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamps();

            $table->index(['product_id', 'created_at']);
            $table->index(['action', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_enrichment_logs');
    }
};
