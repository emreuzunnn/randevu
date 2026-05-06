<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AppointmentReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function index(Request $request, AppointmentReportService $reportService): JsonResponse
    {
        $user     = $request->user();
        $period   = $request->input('period', 'monthly');
        $studioId = $request->integer('studio_id') ?: null;

        $data = $reportService->buildReport($user, $period, $studioId);

        return response()->json([
            'status' => 'success',
            'code'   => 200,
            'data'   => $data,
        ]);
    }
}
