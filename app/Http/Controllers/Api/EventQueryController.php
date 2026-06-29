<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RescueEvent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EventQueryController extends Controller
{
    public function recent(Request $request): JsonResponse
    {
        $limit = (int) $request->query('limit', 10);
        $limit = max(1, min($limit, 50));

        $events = RescueEvent::query()
            ->latest('event_time')
            ->latest('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'ok' => true,
            'events' => $events,
        ]);
    }

    public function show(RescueEvent $event): JsonResponse
    {
        return response()->json([
            'ok' => true,
            'event' => $event,
        ]);
    }
}
