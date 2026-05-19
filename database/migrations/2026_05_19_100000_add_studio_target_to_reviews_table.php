<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('reviews', 'artist_id');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE reviews MODIFY artist_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('reviews', function (Blueprint $table) {
                $table->foreignId('artist_id')->nullable()->change();
            });
        }

        Schema::table('reviews', function (Blueprint $table) {
            if (! Schema::hasColumn('reviews', 'studio_id')) {
                $table->foreignId('studio_id')
                    ->nullable()
                    ->after('artist_id')
                    ->constrained('studios')
                    ->cascadeOnDelete();
            }
        });

        $this->deduplicateExistingArtistReviews();

        Schema::table('reviews', function (Blueprint $table) {
            if (! $this->foreignKeyExists('reviews', 'artist_id')) {
                $table->foreign('artist_id')
                    ->references('id')
                    ->on('users')
                    ->cascadeOnDelete();
            }

            if (! $this->indexExists('reviews', 'reviews_user_artist_unique')) {
                $table->unique(['user_id', 'artist_id'], 'reviews_user_artist_unique');
            }
            if (! $this->indexExists('reviews', 'reviews_user_studio_unique')) {
                $table->unique(['user_id', 'studio_id'], 'reviews_user_studio_unique');
            }
            if (! $this->indexExists('reviews', 'reviews_studio_created_at_index')) {
                $table->index(['studio_id', 'created_at'], 'reviews_studio_created_at_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if ($this->indexExists('reviews', 'reviews_user_artist_unique')) {
                $table->dropUnique('reviews_user_artist_unique');
            }
            if ($this->indexExists('reviews', 'reviews_user_studio_unique')) {
                $table->dropUnique('reviews_user_studio_unique');
            }
            if ($this->indexExists('reviews', 'reviews_studio_created_at_index')) {
                $table->dropIndex('reviews_studio_created_at_index');
            }
        });

        $this->dropForeignIfExists('reviews', 'studio_id');
        $this->dropForeignIfExists('reviews', 'artist_id');

        if (Schema::hasColumn('reviews', 'studio_id')) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropColumn('studio_id');
            });
        }

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

    private function dropForeignIfExists(string $table, string $column): void
    {
        $foreignKey = $this->foreignKeyName($table, $column);

        if ($foreignKey === null) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            $safeTable = str_replace('`', '``', $table);
            $safeKey = str_replace('`', '``', $foreignKey);
            DB::statement("ALTER TABLE `{$safeTable}` DROP FOREIGN KEY `{$safeKey}`");
            return;
        }

        Schema::table($table, function (Blueprint $blueprint) use ($foreignKey): void {
            $blueprint->dropForeign($foreignKey);
        });
    }

    private function foreignKeyExists(string $table, string $column): bool
    {
        return $this->foreignKeyName($table, $column) !== null;
    }

    private function foreignKeyName(string $table, string $column): ?string
    {
        if (DB::getDriverName() !== 'mysql') {
            return null;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT CONSTRAINT_NAME AS name
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
              AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
            SQL,
            [$table, $column]
        );

        return $row?->name;
    }

    private function indexExists(string $table, string $index): bool
    {
        if (DB::getDriverName() !== 'mysql') {
            return false;
        }

        $row = DB::selectOne(
            <<<'SQL'
            SELECT INDEX_NAME AS name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND INDEX_NAME = ?
            LIMIT 1
            SQL,
            [$table, $index]
        );

        return $row !== null;
    }

    private function deduplicateExistingArtistReviews(): void
    {
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement(
            <<<'SQL'
            DELETE old_reviews
            FROM reviews old_reviews
            INNER JOIN reviews new_reviews
              ON old_reviews.user_id = new_reviews.user_id
             AND old_reviews.artist_id = new_reviews.artist_id
             AND old_reviews.id < new_reviews.id
            WHERE old_reviews.artist_id IS NOT NULL
            SQL
        );
    }
};
