<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropForeign(['artist_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE reviews MODIFY artist_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('artist_id')->nullable()->change();
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('artist_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();

            $table->foreignId('studio_id')
                ->nullable()
                ->after('artist_id')
                ->constrained('studios')
                ->cascadeOnDelete();

            $table->unique(['user_id', 'artist_id'], 'reviews_user_artist_unique');
            $table->unique(['user_id', 'studio_id'], 'reviews_user_studio_unique');
            $table->index(['studio_id', 'created_at'], 'reviews_studio_created_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropUnique('reviews_user_artist_unique');
            $table->dropUnique('reviews_user_studio_unique');
            $table->dropIndex('reviews_studio_created_at_index');
            $table->dropForeign(['studio_id']);
            $table->dropColumn('studio_id');
            $table->dropForeign(['artist_id']);
        });

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE reviews MODIFY artist_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('artist_id')->nullable(false)->change();
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            $table->foreign('artist_id')
                ->references('id')
                ->on('users')
                ->cascadeOnDelete();
        });
    }
};
