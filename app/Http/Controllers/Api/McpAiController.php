<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\RescueEvent;
use App\Services\DroneService;
use DOMDocument;
use DOMXPath;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Throwable;

class McpAiController extends Controller
{
    private const WEB_SOURCE_LIMIT = 3;
    private const WEB_EXCERPT_CHARS = 750;
    private const OLLAMA_TIMEOUT_SECONDS = 240;

    public function __construct(private DroneService $droneService) {}

    public function status(): JsonResponse
    {
        $drone = null;
        $droneError = null;
        $latestEventTime = RescueEvent::query()->latest('event_time')->value('event_time');

        try {
            $drone = $this->droneService->getStatus();
        } catch (Throwable $e) {
            $droneError = $e->getMessage();
        }

        return response()->json([
            'ok' => true,
            'model' => config('services.ollama.model', 'gemma3:4b'),
            'ollama_base' => config('services.ollama.base_url', 'http://127.0.0.1:11434'),
            'db' => [
                'connection' => config('database.default'),
                'database' => config('database.connections.' . config('database.default') . '.database'),
                'event_count' => RescueEvent::query()->count(),
                'latest_event_time' => $latestEventTime ? Carbon::parse($latestEventTime)->format('Y-m-d H:i:s') : null,
            ],
            'tools' => [
                'get_drone_status',
                'get_recent_events',
                'get_event_detail',
                'summarize_recent_events',
                'local_model_chat',
                'local_web_search',
                'fetch_web_page',
                'answer_with_sources',
            ],
            'drone' => $drone,
            'drone_error' => $droneError,
        ]);
    }

    public function recentEvents(Request $request): JsonResponse
    {
        $limit = max(1, min((int) $request->query('limit', 10), 50));
        $since = $this->parseSince($request->query('since'));

        return response()->json([
            'ok' => true,
            'events' => $this->events($limit, $since),
            'since' => $since?->toIso8601String(),
        ]);
    }

    public function clearEvents(): JsonResponse
    {
        $deleted = RescueEvent::query()->delete();

        return response()->json([
            'ok' => true,
            'message' => 'Test incidents cleared',
            'deleted' => $deleted,
        ]);
    }

    public function summarize(Request $request): JsonResponse
    {
        $data = $request->validate([
            'limit' => 'nullable|integer|min:1|max:50',
            'mode' => 'nullable|string|in:rescue,general,web',
            'question' => 'nullable|string|max:1000',
            'since' => 'nullable|date',
        ]);

        $mode = $data['mode'] ?? 'rescue';
        $limit = $data['limit'] ?? 10;
        $since = isset($data['since']) ? $this->parseSince($data['since']) : null;
        $events = $this->events($limit, $since);
        $question = trim($data['question'] ?? 'Summarize the current rescue incidents.');

        if ($question === '') {
            $question = $mode === 'web'
                ? 'Search current sources and summarize the key points.'
                : 'Summarize the current rescue incidents.';
        }

        if ($mode === 'web') {
            $sources = $this->webSearch($question);

            if (count($sources) === 0) {
                return response()->json([
                    'ok' => true,
                    'mode' => 'web',
                    'model' => config('services.ollama.model', 'gemma3:4b'),
                    'summary' => "No usable web sources were retrieved.\n\nCheck network connectivity or try a more specific query.",
                    'events' => $events,
                    'sources' => [],
                ]);
            }

            return $this->askLocalModel($this->buildWebPrompt($question, $sources), $events, 'web', $sources);
        }

        if ($mode === 'general') {
            return $this->askLocalModel($this->buildGeneralPrompt($question), $events, 'general');
        }

        if (! $this->isRescueEventQuestion($question)) {
            return response()->json([
                'ok' => true,
                'model' => config('services.ollama.model', 'gemma3:4b'),
                'summary' => "The MCP AI center is currently connected to rescue incidents, drone status, and local analysis tools.\n\nYour question does not appear to be about incident analysis. Switch to Web Research for public-source queries.",
                'events' => $events,
                'mode' => 'rescue',
                'sources' => [],
            ]);
        }

        return $this->askLocalModel($this->buildRescuePrompt($question, $events), $events, 'rescue');
    }

    private function askLocalModel(string $prompt, array $events, string $mode, array $sources = []): JsonResponse
    {
        try {
            $response = Http::timeout(self::OLLAMA_TIMEOUT_SECONDS)
                ->connectTimeout(8)
                ->post(
                    rtrim(config('services.ollama.base_url', 'http://127.0.0.1:11434'), '/') . '/api/generate',
                    [
                        'model' => config('services.ollama.model', 'gemma3:4b'),
                        'prompt' => $prompt,
                        'stream' => false,
                        'keep_alive' => '10m',
                        'options' => [
                            'temperature' => $mode === 'web' ? 0.2 : 0.3,
                            'num_ctx' => 4096,
                            'num_predict' => $mode === 'web' ? 420 : 800,
                        ],
                    ]
                );

            if (! $response->successful()) {
                return response()->json([
                    'ok' => false,
                    'error' => 'OLLAMA_REQUEST_FAILED',
                    'message' => $response->body(),
                    'sources' => $sources,
                ], 502);
            }

            return response()->json([
                'ok' => true,
                'mode' => $mode,
                'model' => config('services.ollama.model', 'gemma3:4b'),
                'summary' => $response->json('response'),
                'events' => $events,
                'sources' => $sources,
            ]);
        } catch (Throwable $e) {
            return response()->json([
                'ok' => false,
                'error' => 'OLLAMA_UNAVAILABLE',
                'message' => $e->getMessage(),
                'sources' => $sources,
            ], 503);
        }
    }

    private function events(int $limit, ?Carbon $since = null): array
    {
        $query = RescueEvent::query();

        if ($since !== null) {
            $query->where('event_time', '>=', $since);
        }

        return $query
            ->latest('event_time')
            ->latest('id')
            ->limit($limit)
            ->get()
            ->map(fn (RescueEvent $event) => [
                'id' => $event->id,
                'display_number' => $this->displayNumberFor($event),
                'event_type' => $event->event_type,
                'status' => $event->status,
                'status_label' => $this->statusLabel($event->status),
                'tid' => $event->tid,
                'track_label' => $this->trackLabel($event->tid),
                'conf' => $event->conf,
                'confidence_label' => $this->confidenceLabel($event->conf),
                'message' => $event->message,
                'event_time' => optional($event->event_time)->format('Y-m-d H:i:s'),
                'review_status' => $event->review_status ?: 'pending',
                'review_label' => $this->reviewLabel($event->review_status),
                'detail_url' => route('events.show', $event),
                'original_image_path' => $event->original_image_path,
                'annotated_image_path' => $event->annotated_image_path,
            ])
            ->all();
    }

    private function parseSince(mixed $value): ?Carbon
    {
        if (! is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (Throwable) {
            return null;
        }
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

    private function buildWebPrompt(string $question, array $sources): string
    {
        $sourceText = collect(array_slice($sources, 0, self::WEB_SOURCE_LIMIT))
            ->map(function (array $source) {
                $content = trim($source['content'] ?: $source['snippet']);
                $content = mb_substr($content, 0, self::WEB_EXCERPT_CHARS);

                return "[{$source['id']}] {$source['title']}\nURL: {$source['url']}\nSnippet: {$source['snippet']}\nExcerpt: {$content}";
            })
            ->implode("\n\n");

        return <<<PROMPT
You are Sunny, the local AI assistant for a rescue-drone command center. Answer in professional English using only traceable source material when discussing external information.

User question:
{$question}

Web sources:
{$sourceText}

Respond with concise bullets or short paragraphs. Cite important claims with source numbers such as [1] or [2]. If the sources are insufficient, state the limitation explicitly.
PROMPT;
    }

    private function buildRescuePrompt(string $question, array $events): string
    {
        $payload = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
You are Sunny, the local AI assistant for an intelligent rescue-drone command center. Analyze the rescue-incident JSON and focus on operational risk, response priority, and next actions.

User question:
{$question}

Rescue incident JSON:
{$payload}

Answer in professional English and include:
1. Incident overview
2. High-risk or attention-required incident IDs
3. Target ID, confidence, and operator disposition
4. Recommended next actions for the operator
5. If an incident has detail_url, remind the operator that the detail page contains original and AI-annotated visual evidence
PROMPT;
    }

    private function buildGeneralPrompt(string $question): string
    {
        return <<<PROMPT
You are Sunny, the local AI assistant for an intelligent rescue-drone platform. Answer in professional English, clearly and concisely.

User question:
{$question}
PROMPT;
    }

    private function isRescueEventQuestion(string $question): bool
    {
        $keywords = [
            'rescue',
            'incident',
            'casualty',
            'detection',
            'posture',
            'risk',
            'confidence',
            'review',
            'target',
            'drone',
            'summary',
            'status',
            'operator',
            'evidence',
        ];

        $lowered = strtolower($question);

        foreach ($keywords as $keyword) {
            if (str_contains($lowered, $keyword)) {
                return true;
            }
        }

        return false;
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'Rescue Needed' => 'Rescue Required',
            'Suspected Injury' => 'Suspected Casualty',
            'Detected Injury' => 'Casualty Detected',
            'Posture Unclear', 'Pose Unclear' => 'Posture Unclear',
            'OK' => 'No Abnormality Detected',
            default => $status ?: 'Unknown Status',
        };
    }

    private function reviewLabel(?string $review): string
    {
        return match ($review ?: 'pending') {
            'pending' => 'Pending Review',
            'reviewed' => 'Confirmed',
            'dismissed' => 'Dismissed',
            default => $review ?: 'Pending Review',
        };
    }

    private function confidenceLabel(mixed $confidence): string
    {
        if ($confidence === null || $confidence === '') {
            return '--';
        }

        $value = (float) $confidence;
        return round($value <= 1 ? $value * 100 : $value) . '%';
    }

    private function trackLabel(mixed $tid): string
    {
        $value = trim((string) $tid);
        if ($value === '') {
            return 'Unassigned';
        }

        $value = trim($value, " \t\n\r\0\x0B()");
        $value = preg_replace('/\s*,\s*/', '-', $value) ?? $value;
        $value = preg_replace('/\s+/', '-', $value) ?? $value;

        return 'Target #' . $value;
    }

    private function webSearch(string $query, int $limit = self::WEB_SOURCE_LIMIT): array
    {
        try {
            $response = Http::timeout(12)
                ->connectTimeout(6)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 RescueDroneLocalSearch/1.0',
                ])
                ->get('https://duckduckgo.com/html/', [
                    'q' => $query,
                    'kl' => 'wt-wt',
                ]);

            if (! $response->successful()) {
                return [];
            }

            $results = $this->parseDuckDuckGoResults($response->body(), $limit);

            return array_map(function (array $source, int $index) {
                $content = $this->fetchPageExcerpt($source['url']);

                return [
                    'id' => $index + 1,
                    'title' => $source['title'],
                    'url' => $source['url'],
                    'snippet' => $source['snippet'],
                    'content' => $content,
                ];
            }, $results, array_keys($results));
        } catch (Throwable) {
            return [];
        }
    }

    private function parseDuckDuckGoResults(string $html, int $limit): array
    {
        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $nodes = $xpath->query("//a[contains(@class, 'result__a')]");
        $results = [];

        foreach ($nodes as $node) {
            if (count($results) >= $limit) {
                break;
            }

            $title = trim(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            $url = $this->normalizeDuckDuckGoUrl($node->getAttribute('href'));
            $resultNode = $node;

            while ($resultNode && $resultNode->parentNode && ! str_contains($this->nodeClass($resultNode), 'result')) {
                $resultNode = $resultNode->parentNode;
            }

            $snippet = '';
            if ($resultNode) {
                $snippetNode = $xpath->query(".//*[contains(@class, 'result__snippet')]", $resultNode)->item(0);
                if ($snippetNode) {
                    $snippet = trim(html_entity_decode($snippetNode->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                }
            }

            if ($title !== '' && $url !== '' && $this->isHttpUrl($url)) {
                $results[] = [
                    'title' => $this->squashWhitespace($title),
                    'url' => $url,
                    'snippet' => $this->squashWhitespace($snippet),
                ];
            }
        }

        return $results;
    }

    private function fetchPageExcerpt(string $url): string
    {
        if (! $this->isHttpUrl($url)) {
            return '';
        }

        try {
            $response = Http::timeout(8)
                ->connectTimeout(5)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 RescueDroneLocalSearch/1.0',
                    'Accept' => 'text/html,application/xhtml+xml',
                ])
                ->get($url);

            if (! $response->successful()) {
                return '';
            }

            $contentType = strtolower($response->header('Content-Type', ''));
            if ($contentType !== '' && ! str_contains($contentType, 'text/html')) {
                return '';
            }

            return $this->extractReadableText($response->body(), self::WEB_EXCERPT_CHARS);
        } catch (Throwable) {
            return '';
        }
    }

    private function extractReadableText(string $html, int $maxLength): string
    {
        $html = preg_replace('/<script\b[^>]*>.*?<\/script>/is', ' ', $html) ?? $html;
        $html = preg_replace('/<style\b[^>]*>.*?<\/style>/is', ' ', $html) ?? $html;

        $dom = new DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML($html);
        libxml_clear_errors();

        $xpath = new DOMXPath($dom);
        $paragraphs = [];

        foreach ($xpath->query('//article//p | //main//p | //p') as $node) {
            $text = $this->squashWhitespace(html_entity_decode($node->textContent, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if (mb_strlen($text) >= 40) {
                $paragraphs[] = $text;
            }
            if (mb_strlen(implode("\n", $paragraphs)) >= $maxLength) {
                break;
            }
        }

        $text = implode("\n", $paragraphs);

        if ($text === '') {
            $text = $this->squashWhitespace(strip_tags($html));
        }

        return mb_substr($text, 0, $maxLength);
    }

    private function normalizeDuckDuckGoUrl(string $url): string
    {
        $url = html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (str_starts_with($url, '//')) {
            $url = 'https:' . $url;
        }

        $parts = parse_url($url);
        if (isset($parts['query'])) {
            parse_str($parts['query'], $query);
            if (isset($query['uddg'])) {
                return urldecode($query['uddg']);
            }
        }

        return $url;
    }

    private function nodeClass($node): string
    {
        return method_exists($node, 'getAttribute') ? (string) $node->getAttribute('class') : '';
    }

    private function isHttpUrl(string $url): bool
    {
        return str_starts_with($url, 'http://') || str_starts_with($url, 'https://');
    }

    private function squashWhitespace(string $text): string
    {
        return trim(preg_replace('/\s+/u', ' ', $text) ?? $text);
    }
}
