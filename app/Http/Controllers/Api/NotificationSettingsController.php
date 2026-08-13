<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationSettingsController extends Controller
{
    public const REMINDER_MINUTES_KEY = 'appointment_reminder_minutes';

    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->payload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'appointment_reminder_minutes' => ['required', 'integer', 'min:1', 'max:1440'],
        ]);

        AppSetting::setValue(
            self::REMINDER_MINUTES_KEY,
            (int) $validated['appointment_reminder_minutes'],
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Randevu hatırlatma süresi güncellendi.',
            'data' => $this->payload(),
        ]);
    }

    private function payload(): array
    {
        return [
            'appointment_reminder_minutes' => (int) AppSetting::valueFor(self::REMINDER_MINUTES_KEY, 15),
        ];
    }
}
