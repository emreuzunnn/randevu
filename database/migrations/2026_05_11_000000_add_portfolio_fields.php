<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->text('about')->nullable()->after('email');
            $table->string('website', 255)->nullable()->after('about');
            $table->json('gallery_images')->nullable()->after('website');
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->string('logo_path', 2048)->nullable()->after('is_active');
            $table->text('about')->nullable()->after('logo_path');
            $table->time('opening_time')->nullable()->after('about');
            $table->time('closing_time')->nullable()->after('opening_time');
            $table->json('gallery_images')->nullable()->after('closing_time');
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->text('about')->nullable()->after('logo_path');
            $table->time('opening_time')->nullable()->after('about');
            $table->time('closing_time')->nullable()->after('opening_time');
            $table->json('gallery_images')->nullable()->after('closing_time');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['about', 'website', 'gallery_images']);
        });

        Schema::table('shops', function (Blueprint $table) {
            $table->dropColumn(['logo_path', 'about', 'opening_time', 'closing_time', 'gallery_images']);
        });

        Schema::table('studios', function (Blueprint $table) {
            $table->dropColumn(['about', 'opening_time', 'closing_time', 'gallery_images']);
        });
    }
};
