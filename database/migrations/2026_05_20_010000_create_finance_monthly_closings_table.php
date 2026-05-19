<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('finance_monthly_closings', function (Blueprint $table) {
            $table->id();
            $table->string('month', 7)->unique(); // 2026-05
            $table->enum('status', ['open', 'closed', 'reopened'])->default('open');
            $table->json('snapshot')->nullable();
            $table->string('closed_by')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->string('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_monthly_closings');
    }
};
