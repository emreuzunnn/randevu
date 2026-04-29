<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppointmentSlipOcrService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Throwable;

class AppointmentSlipOcrController extends Controller
{
    public function __invoke(Request $request, AppointmentSlipOcrService $ocrService): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'file', 'image', 'max:10240'],
        ]);

        try {
            $result = $ocrService->parse($validated['image']->getRealPath());
        } catch (Throwable $exception) {
            return response()->json([
                'message' => 'Gorsel okunamadi.',
                'error' => $exception->getMessage(),
            ], 422);
        }

        return response()->json([
            'data' => $result,
        ]);
    }
}
