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

        try {
            $drone = $this->droneService->getStatus();
        } catch (Throwable $e) {
            $droneError = $e->getMessage();
        }

        return response()->json([
            'ok' => true,
            'model' => config('services.ollama.model', 'gemma3:4b'),
            'ollama_base' => config('services.ollama.base_url', 'http://127.0.0.1:11434'),
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
            'message' => 'Test events cleared',
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
        $question = trim($data['question'] ?? '請摘要目前救援事件。');

        if ($question === '') {
            $question = $mode === 'web' ? '請查詢最新資料並整理重點。' : '請摘要目前救援事件。';
        }

        if ($mode === 'web') {
            $sources = $this->webSearch($question);

            if (count($sources) === 0) {
                return response()->json([
                    'ok' => true,
                    'mode' => 'web',
                    'model' => config('services.ollama.model', 'gemma3:4b'),
                    'summary' => "目前沒有取得可用的網路來源。\n\n請確認這台電腦可以連上網路，或把問題改得更具體再查一次。",
                    'events' => $events,
                    'sources' => [],
                ]);
            }

            return $this->askLocalModel(
                $this->buildWebPrompt($question, $sources),
                $events,
                'web',
                $sources
            );
        }

        if ($mode === 'general') {
            return $this->askLocalModel($this->buildGeneralPrompt($question), $events, 'general');
        }

        if (! $this->isRescueEventQuestion($question)) {
            return response()->json([
                'ok' => true,
                'model' => config('services.ollama.model', 'gemma3:4b'),
                'summary' => "目前 MCP AI 工具中心主要連接了救援事件、無人機狀態與本地摘要工具。\n\n你的問題看起來不是救援事件分析。如果要查網路資料，請切換到「網路查詢」。",
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
                'event_type' => $event->event_type,
                'status' => $event->status,
                'conf' => $event->conf,
                'message' => $event->message,
                'event_time' => optional($event->event_time)->format('Y-m-d H:i:s'),
                'review_status' => $event->review_status,
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

    private function buildWebPrompt(string $question, array $sources): string
    {
        $sourceText = collect(array_slice($sources, 0, self::WEB_SOURCE_LIMIT))
            ->map(function (array $source) {
                $content = trim($source['content'] ?: $source['snippet']);
                $content = mb_substr($content, 0, self::WEB_EXCERPT_CHARS);

                return "[{$source['id']}] {$source['title']}\nURL: {$source['url']}\n摘要: {$source['snippet']}\n內容摘錄: {$content}";
            })
            ->implode("\n\n");

        return <<<PROMPT
你是本地端 AI 助手。請只根據下列網路來源回答，不要假裝知道來源沒有提供的資訊。

使用者問題：
{$question}

網路來源：
{$sourceText}

回答規則：
1. 使用繁體中文。
2. 先給簡短結論，再列重點。
3. 重要句子後面加上來源編號，例如 [1]、[2]。
4. 最後加入「來源整理」，逐條列出來源標題與網址。
5. 如果來源不足，請明確說明不足，不要自行編造。
PROMPT;
    }

    private function buildRescuePrompt(string $question, array $events): string
    {
        $payload = json_encode($events, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        return <<<PROMPT
你是智慧救援無人機指揮中心的本地 AI 助手。請根據救援事件 JSON 回答，重點放在現場風險、優先順序與下一步行動。

使用者問題：
{$question}

救援事件 JSON：
{$payload}

請用繁體中文回答：
1. 重點摘要
2. 高風險或需要注意的事件
3. 建議下一步
PROMPT;
    }

    private function buildGeneralPrompt(string $question): string
    {
        return <<<PROMPT
你是本地端繁體中文 AI 助手。請用清楚、簡短、可執行的方式回答。

限制：
- 你目前不能保證知道即時外部資料。
- 如果問題需要最新資料，請提醒使用者切換到「網路查詢」。

使用者問題：
{$question}
PROMPT;
    }

    private function isRescueEventQuestion(string $question): bool
    {
        $keywords = [
            '救援',
            '事件',
            '無人機',
            '偵測',
            '受傷',
            '風險',
            '狀態',
            'rescue',
            'event',
            'drone',
            'summary',
            'risk',
            'status',
        ];

        $lowered = strtolower($question);

        foreach ($keywords as $keyword) {
            if (str_contains($lowered, strtolower($keyword))) {
                return true;
            }
        }

        return false;
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
