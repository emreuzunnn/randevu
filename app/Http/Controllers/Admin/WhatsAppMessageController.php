<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\WhatsAppInboundMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WhatsAppMessageController extends Controller
{
    public function __invoke(Request $request): View
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $search = trim($request->string('q')->toString());
        $type = $request->string('type')->toString();
        $replyStatus = $request->string('reply_status')->toString();
        $dateFrom = $request->date('date_from');
        $dateTo = $request->date('date_to');

        $query = WhatsAppInboundMessage::query()->latest('received_at');

        if ($search !== '') {
            $query->where(function ($searchQuery) use ($search): void {
                $searchQuery
                    ->where('from_phone', 'like', "%{$search}%")
                    ->orWhere('profile_name', 'like', "%{$search}%")
                    ->orWhere('message_body', 'like', "%{$search}%")
                    ->orWhere('auto_reply_error', 'like', "%{$search}%");
            });
        }

        if ($type !== '') {
            $query->where('message_type', $type);
        }

        if ($replyStatus !== '') {
            $query->where('auto_reply_status', $replyStatus);
        }

        if ($dateFrom) {
            $query->where('received_at', '>=', $dateFrom->startOfDay());
        }

        if ($dateTo) {
            $query->where('received_at', '<=', $dateTo->endOfDay());
        }

        $logs = $query->paginate(50)->withQueryString();

        $summary = [
            'total' => WhatsAppInboundMessage::query()->count(),
            'today' => WhatsAppInboundMessage::query()->whereDate('received_at', today())->count(),
            'auto_replied' => WhatsAppInboundMessage::query()->where('auto_reply_status', 'sent')->count(),
            'failed' => WhatsAppInboundMessage::query()->where('auto_reply_status', 'failed')->count(),
        ];

        return view('admin.whatsapp-messages.index', [
            'logs' => $logs,
            'summary' => $summary,
            'types' => WhatsAppInboundMessage::query()
                ->whereNotNull('message_type')
                ->distinct()
                ->orderBy('message_type')
                ->pluck('message_type'),
            'replyStatuses' => WhatsAppInboundMessage::query()
                ->whereNotNull('auto_reply_status')
                ->distinct()
                ->orderBy('auto_reply_status')
                ->pluck('auto_reply_status'),
            'filters' => [
                'q' => $search,
                'type' => $type,
                'reply_status' => $replyStatus,
                'date_from' => $dateFrom?->toDateString(),
                'date_to' => $dateTo?->toDateString(),
            ],
        ]);
    }
}
