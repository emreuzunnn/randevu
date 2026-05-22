<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('appointments', 'price')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->decimal('price', 10, 2)->nullable()->after('pax');
            });
        }

        if (! Schema::hasColumn('appointment_requests', 'price')) {
            Schema::table('appointment_requests', function (Blueprint $table): void {
                $table->decimal('price', 10, 2)->nullable()->after('pax');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('appointment_requests', 'price')) {
            Schema::table('appointment_requests', function (Blueprint $table): void {
                $table->dropColumn('price');
            });
        }

        if (Schema::hasColumn('appointments', 'price')) {
            Schema::table('appointments', function (Blueprint $table): void {
                $table->dropColumn('price');
            });
        }
    }
};
