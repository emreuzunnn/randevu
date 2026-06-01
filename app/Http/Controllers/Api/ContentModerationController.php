<?php

namespace App\Http\Controllers\Api;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\Review;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ContentModerationController extends Controller
{
    public function banUser(Request $request, User $user, ContentModerationService $moderationService): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser?->hasRole(UserRole::Admin), 403);
        abort_if($authUser->is($user), 422, 'Kendinize ban veremezsiniz.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $moderationService->ban($user, $validated['reason'] ?? null);

        return response()->json(['message' => 'Kullanıcı uygulamadan banlandı.']);
    }

    public function unbanUser(Request $request, User $user, ContentModerationService $moderationService): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser?->hasRole(UserRole::Admin), 403);

        $moderationService->unban($user);

        return response()->json(['message' => 'Kullanıcının banı kaldırıldı.']);
    }

    public function reportReview(Request $request, Review $review): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User, 401);

        $validated = $this->validateReport($request);
        ContentReport::query()->updateOrCreate(
            [
                'reporter_user_id' => $authUser->id,
                'review_id' => $review->id,
            ],
            [
                'reported_user_id' => null,
                ...$validated,
                'status' => 'pending',
            ]
        );

        return response()->json(['message' => 'Şikayetiniz alındı.']);
    }

    public function reportUser(Request $request, User $user): JsonResponse
    {
        $authUser = $request->user();
        abort_unless($authUser instanceof User, 401);
        abort_if($authUser->is($user), 422, 'Kendi profilinizi şikayet edemezsiniz.');

        $validated = $this->validateReport($request);
        ContentReport::query()->updateOrCreate(
            [
                'reporter_user_id' => $authUser->id,
                'reported_user_id' => $user->id,
            ],
            [
                'review_id' => null,
                ...$validated,
                'status' => 'pending',
            ]
        );

        return response()->json(['message' => 'Şikayetiniz alındı.']);
    }

    public function reports(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $reports = ContentReport::query()
            ->with([
                'reporter:id,name,surname,email',
                'reportedUser:id,name,surname,email,banned_at,ban_reason',
                'review:id,user_id,comment,image_path',
                'review.reviewer:id,name,surname,email,banned_at,ban_reason',
            ])
            ->latest()
            ->paginate((int) $request->integer('per_page', 50));

        return response()->json([
            'data' => [
                'items' => $reports->getCollection()
                    ->map(fn (ContentReport $report): array => $this->reportResource($report))
                    ->values(),
                'pagination' => [
                    'current_page' => $reports->currentPage(),
                    'last_page' => $reports->lastPage(),
                    'per_page' => $reports->perPage(),
                    'total' => $reports->total(),
                ],
            ],
        ]);
    }

    public function resolveReport(Request $request, ContentReport $contentReport): JsonResponse
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);

        $contentReport->forceFill(['status' => 'resolved'])->save();

        return response()->json(['message' => 'Şikayet incelendi olarak işaretlendi.']);
    }

    private function validateReport(Request $request): array
    {
        return $request->validate([
            'reason' => ['required', 'string', 'in:profanity,harassment,inappropriate_content,spam,other'],
            'details' => ['nullable', 'string', 'max:1000'],
        ]);
    }

    private function reportResource(ContentReport $report): array
    {
        $reportedUser = $report->reportedUser ?? $report->review?->reviewer;

        return [
            'id' => $report->id,
            'type' => $report->review_id !== null ? 'review' : 'user',
            'reason' => $report->reason,
            'details' => $report->details,
            'status' => $report->status,
            'created_at' => $report->created_at?->toIso8601String(),
            'reporter' => $report->reporter ? [
                'id' => $report->reporter->id,
                'name' => $report->reporter->fullName(),
                'email' => $report->reporter->email,
            ] : null,
            'reported_user' => $reportedUser ? [
                'id' => $reportedUser->id,
                'name' => $reportedUser->fullName(),
                'email' => $reportedUser->email,
                'is_banned' => $reportedUser->banned_at !== null,
                'ban_reason' => $reportedUser->ban_reason,
            ] : null,
            'review' => $report->review ? [
                'id' => $report->review->id,
                'comment' => $report->review->comment,
                'image_path' => $this->mediaUrl($report->review->image_path),
            ] : null,
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || $path === '' || str_starts_with($path, 'http')) {
            return $path;
        }

        return str_starts_with($path, '/storage/') || str_starts_with($path, 'storage/')
            ? url($path)
            : url('storage/' . ltrim($path, '/'));
    }
}
