<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Models\StudioStaffInvitation;
use App\Models\User;
use App\Services\StudioStaffService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioStaffInvitationController extends Controller
{
    public function store(
        Request $request,
        User $user,
        StudioStaffService $studioStaffService
    ): JsonResponse {
        $invitedBy = $request->user();
        abort_unless($invitedBy instanceof User, 401);

        $validated = $request->validate([
            'studio_id' => ['required', 'integer', 'exists:studios,id'],
            'role' => ['required', 'string', 'in:artist,designer'],
        ]);

        $studio = Studio::query()->findOrFail($validated['studio_id']);
        $role = UserRole::fromValue($validated['role']);

        abort_unless(
            in_array($studio->id, $invitedBy->staffScopeStudioIds(), true)
                && $invitedBy->canManageStaffRole($role),
            403
        );
        abort_unless($user->isIndependentProfessionalFor($role), 422);

        $result = $studioStaffService->createOrAttach(
            $studio,
            $role,
            [
                'name' => $user->fullName(),
                'email' => $user->email,
            ],
            $invitedBy
        );

        return response()->json([
            'message' => 'Kullanıcıya çalışanlık daveti gönderildi.',
            'data' => [
                'invitation_id' => $result['invitation']->id,
                'studio_id' => $studio->id,
                'user_id' => $user->id,
                'role' => $role->value,
                'status' => $result['invitation']->status,
            ],
        ], 202);
    }

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
