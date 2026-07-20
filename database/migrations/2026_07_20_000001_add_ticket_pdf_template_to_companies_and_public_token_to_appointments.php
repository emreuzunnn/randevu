<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table): void {
            $table->json('ticket_pdf_template')->nullable()->after('gallery_images');
        });

        Schema::table('appointments', function (Blueprint $table): void {
            $table->string('public_token', 80)->nullable()->unique()->after('pickup_required');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table): void {
            $table->dropUnique(['public_token']);
            $table->dropColumn('public_token');
        });

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn('ticket_pdf_template');
        });
    }
};
