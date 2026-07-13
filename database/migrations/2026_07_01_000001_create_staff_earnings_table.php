<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('studio_user', function (Blueprint $table): void {
            $table->decimal('commission_rate', 5, 2)
                ->default(0)
                ->after('work_status');
        });

        Schema::create('staff_earnings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('appointment_id')
                ->unique()
                ->constrained('appointments')
                ->cascadeOnDelete();
            $table->foreignId('studio_id')
                ->constrained('studios')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->string('role');
            $table->decimal('commission_rate', 5, 2);
            $table->decimal('gross_amount', 12, 2);
            $table->decimal('earning_amount', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('paid_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['studio_id', 'status']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_earnings');

        Schema::table('studio_user', function (Blueprint $table): void {
            $table->dropColumn('commission_rate');
        });
    }
};
