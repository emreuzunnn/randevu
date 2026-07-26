<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('surname')->nullable();
            $table->string('email')->unique();
            $table->string('username')->nullable()->unique();
            $table->string('phone')->nullable();
            $table->text('bio')->nullable();
            $table->string('location')->nullable();
            $table->time('availability_start')->nullable();
            $table->time('availability_end')->nullable();
            $table->json('portfolio')->nullable();
            $table->string('profile_image')->nullable();
            $table->decimal('rating', 3, 1)->nullable();
            $table->unsignedTinyInteger('experience_years')->nullable();
            $table->json('specializations')->nullable();
            $table->string('instagram')->nullable();
            $table->string('whatsapp')->nullable();
            $table->unsignedTinyInteger('response_time_hours')->nullable();
            $table->string('role')->default('kullanici');
            $table->string('requested_staff_role')->nullable();
            $table->boolean('can_open_multiple_studios')->default(false);
            $table->string('api_token')->nullable()->unique();
            $table->timestamp('banned_at')->nullable();
            $table->text('ban_reason')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password');
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address')->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
