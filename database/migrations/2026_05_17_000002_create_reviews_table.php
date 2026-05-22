<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')      // reviewer (müşteri)
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('artist_id')    // değerlendirilen artist
                ->nullable()
                ->constrained('users')
                ->cascadeOnDelete();
            $table->foreignId('studio_id')
                ->nullable()
                ->constrained('studios')
                ->cascadeOnDelete();
            $table->unsignedTinyInteger('rating'); // 1-5
            $table->text('comment')->nullable();
            $table->string('image_path')->nullable();
            $table->timestamps();

            $table->index(['artist_id', 'created_at']);
            $table->index(['studio_id', 'created_at'], 'reviews_studio_created_at_index');
            $table->unique(['user_id', 'artist_id'], 'reviews_user_artist_unique');
            $table->unique(['user_id', 'studio_id'], 'reviews_user_studio_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
