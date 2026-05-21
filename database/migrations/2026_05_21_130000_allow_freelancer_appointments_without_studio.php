<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['studio_id']);
            $table->foreignId('studio_id')
                ->nullable()
                ->change();
            $table->foreign('studio_id')
                ->references('id')
                ->on('studios')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropForeign(['studio_id']);
            $table->foreignId('studio_id')
                ->nullable(false)
                ->change();
            $table->foreign('studio_id')
                ->references('id')
                ->on('studios')
                ->cascadeOnDelete();
        });
    }
};
