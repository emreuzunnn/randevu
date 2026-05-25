<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\PushNotification;
use App\Models\PushNotificationDelivery;
use Illuminate\Http\Request;
use Illuminate\View\View;

class NotificationLogController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $status = $request->string('status')->toString();
        $platform = $request->string('platform')->toString();
        $type = $request->string('type')->toString();
        $search = trim($request->string('q')->toString());

        $query = PushNotificationDelivery::query()
            ->with(['notification', 'user', 'pushToken'])
            ->latest('attempted_at');

        if (in_array($status, ['sent', 'failed', 'skipped'], true)) {
            $query->where('status', $status);
        }

        if ($platform !== '') {
            $query->where('platform', $platform);
        }

        if ($type !== '') {
            $query->whereHas('notification', fn ($notificationQuery) => $notificationQuery->where('type', $type));
        }

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('fcm_status', 'like', "%{$search}%")
                    ->orWhere('fcm_error_code', 'like', "%{$search}%")
                    ->orWhere('error_message', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search): void {
                        $userQuery
                            ->where('name', 'like', "%{$search}%")
                            ->orWhere('surname', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    })
                    ->orWhereHas('notification', function ($notificationQuery) use ($search): void {
                        $notificationQuery
                            ->where('title', 'like', "%{$search}%")
                            ->orWhere('body', 'like', "%{$search}%")
                            ->orWhere('type', 'like', "%{$search}%");
                    });
            });
        }

        $logs = $query->paginate(50)->withQueryString();

        $summary = [
            'total'        => PushNotificationDelivery::query()->count(),
            'sent'         => PushNotificationDelivery::query()->where('status', 'sent')->count(),
            'failed'       => PushNotificationDelivery::query()->where('status', 'failed')->count(),
            'skipped'      => PushNotificationDelivery::query()->where('status', 'skipped')->count(),
            'failed_today' => PushNotificationDelivery::query()
                ->where('status', 'failed')
                ->whereDate('attempted_at', today())
                ->count(),
        ];

        return view('admin.notifications.index', [
            'logs'      => $logs,
            'summary'   => $summary,
            'platforms' => PushNotificationDelivery::query()
                ->whereNotNull('platform')
                ->distinct()
                ->orderBy('platform')
                ->pluck('platform'),
            'types' => PushNotification::query()
                ->distinct()
                ->orderBy('type')
                ->pluck('type'),
            'filters' => [
                'status'   => $status,
                'platform' => $platform,
                'type'     => $type,
                'q'        => $search,
            ],
        ]);
    }
}
