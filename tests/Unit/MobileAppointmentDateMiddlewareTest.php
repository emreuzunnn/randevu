<?php

namespace Tests\Unit;

use App\Http\Controllers\Api\AppointmentRequestController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Middleware\MobileAppointmentDateMiddleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Tests\TestCase;

class MobileAppointmentDateMiddlewareTest extends TestCase
{
    public function test_mobile_nested_dates_are_local_and_other_values_keep_their_shape(): void
    {
        config(['app.timezone' => 'Europe/Istanbul']);
        $request = $this->mobileRequest(AppointmentRequestController::class);
        $response = app(MobileAppointmentDateMiddleware::class)->handle($request, fn () => response()->json([
            'data' => [
                'requested_at' => '2026-09-24T17:00:00+03:00',
                'appointment' => ['appointment_at' => '2026-09-24T14:00:00.000000Z'],
                'previous' => [
                    ['appointment_at' => '2026-09-24T22:30:00Z'],
                    ['appointment_at' => null],
                ],
                'created_at' => '2026-09-24T14:00:00Z',
                'customer' => (object) [],
                'images' => [],
                'notes' => '2026-09-24T14:00:00Z',
            ],
        ]));

        $data = $response->getData()->data;
        $this->assertSame('2026-09-24T17:00:00', $data->requested_at);
        $this->assertSame('2026-09-24T17:00:00', $data->appointment->appointment_at);
        $this->assertSame('2026-09-25T01:30:00', $data->previous[0]->appointment_at);
        $this->assertNull($data->previous[1]->appointment_at);
        $this->assertSame('2026-09-24T14:00:00Z', $data->created_at);
        $this->assertSame('2026-09-24T14:00:00Z', $data->notes);
        $this->assertInstanceOf(\stdClass::class, $data->customer);
        $this->assertSame([], $data->images);
    }

    public function test_error_responses_and_unrelated_controllers_are_unchanged(): void
    {
        $payload = ['appointment_at' => '2026-09-24T14:00:00Z'];
        $middleware = app(MobileAppointmentDateMiddleware::class);
        $error = response()->json($payload, 422);
        $unrelated = response()->json($payload);
        $middleware->handle($this->mobileRequest(AppointmentRequestController::class), fn () => $error);
        $middleware->handle($this->mobileRequest(NotificationController::class), fn () => $unrelated);

        $this->assertSame($payload, $error->getData(true));
        $this->assertSame($payload, $unrelated->getData(true));
    }

    private function mobileRequest(string $controller): Request
    {
        $request = Request::create('/api/appointment-requests');
        $request->headers->set('User-Agent', 'Dart/3.10 (dart:io)');
        $request->setRouteResolver(fn () => new Route('GET', '/api/appointment-requests', [
            'uses' => $controller.'@index',
            'controller' => $controller.'@index',
        ]));

        return $request;
    }
}
