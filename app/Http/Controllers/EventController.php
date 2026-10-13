<?php

namespace App\Http\Controllers;

use App\Models\RescueEvent;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;

class EventController extends Controller
{
    public function index(): View
    {
        $events = RescueEvent::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20);

        return view('events.index', compact('events'));
    }

    public function show(RescueEvent $event): View
    {
        $displayNumber = $this->displayNumberFor($event);

        return view('events.show', compact('event', 'displayNumber'));
    }

    public function updateReview(Request $request, RescueEvent $event): RedirectResponse
    {
        $data = $request->validate([
            'review_status' => 'required|string|in:pending,reviewed,dismissed',
            'review_note' => 'nullable|string|max:1000',
        ]);

        $statusMessages = [
            'pending' => 'Incident returned to pending review.',
            'reviewed' => 'Incident marked as confirmed.',
            'dismissed' => 'Incident marked as dismissed.',
        ];

        $event->update([
            'review_status' => $data['review_status'],
            'review_note' => $data['review_note'] ?? $event->review_note,
        ]);

        return back()->with('status', $statusMessages[$data['review_status']]);
    }

    public function destroy(RescueEvent $event): RedirectResponse
    {
        $this->deleteEventImage($event->original_image_path);
        $this->deleteEventImage($event->annotated_image_path);

        $event->delete();

        return redirect()
            ->route('events.index')
            ->with('status', 'Incident deleted. Display numbers have been refreshed.');
    }

    private function displayNumberFor(RescueEvent $event): int
    {
        $createdAt = $event->created_at;

        if ($createdAt === null) {
            return RescueEvent::query()
                ->where('id', '>', $event->id)
                ->count() + 1;
        }

        return RescueEvent::query()
            ->where(function ($query) use ($event, $createdAt) {
                $query->where('created_at', '>', $createdAt)
                    ->orWhere(function ($query) use ($event, $createdAt) {
                        $query->where('created_at', $createdAt)
                            ->where('id', '>', $event->id);
                    });
            })
            ->count() + 1;
    }

    private function deleteEventImage(?string $path): void
    {
        if (! is_string($path) || $path === '') {
            return;
        }

        $normalized = '/' . ltrim($path, '/');
        if (! str_starts_with($normalized, '/rescue-events/')) {
            return;
        }

        $fullPath = public_path(ltrim($normalized, '/'));
        if (File::isFile($fullPath)) {
            File::delete($fullPath);
        }
    }
}
