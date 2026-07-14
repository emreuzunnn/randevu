<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\StaffEarning;
use App\Models\Studio;
use App\Models\User;
use App\Services\StaffEarningService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StaffEarningController extends Controller
{
    public function mine(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', 'in:pending,paid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $earnings = StaffEarning::query()
            ->with(['studio:id,name', 'appointment:id,appointment_at,first_name,last_name,price'])
            ->where('user_id', $request->user()->id)
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date_from'] ?? null, function ($query, string $date): void {
                $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '>=', $date));
            })
            ->when($validated['date_to'] ?? null, function ($query, string $date): void {
                $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '<=', $date));
            })
            ->latest('id')
            ->get();

        $lastSevenDaysPendingTotal = StaffEarning::query()
            ->where('user_id', $request->user()->id)
            ->where('status', 'pending')
            ->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '>=', now()->subDays(6)->toDateString()))
            ->sum('earning_amount');

        return response()->json([
            'data' => [
                'summary' => [
                    ...$this->summary($earnings),
                    'last_7_days_pending_total' => round((float) $lastSevenDaysPendingTotal, 2),
                ],
                'filters' => [
                    'status' => $validated['status'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                ],
                'earnings' => $earnings->map(fn (StaffEarning $earning): array => $this->earningData($earning))->values(),
            ],
        ]);
    }

    public function studio(Request $request, Studio $studio): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageStaffEarnings($actor) && $actor->canManageStudio($studio), 403);

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'status' => ['nullable', 'in:pending,paid'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ]);

        $earnings = StaffEarning::query()
            ->with([
                'user:id,name,surname,profile_image',
                'appointment:id,appointment_at,first_name,last_name,price',
                'paidBy:id,name,surname',
            ])
            ->where('studio_id', $studio->id)
            ->when($validated['user_id'] ?? null, fn ($query, $userId) => $query->where('user_id', $userId))
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->when($validated['date_from'] ?? null, function ($query, string $date): void {
                $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '>=', $date));
            })
            ->when($validated['date_to'] ?? null, function ($query, string $date): void {
                $query->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '<=', $date));
            })
            ->latest('id')
            ->get();

        $staff = $studio->users()
            ->wherePivot('is_active', true)
            ->wherePivotIn('role', array_map(
                static fn (UserRole $role): string => $role->value,
                UserRole::studioRoles(),
            ))
            ->orderBy('users.name')
            ->get();

        $lastSevenDaysPendingQuery = StaffEarning::query()
            ->where('studio_id', $studio->id)
            ->where('status', 'pending')
            ->whereHas('appointment', fn ($appointmentQuery) => $appointmentQuery->whereDate('appointment_at', '>=', now()->subDays(6)->toDateString()));

        if (isset($validated['user_id'])) {
            $lastSevenDaysPendingQuery->where('user_id', $validated['user_id']);
        }

        return response()->json([
            'data' => [
                'studio' => [
                    'id' => $studio->id,
                    'name' => $studio->name,
                ],
                'summary' => [
                    ...$this->summary($earnings),
                    'last_7_days_pending_total' => round((float) $lastSevenDaysPendingQuery->sum('earning_amount'), 2),
                ],
                'filters' => [
                    'user_id' => isset($validated['user_id']) ? (int) $validated['user_id'] : null,
                    'status' => $validated['status'] ?? null,
                    'date_from' => $validated['date_from'] ?? null,
                    'date_to' => $validated['date_to'] ?? null,
                ],
                'staff' => $staff->map(function (User $user) use ($earnings): array {
                    $userEarnings = $earnings->where('user_id', $user->id);

                    return [
                        'id' => $user->id,
                        'name' => $user->fullName(),
                        'role' => $user->pivot->role,
                        'commission_rate' => (float) ($user->pivot->commission_rate ?? 0),
                        'pending_total' => round((float) $userEarnings->where('status', 'pending')->sum('earning_amount'), 2),
                        'paid_total' => round((float) $userEarnings->where('status', 'paid')->sum('earning_amount'), 2),
                        'earning_count' => $userEarnings->count(),
                    ];
                })->values(),
                'earnings' => $earnings->map(fn (StaffEarning $earning): array => $this->earningData($earning))->values(),
            ],
        ]);
    }

    public function updateCommission(Request $request, Studio $studio, User $user): JsonResponse
    {
        $actor = $request->user();
        abort_unless($this->canManageStaffEarnings($actor) && $actor->canManageStudio($studio), 403);

        $membership = $studio->users()
            ->where('users.id', $user->id)
            ->wherePivot('is_active', true)
            ->first();

        abort_if($membership === null, 404);

        $validated = $request->validate([
            'commission_rate' => ['required', 'numeric', 'min:0', 'max:100'],
        ]);

        $studio->users()->updateExistingPivot($user->id, [
            'commission_rate' => $validated['commission_rate'],
        ]);

        return response()->json([
            'message' => 'Komisyon oranı güncellendi.',
            'data' => [
                'user_id' => $user->id,
                'studio_id' => $studio->id,
                'commission_rate' => (float) $validated['commission_rate'],
            ],
        ]);
    }

    public function markPaid(
        Request $request,
        Studio $studio,
        StaffEarning $staffEarning,
        StaffEarningService $staffEarningService
    ): JsonResponse {
        $actor = $request->user();
        abort_unless($this->canManageStaffEarnings($actor) && $actor->canManageStudio($studio), 403);
        abort_unless((int) $staffEarning->studio_id === (int) $studio->id, 404);

        $earning = $staffEarningService->markAsPaid($staffEarning, $actor);

        return response()->json([
            'message' => 'Hakediş ödendi olarak işaretlendi.',
            'data' => $this->earningData($earning),
        ]);
    }

    private function summary($earnings): array
    {
        return [
            'pending_total' => round((float) $earnings->where('status', 'pending')->sum('earning_amount'), 2),
            'paid_total' => round((float) $earnings->where('status', 'paid')->sum('earning_amount'), 2),
            'total' => round((float) $earnings->sum('earning_amount'), 2),
            'pending_count' => $earnings->where('status', 'pending')->count(),
            'paid_count' => $earnings->where('status', 'paid')->count(),
        ];
    }

    private function canManageStaffEarnings(?User $user): bool
    {
        return $user !== null
            && $user->hasAnyRole([UserRole::Admin, UserRole::Yonetici]);
    }

    private function earningData(StaffEarning $earning): array
    {
        return [
            'id' => $earning->id,
            'appointment_id' => $earning->appointment_id,
            'studio_id' => $earning->studio_id,
            'studio_name' => $earning->studio?->name,
            'user_id' => $earning->user_id,
            'user_name' => $earning->user?->fullName(),
            'role' => $earning->role,
            'commission_rate' => (float) $earning->commission_rate,
            'gross_amount' => (float) $earning->gross_amount,
            'earning_amount' => (float) $earning->earning_amount,
            'status' => $earning->status,
            'paid_at' => $earning->paid_at?->toIso8601String(),
            'paid_by' => $earning->paidBy?->fullName(),
            'appointment' => $earning->appointment ? [
                'appointment_at' => $earning->appointment->appointment_at?->toIso8601String(),
                'customer_name' => trim($earning->appointment->first_name.' '.$earning->appointment->last_name),
            ] : null,
        ];
    }
}
