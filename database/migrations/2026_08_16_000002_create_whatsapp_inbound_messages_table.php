<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('whatsapp_inbound_messages', function (Blueprint $table): void {
            $table->id();
            $table->string('message_id')->unique();
            $table->string('from_phone')->nullable();
            $table->string('profile_name')->nullable();
            $table->string('message_type')->nullable();
            $table->text('message_body')->nullable();
            $table->json('payload')->nullable();
            $table->string('auto_reply_status')->nullable();
            $table->string('auto_reply_message_id')->nullable();
            $table->text('auto_reply_error')->nullable();
            $table->timestamp('received_at')->nullable();
            $table->timestamp('auto_replied_at')->nullable();
            $table->timestamps();

            $table->index(['from_phone', 'received_at']);
            $table->index(['message_type', 'received_at']);
            $table->index(['auto_reply_status', 'received_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('whatsapp_inbound_messages');
    }
};
