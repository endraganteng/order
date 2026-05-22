<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dana_payment_notifications', function (Blueprint $table) {
            $table->id();
            $table->string('payhook_reference', 100)->unique();   // dedup key dari PayHook
            $table->unsignedBigInteger('amount');                  // rupiah, integer
            $table->string('source', 50)->index();                 // 'DANA', 'Superbank', dll
            $table->string('package_name', 100)->nullable();       // 'id.dana', 'id.co.bankfama.android', dll
            $table->string('notification_title', 255)->nullable();
            $table->text('notification_text')->nullable();
            $table->string('sender_name', 200)->nullable();        // diparse dari notification_text
            $table->timestamp('notified_at')->nullable()->index(); // timestamp dari payload (waktu HP)
            $table->timestamp('received_at')->index();             // waktu webhook diterima server
            $table->json('raw_payload')->nullable();               // full payload untuk audit
            $table->string('firebase_key', 64)->nullable();        // key di firebase /dana_payments
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dana_payment_notifications');
    }
};
