<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rack_products', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();
            $table->string('name', 300);
            $table->string('category_id', 150)->nullable();
            $table->integer('standard_qty')->default(0);
            $table->string('unit', 50)->default('pcs');
            $table->boolean('is_active')->default(true);
            // Verbatim RTDB payload so MySQL reads return the exact field shape
            // the portal expects.
            $table->json('firebase_payload')->nullable();
            $table->unsignedInteger('event_created_at')->nullable();
            $table->unsignedInteger('event_updated_at')->nullable();
            $table->timestamps();

            $table->index('is_active');
            $table->index('category_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rack_products');
    }
};
