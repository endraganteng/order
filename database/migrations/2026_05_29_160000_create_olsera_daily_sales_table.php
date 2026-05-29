<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('olsera_daily_sales', function (Blueprint $table) {
            $table->id();
            $table->date('sale_date')->index();
            $table->string('type', 30)->index(); // 'product', 'category', 'summary'
            $table->string('name')->nullable(); // product/category name
            $table->unsignedBigInteger('product_id')->nullable();
            $table->string('group_name')->nullable(); // product group/category
            $table->decimal('total_qty', 12, 3)->default(0);
            $table->decimal('total_amount', 14, 2)->default(0);
            $table->decimal('total_profit', 14, 2)->default(0);
            $table->unsignedInteger('total_transactions')->default(0); // for summary type
            $table->json('payment_breakdown')->nullable(); // for summary type
            $table->json('meta')->nullable(); // extra data
            $table->timestamps();

            $table->unique(['sale_date', 'type', 'product_id', 'name'], 'olsera_daily_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('olsera_daily_sales');
    }
};
