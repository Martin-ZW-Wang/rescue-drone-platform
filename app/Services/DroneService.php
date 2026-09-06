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

    public function takeoff(): array
    {
        $res = Http::timeout(15)->post($this->baseUrl . '/takeoff');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /takeoff failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function land(): array
    {
        $res = Http::timeout(15)->post($this->baseUrl . '/land');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /land failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function emergency(): array
    {
        $res = Http::timeout(8)->post($this->baseUrl . '/emergency');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /emergency failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function resetSdk(): array
    {
        $res = Http::timeout(10)->post($this->baseUrl . '/sdk/reset');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /sdk/reset failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function sendRc(int $lr, int $fb, int $ud, int $yaw): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . '/rc', [
            'lr' => $lr,
            'fb' => $fb,
            'ud' => $ud,
            'yaw' => $yaw,
        ]);

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /rc failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function nudgeUp(): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . '/nudge/up');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /nudge/up failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function nudgeDown(): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . '/nudge/down');

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /nudge/down failed: HTTP {$res->status()}",
            ];
        }

        return $res->json();
    }

    public function nudgeLeft(): array
    {
        return $this->postNudge('left');
    }

    public function nudgeRight(): array
    {
        return $this->postNudge('right');
    }

    public function nudgeForward(): array
    {
        return $this->postNudge('forward');
    }

    public function nudgeBack(): array
    {
        return $this->postNudge('back');
    }

    public function nudgeYawLeft(): array
    {
        return $this->postNudge('yaw-left');
    }

    public function nudgeYawRight(): array
    {
        return $this->postNudge('yaw-right');
    }

    private function postNudge(string $direction): array
    {
        $res = Http::timeout(5)->post($this->baseUrl . "/nudge/{$direction}");

        if (!$res->successful()) {
            return $res->json() ?: [
                'ok' => false,
                'error' => "Flask /nudge/{$direction} failed: HTTP {$res->status()}",
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
