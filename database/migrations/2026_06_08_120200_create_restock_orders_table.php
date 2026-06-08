<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restock_orders', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            // Source: dari task cek rak mana
            $table->unsignedBigInteger('source_task_id')->nullable();
            $table->string('source_task_legacy_key', 150)->nullable();

            // Rack info
            $table->string('rack_id', 100);
            $table->string('rack_name', 200)->nullable();

            // Product info
            $table->string('product_id', 100);
            $table->string('product_name', 200)->nullable();
            $table->string('unit', 30)->default('pcs');

            // Quantities
            $table->unsignedInteger('standard_qty')->default(0);
            $table->unsignedInteger('actual_qty')->default(0);
            $table->unsignedInteger('needed_qty')->default(0);
            $table->unsignedInteger('fulfilled_qty')->default(0);

            // Status
            $table->enum('status', ['pending', 'in_progress', 'done', 'cancelled', 'partial'])->default('pending');
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');

            // Assignment
            $table->string('assigned_to', 100)->nullable();
            $table->string('assigned_to_name', 200)->nullable();
            $table->string('fulfilled_by', 100)->nullable();
            $table->string('fulfilled_by_name', 200)->nullable();

            // Timestamps
            $table->timestamp('fulfilled_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();

            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['rack_id', 'status']);
            $table->index(['status', 'created_at']);
            $table->index('product_id');
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('restock_orders');
    }
};
