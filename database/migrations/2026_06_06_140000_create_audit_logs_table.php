<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->string('firebase_legacy_key', 150)->nullable()->unique();

            $table->string('action', 100);
            $table->string('entity', 100);
            $table->string('entity_id', 100)->nullable();

            $table->string('admin_id', 100)->nullable();
            $table->string('admin_name', 200)->nullable();

            $table->json('details')->nullable();
            $table->string('ip', 64)->nullable();

            $table->unsignedBigInteger('event_timestamp');     // detik epoch dari Firebase
            $table->date('event_date');                        // Y-m-d untuk filter cepat

            $table->timestamps();

            $table->index('event_date', 'idx_event_date');
            $table->index(['entity', 'entity_id'], 'idx_audit_entity');
            $table->index('admin_id', 'idx_admin');
            $table->index('event_timestamp', 'idx_event_ts');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_logs');
    }
};
