<?php

namespace App\Http\Controllers;

use App\Models\RescueEvent;

class EventController extends Controller
{
    public function index()
    {
        $events = RescueEvent::latest()->paginate(20);

        return view('events.index', compact('events'));
    }

    public function show(RescueEvent $event)
    {
        return view('events.show', compact('event'));
    }
}