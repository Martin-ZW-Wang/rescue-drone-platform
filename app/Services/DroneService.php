<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DroneService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.drone.base_url', 'http://127.0.0.1:5001'), '/');
    }

    public function getStatus(): array
    {
        $res = Http::timeout(5)->get($this->baseUrl . '/status');

        if (!$res->successful()) {
            throw new RuntimeException("Flask /status failed: HTTP {$res->status()}");
        }

        return $res->json();
    }

    public function startTracking(): array
    {
        $res = Http::timeout(8)->post($this->baseUrl . '/start');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /start failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function stopTracking(): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . '/stop');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /stop failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function startPose(): array
    {
        $res = Http::timeout(8)->post($this->baseUrl . '/pose/start');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /pose/start failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function stopPose(): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . '/pose/stop');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /pose/stop failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function debugSeg(): array
    {
        $res = Http::timeout(120)->get($this->baseUrl . '/debug/seg');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /debug/seg failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }
}
