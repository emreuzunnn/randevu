<?php

namespace Tests\Unit;

use App\Models\Appointment;
use App\Models\AppointmentRequest;
use Carbon\Carbon;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AppointmentTimezoneTest extends TestCase
{
    #[DataProvider('dates')]
    public function test_dates_keep_their_business_time_after_storage(string $input, string $expected): void
    {
        config(['app.timezone' => 'Europe/Istanbul']);
        date_default_timezone_set('Europe/Istanbul');

        foreach ([Appointment::class => 'appointment_at', AppointmentRequest::class => 'requested_at'] as $class => $field) {
            $model = new $class([$field => $input]);
            $this->assertSame($expected, $model->getAttributes()[$field]);
            $hydrated = $model->newFromBuilder($model->getAttributes());
            $this->assertSame($expected, $hydrated->$field->format('Y-m-d H:i:s'));
            $this->assertSame('+03:00', $hydrated->$field->format('P'));
        }
    }

    public static function dates(): array
    {
        return [
            'web UTC input' => ['2026-09-24T14:00:00.000Z', '2026-09-24 17:00:00'],
            'explicit local offset' => ['2026-09-24T17:00:00+03:00', '2026-09-24 17:00:00'],
            'legacy mobile local input' => ['2026-09-24T17:00:00.000', '2026-09-24 17:00:00'],
            'SQL local input' => ['2026-09-24 17:00:00', '2026-09-24 17:00:00'],
            'midnight rollover' => ['2026-09-24T22:30:00Z', '2026-09-25 01:30:00'],
            'negative offset' => ['2026-09-24T10:00:00-04:00', '2026-09-24 17:00:00'],
        ];
    }

    public function test_input_carbon_is_not_mutated_and_null_is_preserved(): void
    {
        config(['app.timezone' => 'Europe/Istanbul']);
        $input = Carbon::parse('2026-09-24T14:00:00Z');
        $appointment = new Appointment(['appointment_at' => $input]);
        $this->assertSame('2026-09-24 17:00:00', $appointment->getAttributes()['appointment_at']);
        $this->assertSame('14:00 +00:00', $input->format('H:i P'));
        $appointment->appointment_at = null;
        $this->assertNull($appointment->appointment_at);
    }
}
