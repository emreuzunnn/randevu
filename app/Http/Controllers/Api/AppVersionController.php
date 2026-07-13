<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AppVersionController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'platform' => ['required', Rule::in(['ios', 'android'])],
            'version' => ['required', 'regex:/^\d+\.\d+\.\d+$/'],
            'build' => ['nullable', 'integer', 'min:0'],
        ]);

        $platform = $validated['platform'];
        $minimumVersion = (string) config("app_update.{$platform}.minimum_version", '1.0.0');
        $latestVersion = (string) config("app_update.{$platform}.latest_version", $minimumVersion);
        $currentVersion = $validated['version'];

        return response()->json([
            'data' => [
                'platform' => $platform,
                'current_version' => $currentVersion,
                'current_build' => isset($validated['build']) ? (int) $validated['build'] : null,
                'minimum_version' => $minimumVersion,
                'latest_version' => $latestVersion,
                'force_update' => version_compare($currentVersion, $minimumVersion, '<'),
                'update_available' => version_compare($currentVersion, $latestVersion, '<'),
                'store_url' => (string) config("app_update.{$platform}.store_url"),
                'message' => (string) config('app_update.message'),
            ],
        ]);
    }
}
