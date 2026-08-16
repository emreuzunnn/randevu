<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_message_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('appointment_id')
                ->nullable()
                ->constrained('appointments')
                ->nullOnDelete();
            $table->string('event_type', 80);
            $table->string('recipient_phone', 32);
            $table->string('template_name', 120);
            $table->string('language_code', 12)->default('tr');
            $table->string('status', 32);
            $table->string('whatsapp_message_id', 128)->nullable();
            $table->text('error_message')->nullable();
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'event_type']);
            $table->index(['recipient_phone', 'event_type']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
