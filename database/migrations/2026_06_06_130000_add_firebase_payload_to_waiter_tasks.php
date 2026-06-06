<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('waiter_tasks', function (Blueprint $table) {
            // Full Firebase task payload mirror. Portal reads ~14 detail fields
            // (requires_*, category_*, completed_* cluster, completions[]) that
            // don't warrant individual columns. Structured columns above stay
            // authoritative for querying; this JSON serves portal display.
            $table->json('firebase_payload')->nullable()->after('metadata');
        });
    }

    public function down(): void
    {
        Schema::table('waiter_tasks', function (Blueprint $table) {
            $table->dropColumn('firebase_payload');
        });
    }
};
