<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StudioStaffInvitation;
use App\Models\User;
use App\Services\StudioStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioStaffInvitationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $invitations = StudioStaffInvitation::query()
            ->with(['studio:id,name', 'invitedBy:id,name,surname'])
            ->where('user_id', $user->id)
            ->latest()
            ->get();

        return response()->json([
            'data' => $invitations->map(
                fn (StudioStaffInvitation $invitation): array => $this->format($invitation)
            )->values(),
        ]);
    }

    public function accept(
        Request $request,
        StudioStaffInvitation $studioStaffInvitation,
        StudioStaffService $studioStaffService
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $invitation = $studioStaffService->acceptInvitation($studioStaffInvitation, $user);

        return response()->json([
            'message' => 'Çalışanlık daveti kabul edildi.',
            'data' => $this->format($invitation),
        ]);
    }

    public function reject(
        Request $request,
        StudioStaffInvitation $studioStaffInvitation,
        StudioStaffService $studioStaffService
    ): JsonResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $invitation = $studioStaffService->rejectInvitation($studioStaffInvitation, $user);

        return response()->json([
            'message' => 'Çalışanlık daveti reddedildi.',
            'data' => $this->format($invitation),
        ]);
    }

    private function format(StudioStaffInvitation $invitation): array
    {
        $invitation->loadMissing(['studio:id,name', 'invitedBy:id,name,surname']);

        return [
            'id' => $invitation->id,
            'status' => $invitation->status,
            'role' => $invitation->role,
            'studio' => [
                'id' => $invitation->studio?->id,
                'name' => $invitation->studio?->name,
            ],
            'invited_by' => [
                'id' => $invitation->invitedBy?->id,
                'name' => $invitation->invitedBy?->fullName(),
            ],
            'responded_at' => $invitation->responded_at?->toIso8601String(),
            'created_at' => $invitation->created_at?->toIso8601String(),
        ];
    }
}
