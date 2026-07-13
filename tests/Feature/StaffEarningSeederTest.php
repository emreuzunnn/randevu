<?php

namespace Tests\Feature;

use App\Models\StaffEarning;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StaffEarningSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeders_create_pending_and_paid_staff_earnings(): void
    {
        $this->seed();

        $this->assertTrue(StaffEarning::query()->where('status', 'pending')->exists());
        $this->assertTrue(StaffEarning::query()->where('status', 'paid')->exists());
        $this->assertDatabaseHas('push_notifications', [
            'type' => 'earning_paid',
        ]);
    }
}
