<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('waiter_activity_reports', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 100)->nullable()->unique();

            $table->string('waiter_id', 100);
            $table->string('waiter_name', 200)->nullable();
            $table->string('waiter_email', 200)->nullable();

            $table->date('report_date');
            $table->text('activity_text')->nullable();
            $table->json('activity_items')->nullable();

            $table->unsignedBigInteger('event_timestamp')->nullable(); // created_at epoch dari Firebase
            $table->timestamps();

            $table->index(['waiter_id', 'report_date'], 'idx_waiter_date');
            $table->index('report_date', 'idx_report_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('waiter_activity_reports');
    }
};
