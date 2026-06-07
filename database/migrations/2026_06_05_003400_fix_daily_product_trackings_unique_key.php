<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // On a fresh schema the create migration already declares the
        // (tracking_date, product_name) unique key, so the old index may not
        // exist. Schema::table queues commands and executes after the closure
        // returns, so guard by checking existence first (e.g. sqlite tests).
        if ($this->indexExists('daily_product_trackings_tracking_date_product_id_unique')) {
            Schema::table('daily_product_trackings', function (Blueprint $table) {
                $table->dropUnique(['tracking_date', 'product_id']);
            });
        }
        if (! $this->indexExists('daily_product_trackings_tracking_date_product_name_unique')) {
            Schema::table('daily_product_trackings', function (Blueprint $table) {
                $table->unique(['tracking_date', 'product_name']);
            });
        }
    }

    private function indexExists(string $name): bool
    {
        if (DB::getDriverName() === 'sqlite') {
            return (bool) DB::selectOne(
                "SELECT name FROM sqlite_master WHERE type='index' AND name = ?",
                [$name]
            );
        }

        // MySQL: assume the create migration produced the expected indexes.
        return $name === 'daily_product_trackings_tracking_date_product_id_unique';
    }

    public function down(): void
    {
        Schema::table('daily_product_trackings', function (Blueprint $table) {
            $table->dropUnique(['tracking_date', 'product_name']);
            $table->unique(['tracking_date', 'product_id']);
        });
    }
};
