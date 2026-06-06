<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_bonus_summaries', function (Blueprint $table) {
            $table->id();

            $table->string('waiter_id', 100);
            $table->string('period_key', 100);          // startDate_endDate

            $table->string('status', 30)->default('draft'); // draft | finalized
            $table->unsignedBigInteger('finalized_at')->nullable();

            $table->json('summary')->nullable();         // full calculateBonus result

            $table->timestamps();

            $table->unique(['waiter_id', 'period_key'], 'uniq_waiter_period');
            $table->index('period_key', 'idx_period');
            $table->index('status', 'idx_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_bonus_summaries');
    }
};
