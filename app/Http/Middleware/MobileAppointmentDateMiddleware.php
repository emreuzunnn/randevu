<?php

namespace App\Http\Middleware;

use App\Http\Controllers\Api\AppointmentController;
use App\Http\Controllers\Api\AppointmentRequestController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\StaffEarningController;
use Carbon\CarbonImmutable;
use Carbon\Exceptions\InvalidFormatException;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use stdClass;
use Symfony\Component\HttpFoundation\Response;

class MobileAppointmentDateMiddleware
{
    private const CONTROLLERS = [
        AppointmentController::class,
        AppointmentRequestController::class,
        DashboardController::class,
        ReportController::class,
        StaffEarningController::class,
    ];

    private const DATE_FIELDS = [
        'appointment_at',
        'requested_at',
        'first_appointment_at',
        'last_appointment_at',
        'last_ticket_at',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $response instanceof JsonResponse || ! $response->isSuccessful()
            || ! in_array($request->route()?->getControllerClass(), self::CONTROLLERS, true)) {
            return $response;
        }

        $response->setVary('User-Agent', false);

        // Released Flutter clients read offset-bearing dates as UTC wall time.
        // Native dart:io already sends this user agent, so no app update is needed.
        if (str_starts_with($request->userAgent() ?? '', 'Dart/')) {
            $data = $response->getData();
            if (is_array($data) || $data instanceof stdClass) {
                $response->setData($this->formatDates($data));
            }
        }

        return $response;
    }

    private function formatDates(array|stdClass $data): array|stdClass
    {
        foreach ($data as $key => $value) {
            if (is_array($value) || $value instanceof stdClass) {
                $value = $this->formatDates($value);
            } elseif (in_array($key, self::DATE_FIELDS, true) && is_string($value) && $value !== '') {
                try {
                    $timezone = config('app.timezone');
                    $value = CarbonImmutable::parse($value, $timezone)
                        ->setTimezone($timezone)->format('Y-m-d\TH:i:s');
                } catch (InvalidFormatException) {
                    // Preserve values that are not date strings.
                }
            }

            if (is_array($data)) {
                $data[$key] = $value;
            } else {
                $data->$key = $value;
            }
        }

        return $data;
    }
}
