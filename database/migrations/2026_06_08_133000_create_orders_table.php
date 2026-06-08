<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->index();
            $table->integer('queue_number')->default(0)->index();
            $table->string('waiter_id', 100)->nullable()->index();
            $table->string('waiter_name', 100)->nullable();
            $table->string('waiter_email', 150)->nullable();
            $table->json('products')->nullable();
            $table->integer('product_count')->default(0);
            $table->integer('total_price')->default(0);
            $table->string('status', 30)->default('active')->index(); // active, expired, completed, cancelled
            $table->timestamp('expires_at')->nullable();
            $table->date('order_date')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
