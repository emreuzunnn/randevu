<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('banned_at')->nullable()->after('api_token');
            $table->text('ban_reason')->nullable()->after('banned_at');
        });

        Schema::create('content_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reporter_user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('review_id')->nullable()->constrained('reviews')->cascadeOnDelete();
            $table->foreignId('reported_user_id')->nullable()->constrained('users')->cascadeOnDelete();
            $table->string('reason');
            $table->text('details')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->unique(['reporter_user_id', 'review_id']);
            $table->unique(['reporter_user_id', 'reported_user_id']);
            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_reports');
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['banned_at', 'ban_reason']);
        });
    }
};
