@extends('layouts.app')

@section('content')
    @php
        $confidence = null;
        if ($event->conf !== null) {
            $rawConfidence = (float) $event->conf;
            $confidence = $rawConfidence <= 1 ? round($rawConfidence * 100) : round($rawConfidence);
        }

        $statusLabel = match ($event->status) {
            'Rescue Needed' => 'Rescue Required',
            'Suspected Injury' => 'Suspected Casualty',
            'Detected Injury' => 'Casualty Detected',
            'Posture Unclear', 'Pose Unclear' => 'Posture Unclear',
            'OK' => 'No Abnormality Detected',
            default => $event->status ?: 'Unknown Status',
        };

        $reviewLabel = match ($event->review_status ?: 'pending') {
            'pending' => 'Pending Review',
            'reviewed' => 'Confirmed',
            'dismissed' => 'Dismissed',
            default => $event->review_status,
        };

        $typeLabel = strtoupper((string) $event->event_type) === 'RESCUE' ? 'Rescue Incident' : ($event->event_type ?? 'Incident');

        $trackId = trim((string) $event->tid);
        if ($trackId !== '') {
            $trackId = trim($trackId, " \t\n\r\0\x0B()");
            $trackId = preg_replace('/\s*,\s*/', '-', $trackId);
            $trackId = preg_replace('/\s+/', '-', $trackId);
        }
        $trackLabel = $trackId !== '' ? 'Target #' . $trackId : 'Unassigned';
    @endphp

    <style>
        .review-form {
            display: grid;
            gap: 10px;
            margin-top: 16px;
        }

        .review-form textarea {
            width: 100%;
            min-height: 88px;
            border: 1px solid var(--accent-border);
            border-radius: 12px;
            background: #0b070a;
            color: #fff7ed;
            padding: 10px 12px;
            resize: vertical;
        }

        .review-action-row {
            display: flex;
            gap: 12px;
            flex-wrap: wrap;
        }

        .review-action-row button {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 12px;
            background: rgba(33, 25, 35, .82);
            color: #f7ddca;
            cursor: pointer;
            font-weight: 800;
            font-size: 16px;
            min-height: 52px;
            padding: 14px 20px;
        }

        .review-action-row .confirm { border-color: rgba(16, 185, 129, .48); color: #bbf7d0; }
        .review-action-row .dismiss { border-color: rgba(148, 163, 184, .42); color: #cbd5e1; }
        .review-action-row .pending { border-color: var(--accent-border); color: #f7ddca; }

        .flash-message {
            border: 1px solid rgba(16, 185, 129, .35);
            border-radius: 12px;
            background: rgba(16, 185, 129, .12);
            color: #bbf7d0;
            padding: 11px 13px;
            margin-bottom: 14px;
        }

        .detail-action-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid var(--accent-border);
            border-radius: 12px;
            background: rgba(252, 200, 155, .16);
            color: var(--accent);
            font-weight: 900;
            padding: 11px 14px;
            text-decoration: none;
        }

        .detail-action-btn:hover {
            border-color: var(--accent);
            background: rgba(252, 200, 155, .28);
            color: #fff7ed;
        }

        .detail-delete-form {
            margin: 0;
        }

        .detail-delete-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 44px;
            border: 1px solid rgba(239, 68, 68, .56);
            border-radius: 12px;
            background: rgba(127, 29, 29, .32);
            color: #fecaca;
            cursor: pointer;
            font-weight: 900;
            padding: 11px 14px;
        }

        .detail-delete-btn:hover {
            border-color: rgba(252, 165, 165, .8);
            background: rgba(153, 27, 27, .48);
            color: #fff;
        }

        .track-code {
            color: #f8fafc;
            font-weight: 900;
            white-space: nowrap;
        }

        .evidence-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 14px;
            margin-bottom: 16px;
        }

        .evidence-frame {
            border: 1px solid #4a3946;
            border-radius: 12px;
            background: #0b070a;
            overflow: hidden;
        }

        .evidence-frame h3 {
            color: #f7ddca;
            font-size: 14px;
            font-weight: 900;
            padding: 10px 12px;
            border-bottom: 1px solid #4a3946;
        }

        .evidence-frame img {
            width: 100%;
            aspect-ratio: 4 / 3;
            object-fit: contain;
            background: #000;
        }

        .evidence-empty {
            border: 1px dashed rgba(148, 163, 184, .36);
            border-radius: 12px;
            color: #94a3b8;
            padding: 16px;
            margin-bottom: 16px;
        }

        @media (max-width: 860px) {
            .evidence-grid { grid-template-columns: 1fr; }
        }
    </style>

    <h1 class="page-title">Incident #{{ $displayNumber ?? $event->id }}</h1>
    <p class="page-subtitle">Review detection output, confidence, target ID, visual evidence, and operator disposition.</p>

    @if(session('status'))
        <div class="flash-message">{{ session('status') }}</div>
    @endif

    <div class="detail-actions">
        <a class="detail-action-btn" href="{{ route('events.index') }}">Back to Incidents</a>
        <a class="detail-action-btn" href="{{ route('dashboard') }}">Back to Command Center</a>
        <form class="detail-delete-form" method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete Incident #{{ $displayNumber ?? $event->id }} permanently? This action cannot be undone.');">
            @csrf
            @method('DELETE')
            <button class="detail-delete-btn" type="submit">Delete Incident</button>
        </form>
    </div>

    <div class="event-detail-grid">
        <div class="panel">
            <div class="panel-head">
                <h2>Incident Summary</h2>
            </div>

            <div class="panel-body">
                <dl class="detail-list">
                    <div>
                        <dt>Incident Type</dt>
                        <dd>{{ $typeLabel }}</dd>
                    </div>
                    <div>
                        <dt>Detection Status</dt>
                        <dd>{{ $statusLabel }}</dd>
                    </div>
                    <div>
                        <dt>Confidence</dt>
                        <dd>{{ $confidence !== null ? $confidence . '%' : '--' }}</dd>
                    </div>
                    <div>
                        <dt>Target ID</dt>
                        <dd><span class="track-code">{{ $trackLabel }}</span></dd>
                    </div>
                    <div>
                        <dt>Incident Time</dt>
                        <dd>{{ optional($event->event_time)->format('Y-m-d H:i:s') ?? '--' }}</dd>
                    </div>
                    <div>
                        <dt>Operator Disposition</dt>
                        <dd>{{ $reviewLabel }}</dd>
                    </div>
                </dl>

                <div class="detail-message">
                    <h3>Incident Narrative</h3>
                    <p>{{ $event->message ?: 'No incident description is available.' }}</p>
                </div>

                <form class="review-form" method="POST" action="{{ route('events.review.update', $event) }}">
                    @csrf
                    @method('PATCH')
                    <textarea name="review_note" placeholder="Record operator assessment, rationale, or handoff notes.">{{ old('review_note', $event->review_note) }}</textarea>
                    <div class="review-action-row">
                        <button class="confirm" type="submit" name="review_status" value="reviewed">Mark Confirmed</button>
                        <button class="dismiss" type="submit" name="review_status" value="dismissed">Mark Dismissed</button>
                        <button class="pending" type="submit" name="review_status" value="pending">Return to Pending</button>
                    </div>
                </form>
            </div>
        </div>

        <div class="panel">
            <div class="panel-head">
                <h2>Visual Evidence</h2>
            </div>

            <div class="panel-body">
                @if($event->original_image_path || $event->annotated_image_path)
                    <div class="evidence-grid">
                        <div class="evidence-frame">
                            <h3>Original Frame</h3>
                            @if($event->original_image_path)
                                <img src="{{ url($event->original_image_path) }}" alt="Original frame for incident #{{ $displayNumber ?? $event->id }}">
                            @endif
                        </div>
                        <div class="evidence-frame">
                            <h3>AI-Annotated Frame</h3>
                            @if($event->annotated_image_path)
                                <img src="{{ url($event->annotated_image_path) }}" alt="AI-annotated frame for incident #{{ $displayNumber ?? $event->id }}">
                            @endif
                        </div>
                    </div>
                @else
                    <div class="evidence-empty">
                        No visual evidence has been stored for this incident. New incidents will automatically store the original frame and AI-annotated frame.
                    </div>
                @endif

                <h3 class="payload-title">Bounding Box Data</h3>
                <pre class="payload-pre">{{ json_encode($event->bbox_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>

                <h3 class="payload-title">Diagnostic Payload</h3>
                <pre class="payload-pre">{{ json_encode($event->dbg_json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
            </div>
        </div>
    </div>
@endsection
