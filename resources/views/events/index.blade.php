@extends('layouts.app')

@section('content')
    @php
        $pageEvents = $events->getCollection();

        $confidencePercent = function ($event) {
            if ($event->conf === null) {
                return null;
            }

            $value = (float) $event->conf;
            return $value <= 1 ? round($value * 100) : round($value);
        };

        $statusLabel = fn ($status) => match ($status) {
            'Rescue Needed' => 'Rescue Required',
            'Suspected Injury' => 'Suspected Casualty',
            'Detected Injury' => 'Casualty Detected',
            'Posture Unclear', 'Pose Unclear' => 'Posture Unclear',
            'OK' => 'No Abnormality Detected',
            default => $status ?: 'Unknown Status',
        };

        $typeLabel = fn ($type) => strtoupper((string) $type) === 'RESCUE' ? 'Rescue Incident' : ($type ?: 'Incident');

        $reviewLabel = fn ($review) => match ($review ?: 'pending') {
            'pending' => 'Pending Review',
            'reviewed' => 'Confirmed',
            'dismissed' => 'Dismissed',
            default => $review,
        };

        $trackLabel = function ($event) {
            $tid = trim((string) $event->tid);
            if ($tid === '') {
                return 'Unassigned';
            }

            $tid = trim($tid, " \t\n\r\0\x0B()");
            $tid = preg_replace('/\s*,\s*/', '-', $tid);
            $tid = preg_replace('/\s+/', '-', $tid);

            return 'Target #' . $tid;
        };

        $riskLevel = function ($event) use ($confidencePercent) {
            $confidence = $confidencePercent($event);
            $status = (string) $event->status;

            if ($status === 'Rescue Needed' || ($confidence !== null && $confidence >= 70)) {
                return 'high';
            }

            if (str_contains($status, 'Suspected') || ($confidence !== null && $confidence >= 50)) {
                return 'medium';
            }

            return 'low';
        };

        $riskLabel = fn ($risk) => match ($risk) {
            'high' => 'High Risk',
            'medium' => 'Medium Risk',
            default => 'Low Risk',
        };

        $highRiskCount = $pageEvents->filter(fn ($event) => $riskLevel($event) === 'high')->count();
        $mediumRiskCount = $pageEvents->filter(fn ($event) => $riskLevel($event) === 'medium')->count();
        $pendingCount = $pageEvents->filter(fn ($event) => ($event->review_status ?: 'pending') === 'pending')->count();
        $reviewedCount = $pageEvents->filter(fn ($event) => ($event->review_status ?: 'pending') === 'reviewed')->count();
        $dismissedCount = $pageEvents->filter(fn ($event) => ($event->review_status ?: 'pending') === 'dismissed')->count();
    @endphp

    <style>
        .flash-message {
            border: 1px solid rgba(16, 185, 129, .35);
            border-radius: 12px;
            background: rgba(16, 185, 129, .12);
            color: #bbf7d0;
            padding: 11px 13px;
            margin-bottom: 14px;
        }

        .event-hero {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: 16px;
            align-items: end;
            margin-bottom: 18px;
        }

        .event-help {
            border: 1px solid var(--accent-border);
            border-radius: 14px;
            background: rgba(33, 25, 35, .72);
            padding: 12px 14px;
            color: #cbd5e1;
            line-height: 1.55;
            margin-bottom: 16px;
        }

        .event-help strong { color: #f8fafc; }

        .event-toolbar {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: center;
            margin-bottom: 14px;
            flex-wrap: wrap;
        }

        .event-search {
            min-width: min(100%, 320px);
            flex: 1;
            border: 1px solid #334155;
            border-radius: 12px;
            background: #020617;
            color: #fff;
            padding: 11px 12px;
            outline: none;
        }

        .event-filters,
        .review-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .filter-btn,
        .event-action {
            border: 1px solid rgba(148, 163, 184, .28);
            border-radius: 999px;
            background: rgba(15, 23, 42, .82);
            color: #dbeafe;
            cursor: pointer;
            font-weight: 800;
            padding: 9px 12px;
            text-decoration: none;
        }

        .filter-btn.active {
            background: var(--accent-gradient);
            border-color: var(--accent);
            color: var(--accent-ink);
        }

        .event-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-color: var(--accent-border);
            color: var(--accent);
        }

        .event-risk,
        .review-pill {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            border-radius: 999px;
            padding: 5px 9px;
            font-size: 12px;
            font-weight: 900;
        }

        .event-risk::before {
            content: "";
            width: 7px;
            height: 7px;
            border-radius: 999px;
        }

        .event-risk.high { background: rgba(239, 68, 68, .16); color: #fecaca; }
        .event-risk.high::before { background: #ef4444; }
        .event-risk.medium { background: rgba(252, 200, 155, .16); color: #f7ddca; }
        .event-risk.medium::before { background: var(--accent); }
        .event-risk.low { background: rgba(125, 211, 252, .12); color: #bae6fd; }
        .event-risk.low::before { background: #7dd3fc; }

        .review-pill { background: rgba(252, 200, 155, .14); color: #f7ddca; }
        .review-pill.reviewed { background: rgba(16, 185, 129, .14); color: #bbf7d0; }
        .review-pill.dismissed { background: rgba(148, 163, 184, .14); color: #cbd5e1; }

        .confidence-meter { min-width: 150px; }
        .confidence-meter strong { display: block; margin-bottom: 5px; }

        .confidence-bar {
            width: 100%;
            height: 7px;
            border-radius: 999px;
            background: #1e293b;
            overflow: hidden;
        }

        .confidence-bar span {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, var(--accent), #2dd4bf);
        }

        .event-table-wrap tbody tr { cursor: pointer; }
        .event-table-wrap tbody tr:hover { background: rgba(252, 200, 155, .12); }
        .review-actions { margin-top: 8px; }
        .review-actions form { margin: 0; }

        .review-action-btn {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 10px;
            background: rgba(15, 23, 42, .82);
            color: #dbeafe;
            cursor: pointer;
            font-size: 14px;
            font-weight: 800;
            min-height: 40px;
            padding: 9px 13px;
        }

        .review-action-btn.confirm { border-color: rgba(16, 185, 129, .48); color: #bbf7d0; }
        .review-action-btn.dismiss { border-color: rgba(148, 163, 184, .42); color: #cbd5e1; }
        .review-action-btn.pending { border-color: var(--accent-border); color: #f7ddca; }
        .review-action-btn.delete { border-color: rgba(239, 68, 68, .56); color: #fecaca; }

        .event-hidden { display: none !important; }
        .event-empty-filter {
            display: none;
            color: #94a3b8;
            padding: 18px 0 4px;
        }

        .track-help { color: #94a3b8; font-size: 12px; }
        .track-code {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            color: #f8fafc;
            font-weight: 900;
            white-space: nowrap;
        }

        @media (max-width: 720px) {
            .event-hero { grid-template-columns: 1fr; }
            .event-toolbar {
                align-items: stretch;
                flex-direction: column;
            }
            .event-filters {
                display: grid;
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }
    </style>

    <div class="event-hero">
        <div>
            <h1 class="page-title">Incident Review</h1>
            <p class="page-subtitle">Review detection results, confidence, target identifiers, visual evidence, and operator disposition.</p>
        </div>
        <a class="event-action" href="{{ route('dashboard') }}">Back to Command Center</a>
    </div>

    @if(session('status'))
        <div class="flash-message">{{ session('status') }}</div>
    @endif

    <div class="event-summary-grid">
        <div class="summary-tile">
            <span>Total Incidents</span>
            <strong>{{ $events->total() }}</strong>
        </div>
        <div class="summary-tile">
            <span>High-Risk Incidents</span>
            <strong>{{ $highRiskCount }}</strong>
        </div>
        <div class="summary-tile">
            <span>Pending Review</span>
            <strong>{{ $pendingCount }}</strong>
        </div>
    </div>

    <div class="event-help">
        <strong>Target ID</strong> is a temporary tracking identifier assigned to a suspected casualty within the same video sequence; it is not a GPS coordinate.
        <strong>Confidence</strong> indicates model certainty and must still be verified by an operator.
    </div>

    <div class="panel">
        <div class="panel-head">
            <h2>Incident List</h2>
            <span style="color:#94a3b8; font-size:13px;">{{ $events->count() }} on this page</span>
        </div>

        <div class="panel-body">
            <div class="event-toolbar">
                <input id="eventSearch" class="event-search" type="search" placeholder="Search incident, status, target ID, or timestamp">
                <div class="event-filters" aria-label="Incident filters">
                    <button class="filter-btn active" type="button" data-filter="all">All {{ $events->count() }}</button>
                    <button class="filter-btn" type="button" data-filter="high">High Risk {{ $highRiskCount }}</button>
                    <button class="filter-btn" type="button" data-filter="medium">Medium Risk {{ $mediumRiskCount }}</button>
                    <button class="filter-btn" type="button" data-filter="pending">Pending {{ $pendingCount }}</button>
                    <button class="filter-btn" type="button" data-filter="reviewed">Confirmed {{ $reviewedCount }}</button>
                    <button class="filter-btn" type="button" data-filter="dismissed">Dismissed {{ $dismissedCount }}</button>
                </div>
            </div>

            <div class="event-card-list">
                @forelse($events as $event)
                    @php
                        $displayNumber = $events->firstItem() + $loop->index;
                        $risk = $riskLevel($event);
                        $confidence = $confidencePercent($event);
                        $searchText = strtolower(trim(implode(' ', [
                            $displayNumber,
                            'record ' . $event->id,
                            $event->event_type,
                            $statusLabel($event->status),
                            $event->status,
                            $trackLabel($event),
                            optional($event->event_time)->format('Y-m-d H:i:s'),
                            $reviewLabel($event->review_status),
                            $event->message,
                        ])));
                    @endphp
                    <article class="event-card" data-event-row data-risk="{{ $risk }}" data-review="{{ $event->review_status ?: 'pending' }}" data-search="{{ e($searchText) }}" data-url="{{ route('events.show', $event) }}">
                        <div class="event-card-main">
                            <div class="event-card-kicker">Incident #{{ $displayNumber }} / {{ $typeLabel($event->event_type) }}</div>
                            <h3>{{ $statusLabel($event->status) }}</h3>
                            <span class="event-risk {{ $risk }}">{{ $riskLabel($risk) }}</span>
                            <p>{{ $event->message ?: 'No incident description is available.' }}</p>
                        </div>
                        <div class="event-card-meta">
                            <span>Confidence {{ $confidence !== null ? $confidence . '%' : '--' }}</span>
                            <span class="track-code">{{ $trackLabel($event) }}</span>
                            <span>{{ optional($event->event_time)->format('Y-m-d H:i:s') ?? '--' }}</span>
                            <span>Disposition {{ $reviewLabel($event->review_status) }}</span>
                        </div>
                        <div class="review-actions">
                            <form method="POST" action="{{ route('events.review.update', $event) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="review_status" value="reviewed">
                                <button class="review-action-btn confirm" type="submit">Confirm</button>
                            </form>
                            <form method="POST" action="{{ route('events.review.update', $event) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="review_status" value="dismissed">
                                <button class="review-action-btn dismiss" type="submit">Dismiss</button>
                            </form>
                            <form method="POST" action="{{ route('events.review.update', $event) }}">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="review_status" value="pending">
                                <button class="review-action-btn pending" type="submit">Mark Pending</button>
                            </form>
                            <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete Incident #{{ $displayNumber }} permanently? This action cannot be undone.');">
                                @csrf
                                @method('DELETE')
                                <button class="review-action-btn delete" type="submit">Delete</button>
                            </form>
                        </div>
                        <a class="event-card-link" href="{{ route('events.show', $event) }}">Details</a>
                    </article>
                @empty
                    <div class="empty-state">No rescue incidents are available.</div>
                @endforelse
            </div>

            <div class="table-wrap event-table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Incident</th>
                        <th>Risk</th>
                        <th>Detection Status</th>
                        <th>Confidence</th>
                        <th>Target ID</th>
                        <th>Timestamp</th>
                        <th>Disposition</th>
                        <th></th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse($events as $event)
                        @php
                            $displayNumber = $events->firstItem() + $loop->index;
                            $risk = $riskLevel($event);
                            $confidence = $confidencePercent($event);
                            $searchText = strtolower(trim(implode(' ', [
                                $displayNumber,
                                'record ' . $event->id,
                                $event->event_type,
                                $statusLabel($event->status),
                                $event->status,
                                $trackLabel($event),
                                optional($event->event_time)->format('Y-m-d H:i:s'),
                                $reviewLabel($event->review_status),
                                $event->message,
                            ])));
                        @endphp
                        <tr data-event-row data-risk="{{ $risk }}" data-review="{{ $event->review_status ?: 'pending' }}" data-search="{{ e($searchText) }}" data-url="{{ route('events.show', $event) }}">
                            <td>
                                <strong>#{{ $displayNumber }}</strong>
                                <div style="color:#94a3b8; font-size:12px;">{{ $typeLabel($event->event_type) }}</div>
                            </td>
                            <td><span class="event-risk {{ $risk }}">{{ $riskLabel($risk) }}</span></td>
                            <td>{{ $statusLabel($event->status) }}</td>
                            <td>
                                <div class="confidence-meter">
                                    <strong>{{ $confidence !== null ? $confidence . '%' : '--' }}</strong>
                                    <div class="confidence-bar"><span style="width: {{ $confidence !== null ? min(100, max(0, $confidence)) : 0 }}%;"></span></div>
                                </div>
                            </td>
                            <td>
                                <span class="track-code">{{ $trackLabel($event) }}</span>
                                <div class="track-help">Temporary identifier for the same suspected target</div>
                            </td>
                            <td>{{ optional($event->event_time)->format('Y-m-d H:i:s') ?? '--' }}</td>
                            <td>
                                <span class="review-pill {{ $event->review_status }}">{{ $reviewLabel($event->review_status) }}</span>
                                <div class="review-actions">
                                    <form method="POST" action="{{ route('events.review.update', $event) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="review_status" value="reviewed">
                                        <button class="review-action-btn confirm" type="submit">Confirm</button>
                                    </form>
                                    <form method="POST" action="{{ route('events.review.update', $event) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="review_status" value="dismissed">
                                        <button class="review-action-btn dismiss" type="submit">Dismiss</button>
                                    </form>
                                    <form method="POST" action="{{ route('events.review.update', $event) }}">
                                        @csrf
                                        @method('PATCH')
                                        <input type="hidden" name="review_status" value="pending">
                                        <button class="review-action-btn pending" type="submit">Pending</button>
                                    </form>
                                    <form method="POST" action="{{ route('events.destroy', $event) }}" onsubmit="return confirm('Delete Incident #{{ $displayNumber }} permanently? This action cannot be undone.');">
                                        @csrf
                                        @method('DELETE')
                                        <button class="review-action-btn delete" type="submit">Delete</button>
                                    </form>
                                </div>
                            </td>
                            <td><a class="link" href="{{ route('events.show', $event) }}">Details</a></td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8">No rescue incidents are available.</td>
                        </tr>
                    @endforelse
                    </tbody>
                </table>
            </div>

            <div id="eventEmptyFilter" class="event-empty-filter">No incidents match the current filter.</div>

            <div style="margin-top: 16px;">
                {{ $events->links() }}
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const search = document.getElementById('eventSearch');
            const buttons = Array.from(document.querySelectorAll('.filter-btn'));
            const rows = Array.from(document.querySelectorAll('[data-event-row]'));
            const empty = document.getElementById('eventEmptyFilter');
            let activeFilter = 'all';

            function applyEventFilters() {
                const keyword = (search?.value || '').trim().toLowerCase();
                let visible = 0;

                rows.forEach((row) => {
                    const risk = row.dataset.risk || 'low';
                    const review = row.dataset.review || 'pending';
                    const text = row.dataset.search || '';
                    const matchesFilter = activeFilter === 'all'
                        || activeFilter === risk
                        || (activeFilter === 'pending' && review === 'pending')
                        || (activeFilter === 'reviewed' && review === 'reviewed')
                        || (activeFilter === 'dismissed' && review === 'dismissed');
                    const matchesKeyword = keyword === '' || text.includes(keyword);
                    const show = matchesFilter && matchesKeyword;

                    row.classList.toggle('event-hidden', !show);
                    if (show) visible += 1;
                });

                if (empty) {
                    empty.style.display = visible === 0 && rows.length > 0 ? 'block' : 'none';
                }
            }

            buttons.forEach((button) => {
                button.addEventListener('click', () => {
                    activeFilter = button.dataset.filter || 'all';
                    buttons.forEach((item) => item.classList.toggle('active', item === button));
                    applyEventFilters();
                });
            });

            search?.addEventListener('input', applyEventFilters);

            rows.forEach((row) => {
                row.addEventListener('click', (event) => {
                    if (event.target.closest('a, button, form, input, textarea')) return;
                    const url = row.dataset.url;
                    if (url) window.location.href = url;
                });
            });
        });
    </script>
@endsection
