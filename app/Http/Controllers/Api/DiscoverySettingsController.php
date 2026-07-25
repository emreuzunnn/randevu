<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DiscoverySettingsController extends Controller
{
    private const STUDIOS_ENABLED_KEY = 'discovery_studios_enabled';

    public function show(): JsonResponse
    {
        return response()->json([
            'status' => 'success',
            'data' => $this->payload(),
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'studios_enabled' => ['sometimes', 'boolean'],
            'studios' => ['sometimes', 'array'],
            'studios.*.id' => ['required_with:studios', 'integer', 'exists:studios,id'],
            'studios.*.discovery_visible' => ['required_with:studios', 'boolean'],
        ]);

        DB::transaction(function () use ($validated): void {
            if (array_key_exists('studios_enabled', $validated)) {
                AppSetting::setValue(self::STUDIOS_ENABLED_KEY, (bool) $validated['studios_enabled']);
            }

            foreach ($validated['studios'] ?? [] as $item) {
                Studio::query()
                    ->whereKey($item['id'])
                    ->update(['discovery_visible' => (bool) $item['discovery_visible']]);
            }
        });

        return response()->json([
            'status' => 'success',
            'message' => 'Keşfet stüdyo görünürlüğü güncellendi.',
            'data' => $this->payload(),
        ]);
    }

    private function payload(): array
    {
        $studiosEnabled = AppSetting::boolean(self::STUDIOS_ENABLED_KEY, true);

        $studios = Studio::query()
            ->with('company')
            ->orderBy('name')
            ->get();

        return [
            'studios_enabled' => $studiosEnabled,
            'studios' => $studios->map(fn (Studio $studio): array => [
                'id' => $studio->id,
                'name' => $studio->name,
                'location' => $studio->location,
                'discovery_visible' => (bool) $studio->discovery_visible,
                'company' => $studio->company ? [
                    'id' => $studio->company->id,
                    'name' => $studio->company->name,
                ] : null,
            ])->values()->all(),
        ];
    }
}
