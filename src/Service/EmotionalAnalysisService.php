<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmotionalAnalysisService
{
    private const GROQ_API_URL = 'https://api.groq.com/openai/v1/chat/completions';
    private const GROQ_MODEL   = 'llama3-8b-8192';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiProvider,
        private readonly string $aiApiKey,
        private readonly string $aiModel,
        private readonly string $groqApiKey = '',
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function analyzePostContent(string $content): EmotionAnalysisResult
    {
        $content = trim($content);
        if ($content === '') {
            return new EmotionAnalysisResult('neutral', 'low', false);
        }

        // Try Groq first (free & fast)
        $groqKey = trim($this->groqApiKey);
        if ($groqKey !== '') {
            try {
                return $this->analyzeWithGroq($content, $groqKey);
            } catch (\Throwable $e) {
                $this->logger?->warning('[EmotionalAnalysis] Groq failed, falling back to heuristics', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Try OpenAI if configured
        $openAiKey = trim($this->aiApiKey);
        if (strtolower(trim($this->aiProvider)) === 'openai' && $openAiKey !== '') {
            try {
                return $this->analyzeWithOpenAi($content, $openAiKey);
            } catch (\Throwable $e) {
                $this->logger?->warning('[EmotionalAnalysis] OpenAI failed, falling back to heuristics', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final fallback: heuristics
        $this->logger?->info('[EmotionalAnalysis] Using heuristics (no AI key configured)');
        return $this->analyzeWithHeuristics($content);
    }

    public function testOpenAIConnection(): EmotionAnalysisResult
    {
        $openAiKey = trim($this->aiApiKey);
        if ($openAiKey === '') {
            throw new \RuntimeException('AI_API_KEY is empty.');
        }

        return $this->analyzeWithOpenAi('I feel okay today and hopeful for tomorrow.', $openAiKey);
    }

    // ─── Groq (primary AI — free) ────────────────────────────────────────────

    private function analyzeWithGroq(string $content, string $apiKey): EmotionAnalysisResult
    {
        $this->logger?->info('[EmotionalAnalysis] Using Groq AI');

        $prompt = $this->buildPrompt($content);

        $response = $this->httpClient->request('POST', self::GROQ_API_URL, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => self::GROQ_MODEL,
                'messages'    => [
                    ['role' => 'system', 'content' => 'You are a mental health content classifier. You ONLY return valid compact JSON, no explanation, no markdown.'],
                    ['role' => 'user',   'content' => $prompt],
                ],
                'temperature' => 0,
                'max_tokens'  => 200,
            ],
            'timeout' => 15,
        ]);

        return $this->parseAiResponse($response);
    }

    // ─── OpenAI (secondary AI) ────────────────────────────────────────────────

    private function analyzeWithOpenAi(string $content, string $apiKey): EmotionAnalysisResult
    {
        $this->logger?->info('[EmotionalAnalysis] Using OpenAI');

        $model = trim($this->aiModel) ?: 'gpt-3.5-turbo';

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'           => $model,
                'messages'        => [
                    ['role' => 'system', 'content' => 'You are a mental health content classifier. You ONLY return valid compact JSON, no explanation, no markdown.'],
                    ['role' => 'user',   'content' => $this->buildPrompt($content)],
                ],
                'temperature'     => 0,
                'max_tokens'      => 200,
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 15,
        ]);

        return $this->parseAiResponse($response);
    }

    // ─── Shared helpers ───────────────────────────────────────────────────────

    private function buildPrompt(string $content): string
    {
        return <<<PROMPT
Analyze the following post and return ONLY a valid JSON object with these exact keys:

- "emotion": one of [happy, sad, neutral, stressed, anxious, angry, hopeful]
- "risk_level": one of [low, medium, high]
  * high   = explicit self-harm, suicide, wanting to die
  * medium = hopelessness, giving up, no reason to live
  * low    = everything else
- "is_sensitive": boolean (true if risk_level is medium or high)
- "matched_signals": array of short phrases that triggered the risk/emotion (empty array if none)

Post content:
"""
{$content}
"""

Return ONLY the JSON object, nothing else.
PROMPT;
    }

    private function parseAiResponse(\Symfony\Contracts\HttpClient\ResponseInterface $response): EmotionAnalysisResult
    {
        $payload = $response->toArray(false);
        $raw     = $payload['choices'][0]['message']['content'] ?? '{}';

        // Strip markdown code blocks if model wraps in ```json ... ```
        $raw = preg_replace('/^```(?:json)?\s*/i', '', trim($raw));
        $raw = preg_replace('/\s*```$/', '', $raw);

        $decoded = json_decode(trim($raw), true);

        if (!is_array($decoded)) {
            $this->logger?->error('[EmotionalAnalysis] Invalid JSON from AI', ['raw' => $raw]);
            throw new \RuntimeException('Invalid JSON from AI: ' . $raw);
        }

        $this->logger?->info('[EmotionalAnalysis] AI result', $decoded);

        return new EmotionAnalysisResult(
            emotion:        (string) ($decoded['emotion']          ?? 'neutral'),
            riskLevel:      (string) ($decoded['risk_level']       ?? 'low'),
            isSensitive:    (bool)   ($decoded['is_sensitive']     ?? false),
            matchedSignals: is_array($decoded['matched_signals'] ?? null) ? $decoded['matched_signals'] : [],
        );
    }

    // ─── Heuristic fallback (no API key) ─────────────────────────────────────

    private function analyzeWithHeuristics(string $content): EmotionAnalysisResult
    {
        $text = mb_strtolower($content);

        $emotionLexicon = [
            'happy'    => ['happy', 'great', 'joy', 'excited', 'grateful', 'motivation', 'wonderful', 'amazing'],
            'sad'      => ['sad', 'depressed', 'cry', 'empty', 'hopeless', 'miserable', 'lonely'],
            'stressed' => ['stressed', 'overwhelmed', 'burnout', 'pressure', 'exhausted', 'tired'],
            'anxious'  => ['anxious', 'anxiety', 'panic', 'worried', 'fear', 'nervous', 'dread'],
            'angry'    => ['angry', 'furious', 'hate', 'rage', 'annoyed', 'frustrated'],
            'hopeful'  => ['hope', 'recover', 'improve', 'better', 'healing', 'progress'],
        ];

        $riskLexicon = [
            'high' => [
                'suicide', 'suicidal', 'kill myself', 'killing myself',
                'self-harm', 'self harm', 'want to die', 'end my life',
                'end it all', 'take my life', 'hurt myself', 'harm myself',
                'i wanna die', 'wanna die', 'wish i was dead',
                'je veux mourir', 'envie de mourir', 'me suicider', 'je vais me tuer',
            ],
            'medium' => [
                'worthless', 'no reason to live', 'give up', 'hopeless',
                'no way out', "can't go on", 'cant go on', 'nothing matters',
            ],
        ];

        $emotion      = 'neutral';
        $emotionScore = 0;
        $matchedSignals = [];

        foreach ($emotionLexicon as $candidateEmotion => $keywords) {
            $score = 0;
            foreach ($keywords as $keyword) {
                if (str_contains($text, $keyword)) {
                    $score++;
                    $matchedSignals[] = $keyword;
                }
            }
            if ($score > $emotionScore) {
                $emotionScore = $score;
                $emotion      = $candidateEmotion;
            }
        }

        $riskLevel = 'low';
        foreach ($riskLexicon['high'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $matchedSignals[] = $keyword;
                $riskLevel = 'high';
            }
        }
        if ($riskLevel === 'low') {
            foreach ($riskLexicon['medium'] as $keyword) {
                if (str_contains($text, $keyword)) {
                    $matchedSignals[] = $keyword;
                    $riskLevel = 'medium';
                    break;
                }
            }
        }

        return new EmotionAnalysisResult(
            emotion:        $emotion,
            riskLevel:      $riskLevel,
            isSensitive:    $riskLevel !== 'low',
            matchedSignals: array_values(array_unique($matchedSignals))
        );
    }
}