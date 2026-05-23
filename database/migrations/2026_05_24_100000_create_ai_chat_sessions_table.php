<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_chat_sessions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type', 20)->index();              // 'admin' | 'waiter'
            $table->string('title', 255)->nullable();
            $table->json('last_product_ids')->nullable();          // array of Firebase product IDs (top 5 dari last answer)
            $table->string('primary_product_id', 64)->nullable();  // produk teratas dari last answer (untuk follow-up)
            $table->timestamps();

            $table->index(['user_id', 'user_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_chat_sessions');
    }
};
