<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_product_feedbacks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('session_id')->nullable()->index();
            $table->unsignedBigInteger('message_id')->nullable()->index();
            $table->unsignedBigInteger('user_id')->nullable()->index();
            $table->string('user_type', 20)->nullable();           // 'admin' | 'waiter'
            $table->text('question')->nullable();
            $table->longText('answer')->nullable();
            $table->json('product_ids')->nullable();               // produk yang ditampilkan saat jawaban itu
            $table->string('rating', 20)->nullable();              // 'helpful' | 'not_helpful'
            $table->string('reason', 255)->nullable();             // optional kategorisasi alasan
            $table->text('note')->nullable();                      // free-form catatan reviewer
            $table->timestamps();

            $table->index(['rating', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_product_feedbacks');
    }
};
