<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RescueEvent;
use Illuminate\Http\Request;

class EventIngestController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'event_type' => 'nullable|string|max:255',
            'tid' => 'nullable|string|max:255',
            'status' => 'nullable|string|max:255',
            'conf' => 'nullable|numeric',
            'message' => 'nullable|string',
            'bbox_json' => 'nullable|array',
            'dbg_json' => 'nullable|array',
            'event_time' => 'nullable|date',
        ]);

        $event = RescueEvent::create([
            'event_type' => $data['event_type'] ?? 'RESCUE',
            'tid' => $data['tid'] ?? null,
            'status' => $data['status'] ?? 'Rescue Needed',
            'conf' => $data['conf'] ?? null,
            'message' => $data['message'] ?? null,
            'bbox_json' => $data['bbox_json'] ?? null,
            'dbg_json' => $data['dbg_json'] ?? null,
            'event_time' => $data['event_time'] ?? now(),
            'review_status' => 'pending',
            'review_note' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Event stored successfully',
            'id' => $event->id,
        ], 201);
    }
}