<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                ->constrained('studios')
                ->cascadeOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('assigned_driver_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_country_code', 10)->nullable();
            $table->string('phone_number', 30)->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_number')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('customer_notes')->nullable();
            $table->unsignedSmallInteger('pax')->default(1);
            $table->timestamp('appointment_at');
            $table->string('status')->default('pending');
            $table->boolean('is_old_customer')->default(false);
            $table->text('notes')->nullable();
            $table->string('source_image_path')->nullable();
            $table->timestamps();

            $table->index(['studio_id', 'appointment_at']);
            $table->index(['studio_id', 'status']);
            $table->index(['studio_id', 'phone_country_code', 'phone_number']);
            $table->index(['studio_id', 'last_name']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
