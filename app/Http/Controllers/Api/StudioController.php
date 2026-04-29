<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioController extends Controller
{
    public function update(Request $request, Studio $studio): JsonResponse
    {
        abort_unless($request->user()?->canManageStudio($studio), 403);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'location' => ['sometimes', 'nullable', 'string', 'max:255'],
            'logo_path' => ['sometimes', 'nullable', 'string', 'max:2048'],
            'notification_lead_minutes' => ['sometimes', 'integer', 'min:0', 'max:1440'],
        ]);

        $studio->fill($validated)->save();

        return response()->json([
            'message' => 'Studyo guncellendi.',
            'data' => $studio->only([
                'id',
                'name',
                'location',
                'slug',
                'logo_path',
                'notification_lead_minutes',
                'shop_id',
            ]),
        ]);
    }
}
