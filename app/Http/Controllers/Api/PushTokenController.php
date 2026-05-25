<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PushToken;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PushTokenController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'token'       => ['required', 'string', 'max:4096'],
            'platform'    => ['nullable', 'string', 'in:android,ios,web,macos,windows,linux,unknown'],
            'device_id'   => ['nullable', 'string', 'max:255'],
            'app_version' => ['nullable', 'string', 'max:40'],
        ]);

        $pushToken = PushToken::query()->updateOrCreate(
            ['token_hash' => hash('sha256', $validated['token'])],
            [
                'user_id'      => $user->id,
                'token'        => $validated['token'],
                'platform'     => $validated['platform'] ?? 'unknown',
                'device_id'    => $validated['device_id'] ?? null,
                'app_version'  => $validated['app_version'] ?? null,
                'last_used_at' => now(),
            ],
        );

        return response()->json([
            'message' => 'Bildirim token kaydedildi.',
            'data'    => ['id' => $pushToken->id],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'token' => ['required', 'string', 'max:4096'],
        ]);

        PushToken::query()
            ->where('user_id', $user->id)
            ->where('token_hash', hash('sha256', $validated['token']))
            ->delete();

        return response()->json(['message' => 'Bildirim token silindi.']);
    }
}
