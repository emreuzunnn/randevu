<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('can_open_multiple_studios')
                ->default(false)
                ->after('role');
        });

        Schema::create('studios', function (Blueprint $table) {
            $table->id();
            $table->foreignId('owner_user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('logo_path')->nullable();
            $table->unsignedSmallInteger('notification_lead_minutes')->default(30);
            $table->timestamps();
        });

        Schema::create('studio_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('studio_id')
                ->constrained('studios')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role');
            $table->boolean('is_active')->default(true);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['studio_id', 'user_id']);
            $table->index(['studio_id', 'role']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('studio_user');
        Schema::dropIfExists('studios');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('can_open_multiple_studios');
        });
    }
};
