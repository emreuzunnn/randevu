<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Kullanıcı profil genişletme
        Schema::table('users', function (Blueprint $table) {
            $table->text('bio')->nullable()->after('phone');
            $table->json('portfolio')->nullable()->after('bio');
        });

        // Randevuya artist atama
        Schema::table('appointments', function (Blueprint $table) {
            $table->foreignId('assigned_artist_user_id')
                ->nullable()
                ->after('assigned_driver_user_id')
                ->constrained('users')
                ->nullOnDelete();
            // null = artist atanmadı / bekleniyor
            // pending  = artist'e bildirim gönderildi, yanıt bekleniyor
            // accepted = artist kabul etti
            // rejected = artist reddetti
            $table->string('artist_status')->nullable()->after('driver_status');
        });
    }

    public function down(): void
    {
        Schema::table('appointments', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assigned_artist_user_id');
            $table->dropColumn('artist_status');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['bio', 'portfolio']);
        });
    }
};
