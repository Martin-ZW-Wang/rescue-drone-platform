<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DroneService;
use Illuminate\Http\JsonResponse;
use Throwable;

class DroneApiController extends Controller
{
    public function __construct(private DroneService $droneService) {}

    public function status(): JsonResponse
    {
        try {
            return response()->json($this->droneService->getStatus());
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'STATUS_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function start(): JsonResponse
    {
        try {
            $data = $this->droneService->startTracking();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 503);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'START_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function stop(): JsonResponse
    {
        try {
            $data = $this->droneService->stopTracking();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'STOP_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function startPose(): JsonResponse
    {
        try {
            $data = $this->droneService->startPose();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'POSE_START_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function stopPose(): JsonResponse
    {
        try {
            $data = $this->droneService->stopPose();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'POSE_STOP_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function debugSeg(): JsonResponse
    {
        try {
            $data = $this->droneService->debugSeg();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'SEG_DEBUG_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
