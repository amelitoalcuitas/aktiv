<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Location\ApproximateLocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LocationController extends Controller
{
    public function approximate(
        Request $request,
        ApproximateLocationService $approximateLocationService
    ): JsonResponse {
        $ip = $approximateLocationService->extractClientIp($request);

        if ($ip === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => $approximateLocationService->locate($ip),
        ]);
    }
}
