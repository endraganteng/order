<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_product_enrichment_batches', function (Blueprint $table) {
            $table->string('log_file', 500)->nullable()->after('summary');
            $table->text('spawn_error')->nullable()->after('log_file');
            $table->string('artisan_command', 1000)->nullable()->after('spawn_error');
        });
    }

    public function down(): void
    {
        Schema::table('ai_product_enrichment_batches', function (Blueprint $table) {
            $table->dropColumn(['log_file', 'spawn_error', 'artisan_command']);
        });
    }
};
