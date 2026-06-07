<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('product_categories', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->unique();

            $table->string('name', 200);
            $table->string('description', 500)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_active')->default(true);

            $table->unsignedBigInteger('event_created_at')->nullable(); // created_at epoch Firebase
            $table->unsignedBigInteger('event_updated_at')->nullable();
            $table->timestamps();

            $table->index('is_active', 'idx_pc_active');
            $table->index('sort_order', 'idx_sort');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_categories');
    }
};
