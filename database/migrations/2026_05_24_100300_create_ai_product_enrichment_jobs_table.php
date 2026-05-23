<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_enrichment_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 64)->index();             // Firebase rack_products key
            $table->string('product_name', 255)->nullable();       // snapshot saat job dibuat
            $table->string('base_name', 191)->nullable()->index(); // hasil split delimiter ' - ' (191 utk utf8mb4 compound index limit)
            $table->string('variant_label', 100)->nullable();      // suffix variant (misal '0,20mm', 'No. 5', 'Tuna')
            $table->string('inherited_from_product_id', 64)->nullable()->index();
            $table->boolean('is_inherited')->default(false)->index();
            $table->string('status', 30)->index();                 // pending|processing|draft|approved|rejected|needs_review|failed
            $table->unsignedInteger('source_count')->default(0);   // jumlah grounding chunks dari Gemini
            $table->decimal('confidence_score', 4, 3)->nullable(); // 0.000 - 1.000
            $table->string('generated_by', 100)->nullable();       // identifier (email admin / 'cli' / uid)
            $table->string('approved_by', 100)->nullable();        // identifier (email admin / uid)
            $table->timestamp('approved_at')->nullable();
            $table->text('error_message')->nullable();
            $table->json('metadata')->nullable();                  // search queries, latency, model versi, dll
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['base_name', 'is_inherited']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_enrichment_jobs');
    }
};
