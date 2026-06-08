<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_orders', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->index();
            $table->string('po_number', 50)->nullable()->index();
            $table->string('supplier_name', 200)->nullable();
            $table->string('supplier_id', 60)->nullable();
            $table->string('supplier_phone', 30)->nullable();
            $table->string('status', 30)->default('open')->index(); // open, partial, completed, cancelled
            $table->text('notes')->nullable();
            $table->string('created_by', 100)->nullable();
            $table->string('created_by_name', 100)->nullable();
            $table->integer('items_count')->default(0);
            $table->integer('received_count')->default(0);
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();
        });

        Schema::create('purchase_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_order_id')->constrained()->onDelete('cascade');
            $table->string('restock_id', 100)->nullable();
            $table->string('product_id', 60)->nullable()->index();
            $table->string('product_name', 200)->nullable();
            $table->string('product_category_id', 60)->nullable();
            $table->string('rack_id', 60)->nullable();
            $table->string('rack_name', 100)->nullable();
            $table->integer('qty_needed')->default(0);
            $table->integer('qty_ordered')->default(0);
            $table->integer('qty_received')->default(0);
            $table->string('status', 30)->default('pending'); // pending, received, accepted_as_is, cancelled
            $table->text('note')->nullable();
            $table->string('received_by', 100)->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamps();
        });

        Schema::create('restock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->index();
            $table->string('product_id', 60)->nullable()->index();
            $table->string('product_name', 200)->nullable();
            $table->string('product_category_id', 60)->nullable();
            $table->string('rack_id', 60)->nullable();
            $table->string('rack_name', 100)->nullable();
            $table->integer('reported_qty')->default(0);
            $table->integer('standard_qty')->default(0);
            $table->integer('qty_needed')->default(0);
            $table->string('status', 30)->default('pending')->index(); // pending, in_po, fulfilled, cancelled
            $table->string('source', 30)->default('rack_check'); // rack_check, manual
            $table->string('reported_by', 100)->nullable();
            $table->string('reported_by_name', 100)->nullable();
            $table->date('date')->nullable()->index();
            $table->text('note')->nullable();
            $table->string('po_id', 100)->nullable();
            $table->timestamps();
        });

        Schema::create('reconciliation_reports', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->index();
            $table->string('iso_year_week', 10)->index(); // e.g. 2026_W23
            $table->string('status', 30)->default('completed');
            $table->integer('total_products')->default(0);
            $table->integer('anomaly_count')->default(0);
            $table->decimal('drift_avg_pct', 8, 2)->default(0);
            $table->json('anomalies')->nullable();
            $table->json('summary')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_order_items');
        Schema::dropIfExists('purchase_orders');
        Schema::dropIfExists('restock_requests');
        Schema::dropIfExists('reconciliation_reports');
    }
};
