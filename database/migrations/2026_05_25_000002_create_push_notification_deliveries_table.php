<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_notification_deliveries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('push_notification_id')
                ->nullable()
                ->constrained('push_notifications')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('push_token_id')
                ->nullable()
                ->constrained('push_tokens')
                ->nullOnDelete();
            $table->string('platform')->nullable();
            $table->string('token_hash')->nullable();
            $table->string('status');
            $table->string('fcm_status')->nullable();
            $table->string('fcm_error_code')->nullable();
            $table->string('fcm_message_id')->nullable();
            $table->text('error_message')->nullable();
            $table->json('response')->nullable();
            $table->timestamp('attempted_at')->useCurrent();
            $table->timestamps();

            $table->index(['status', 'attempted_at']);
            $table->index(['user_id', 'attempted_at']);
            $table->index(['push_notification_id', 'status']);
            $table->index(['platform', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_notification_deliveries');
    }
};
