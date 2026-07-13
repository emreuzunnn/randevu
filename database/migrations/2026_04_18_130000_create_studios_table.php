<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('company_id')
                ->nullable()
                ->constrained('companies')
                ->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('location')->nullable();
            $table->string('logo_path')->nullable();
            $table->text('about')->nullable();
            $table->time('opening_time')->nullable();
            $table->time('closing_time')->nullable();
            $table->json('gallery_images')->nullable();
            $table->timestamps();
        });

        Schema::create('studio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                ->constrained('studios')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role');
            $table->string('work_status')->default('working');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['studio_id', 'user_id']);
            $table->index(['studio_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('studio_user');
        Schema::dropIfExists('studios');
    }
};
