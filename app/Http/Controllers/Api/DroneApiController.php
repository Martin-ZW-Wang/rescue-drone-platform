<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\DroneService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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

    public function takeoff(): JsonResponse
    {
        try {
            $data = $this->droneService->takeoff();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 503);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'TAKEOFF_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function land(): JsonResponse
    {
        try {
            $data = $this->droneService->land();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 503);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'LAND_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function emergency(): JsonResponse
    {
        try {
            $data = $this->droneService->emergency();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 500);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'EMERGENCY_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function resetSdk(): JsonResponse
    {
        try {
            $data = $this->droneService->resetSdk();
            return response()->json($data, ($data['ok'] ?? false) ? 200 : 503);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'SDK_RESET_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function rc(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'lr' => ['required', 'integer', 'between:-100,100'],
            'fb' => ['required', 'integer', 'between:-100,100'],
            'ud' => ['required', 'integer', 'between:-100,100'],
            'yaw' => ['required', 'integer', 'between:-100,100'],
        ]);

        try {
            $data = $this->droneService->sendRc(
                $validated['lr'],
                $validated['fb'],
                $validated['ud'],
                $validated['yaw'],
            );

            $status = ($data['ok'] ?? false) ? 200 : (($data['error'] ?? null) === 'DRONE_NOT_FLYING' ? 409 : 503);
            return response()->json($data, $status);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'RC_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function nudgeUp(): JsonResponse
    {
        try {
            $data = $this->droneService->nudgeUp();
            $status = ($data['ok'] ?? false) ? 200 : (($data['error'] ?? null) === 'DRONE_NOT_FLYING' ? 409 : 503);
            return response()->json($data, $status);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'NUDGE_UP_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function nudgeDown(): JsonResponse
    {
        try {
            $data = $this->droneService->nudgeDown();
            $status = ($data['ok'] ?? false) ? 200 : (($data['error'] ?? null) === 'DRONE_NOT_FLYING' ? 409 : 503);
            return response()->json($data, $status);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'NUDGE_DOWN_PROXY_FAILED',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    public function nudgeLeft(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeLeft(), 'NUDGE_LEFT_PROXY_FAILED');
    }

    public function nudgeRight(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeRight(), 'NUDGE_RIGHT_PROXY_FAILED');
    }

    public function nudgeForward(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeForward(), 'NUDGE_FORWARD_PROXY_FAILED');
    }

    public function nudgeBack(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeBack(), 'NUDGE_BACK_PROXY_FAILED');
    }

    public function nudgeYawLeft(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeYawLeft(), 'NUDGE_YAW_LEFT_PROXY_FAILED');
    }

    public function nudgeYawRight(): JsonResponse
    {
        return $this->nudge(fn () => $this->droneService->nudgeYawRight(), 'NUDGE_YAW_RIGHT_PROXY_FAILED');
    }

    private function nudge(callable $callback, string $proxyError): JsonResponse
    {
        try {
            $data = $callback();
            $status = ($data['ok'] ?? false) ? 200 : (($data['error'] ?? null) === 'DRONE_NOT_FLYING' ? 409 : 503);
            return response()->json($data, $status);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => $proxyError,
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
