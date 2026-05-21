<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->dropForeignIfExists('appointments', 'studio_id');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY studio_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreignId('studio_id')->nullable()->change();
            });
        }

        if (! $this->foreignKeyExists('appointments', 'studio_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreign('studio_id')
                    ->references('id')
                    ->on('studios')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        $this->dropForeignIfExists('appointments', 'studio_id');

        if (DB::getDriverName() === 'mysql') {
            DB::statement('ALTER TABLE appointments MODIFY studio_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreignId('studio_id')->nullable(false)->change();
            });
        }

        if (! $this->foreignKeyExists('appointments', 'studio_id')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->foreign('studio_id')
                    ->references('id')
                    ->on('studios')
                    ->cascadeOnDelete();
            });
        }
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
};
