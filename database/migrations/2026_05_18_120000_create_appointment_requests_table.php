<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('requester_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('target_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('studio_id')->nullable()->constrained('studios')->nullOnDelete();
            $table->foreignId('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->string('request_type')->default('designer');
            $table->timestamp('requested_at');
            $table->string('image_path')->nullable();
            $table->json('tattoo_image_paths')->nullable();
            $table->boolean('pickup_required')->default(false);
            $table->text('notes')->nullable();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_number')->nullable();
            $table->string('place')->nullable();
            $table->unsignedSmallInteger('pax')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->json('ticket_types')->nullable();
            $table->string('tattoo_type')->nullable();
            $table->string('status')->default('pending');
            $table->text('response_notes')->nullable();
            $table->timestamp('responded_at')->nullable();
            $table->timestamps();

            $table->index(['target_user_id', 'status']);
            $table->index(['requester_user_id', 'status']);
            $table->index(['studio_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_requests');
    }
};
