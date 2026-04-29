<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Studio;
use App\Services\StudioManagerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StudioManagerController extends Controller
{
    public function store(Request $request, Studio $studio, StudioManagerService $studioManagerService): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ]);

        $result = $studioManagerService->createOrAttachManager($studio, $validated);

        return response()->json([
            'message' => 'Yonetici olusturuldu.',
            'data' => [
                'user' => [
                    'id' => $result['user']->id,
                    'name' => $result['user']->name,
                    'email' => $result['user']->email,
                ],
                'studio_id' => $studio->id,
                'studio_role' => $result['studio_role'],
                'action' => $result['action'],
            ],
        ], 201);
    }
}
