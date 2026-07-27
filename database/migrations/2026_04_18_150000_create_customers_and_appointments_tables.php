<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                ->nullable()
                ->constrained('studios')
                ->nullOnDelete();
            $table->string('first_name')->nullable();
            $table->string('last_name')->nullable();
            $table->string('phone_country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_number')->nullable();
            $table->string('place')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('customer_notes')->nullable();
            $table->timestamp('first_appointment_at')->nullable();
            $table->timestamp('last_appointment_at')->nullable();
            $table->unsignedInteger('appointments_count')->default(0);
            $table->timestamps();

            $table->index('phone_number');
            $table->index('last_name');
        });

        Schema::create('appointments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                ->nullable()
                ->constrained('studios')
                ->nullOnDelete();
            $table->foreignId('customer_id')
                ->nullable()
                ->constrained('customers')
                ->nullOnDelete();
            $table->foreignId('created_by_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('assigned_info_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_driver_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('assigned_artist_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('appointment_type')->default('designer');
            $table->string('first_name');
            $table->string('last_name');
            $table->string('phone_country_code')->nullable();
            $table->string('phone_number')->nullable();
            $table->string('hotel_name')->nullable();
            $table->string('room_number')->nullable();
            $table->string('place')->nullable();
            $table->string('photo_path')->nullable();
            $table->text('customer_notes')->nullable();
            $table->unsignedSmallInteger('pax')->default(1);
            $table->decimal('price', 10, 2)->nullable();
            $table->decimal('deposit_amount', 10, 2)->nullable();
            $table->string('payment_method')->nullable();
            $table->json('ticket_types')->nullable();
            $table->string('tattoo_type')->nullable();
            $table->timestamp('appointment_at');
            $table->string('status')->default('confirmed');
            $table->string('driver_status')->nullable();
            $table->string('artist_status')->nullable();
            $table->boolean('is_old_customer')->default(false);
            $table->text('notes')->nullable();
            $table->string('source_image_path')->nullable();
            $table->json('tattoo_image_paths')->nullable();
            $table->string('completed_tattoo_image_path')->nullable();
            $table->boolean('pickup_required')->default(false);
            $table->string('public_token')->nullable()->unique();
            $table->timestamps();

            $table->index(['studio_id', 'appointment_at']);
            $table->index(['studio_id', 'status']);
            $table->index('phone_number');
            $table->index(['studio_id', 'last_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
        Schema::dropIfExists('customers');
    }
};
