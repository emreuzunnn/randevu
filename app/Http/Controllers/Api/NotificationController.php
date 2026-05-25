<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use App\Models\User;
use App\Services\FcmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $notifications = PushNotification::query()
            ->where('user_id', $user->id)
            ->latest()
            ->limit(100)
            ->get();

        return response()->json([
            'data' => [
                'unread_count' => $notifications->whereNull('read_at')->count(),
                'unread'       => $notifications->whereNull('read_at')->map(fn (PushNotification $notification): array => $this->format($notification))->values(),
                'read'         => $notifications->whereNotNull('read_at')->map(fn (PushNotification $notification): array => $this->format($notification))->values(),
            ],
        ]);
    }

    public function markAsRead(Request $request, PushNotification $pushNotification): JsonResponse
    {
        $this->authorizeOwner($request, $pushNotification);

        $pushNotification->forceFill(['read_at' => $pushNotification->read_at ?? now()])->save();

        return response()->json([
            'message' => 'Bildirim okundu.',
            'data'    => $this->format($pushNotification->fresh()),
        ]);
    }

    public function markAllAsRead(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        PushNotification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json(['message' => 'Tüm bildirimler okundu.']);
    }

    public function destroy(Request $request, PushNotification $pushNotification): JsonResponse
    {
        $this->authorizeOwner($request, $pushNotification);

        $pushNotification->delete();

        return response()->json(['message' => 'Bildirim silindi.']);
    }

    public function test(Request $request, FcmService $fcmService): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $notification = $fcmService->sendToUser(
            $user,
            'Test Bildirimi',
            'Tattoodesk bildirim sistemi çalışmaya hazır.',
            'test',
            ['source' => 'manual_test'],
        );

        return response()->json([
            'message' => $fcmService->isConfigured()
                ? 'Test bildirimi gönderildi.'
                : 'Test bildirimi kaydedildi. FCM göndermek için service account eklenmeli.',
            'data' => $this->format($notification),
        ]);
    }

    private function authorizeOwner(Request $request, PushNotification $pushNotification): void
    {
        abort_unless((int) $pushNotification->user_id === (int) $request->user()?->id, 403);
    }

    private function format(?PushNotification $notification): array
    {
        abort_if($notification === null, 404);

        $isRead = $notification->read_at !== null;

        return [
            'id'          => (string) $notification->id,
            'type'        => $notification->type,
            'icon'        => $this->icon($notification->type),
            'iconColor'   => $isRead ? '94A3B8' : $this->color($notification->type),
            'title'       => $notification->title,
            'description' => $notification->body,
            'body'        => $notification->body,
            'data'        => $notification->data ?? [],
            'time'        => $notification->created_at?->diffForHumans() ?? '',
            'created_at'  => $notification->created_at?->toIso8601String(),
            'read_at'     => $notification->read_at?->toIso8601String(),
            'isRead'      => $isRead,
        ];
    }

    private function icon(string $type): string
    {
        return match ($type) {
            'appointment_request',
            'appointment_request_accepted',
            'appointment_request_rejected' => 'calendar',
            'artist_assigned',
            'artist_response' => 'person',
            'driver_action' => 'transfer',
            'warning' => 'warning',
            default => 'notification',
        };
    }

    private function color(string $type): string
    {
        return match ($type) {
            'appointment_request' => '4ECDC4',
            'appointment_request_accepted' => '22C55E',
            'appointment_request_rejected' => 'EF4444',
            'artist_assigned' => '001B5E',
            'artist_response' => '8B5CF6',
            'driver_action' => 'F59E0B',
            default => '001B5E',
        };
    }
}
