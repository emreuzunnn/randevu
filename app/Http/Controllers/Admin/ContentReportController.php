<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\ContentReport;
use App\Models\User;
use App\Services\ContentModerationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentReportController extends Controller
{
    public function index(Request $request): View
    {
        $this->ensureAdmin($request);

        $status = $request->string('status')->toString();
        $reports = ContentReport::query()
            ->with([
                'reporter:id,name,surname,email',
                'reportedUser:id,name,surname,email,banned_at,ban_reason',
                'review:id,user_id,comment,image_path',
                'review.reviewer:id,name,surname,email,banned_at,ban_reason',
            ])
            ->when(in_array($status, ['pending', 'resolved'], true), fn ($query) => $query->where('status', $status))
            ->latest()
            ->paginate(50)
            ->withQueryString();

        return view('admin.content-reports.index', [
            'reports' => $reports,
            'status' => $status,
            'summary' => [
                'total' => ContentReport::query()->count(),
                'pending' => ContentReport::query()->where('status', 'pending')->count(),
                'resolved' => ContentReport::query()->where('status', 'resolved')->count(),
            ],
        ]);
    }

    public function resolve(Request $request, ContentReport $contentReport): RedirectResponse
    {
        $this->ensureAdmin($request);
        $contentReport->forceFill(['status' => 'resolved'])->save();

        return back()->with('status', 'Şikayet incelendi olarak işaretlendi.');
    }

    public function ban(Request $request, User $user, ContentModerationService $moderationService): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_if($request->user()->is($user), 422, 'Kendinize ban veremezsiniz.');

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $moderationService->ban($user, $validated['reason'] ?? null);

        return back()->with('status', 'Kullanıcı uygulamadan banlandı.');
    }

    public function unban(Request $request, User $user, ContentModerationService $moderationService): RedirectResponse
    {
        $this->ensureAdmin($request);
        $moderationService->unban($user);

        return back()->with('status', 'Kullanıcının banı kaldırıldı.');
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless($request->user()?->hasRole(UserRole::Admin), 403);
    }
}
