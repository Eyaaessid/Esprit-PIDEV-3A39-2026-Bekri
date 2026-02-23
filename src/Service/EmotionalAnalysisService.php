<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class EmotionalAnalysisService
{
    private readonly string $aiApiKeyTrimmed;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $aiProvider,
        string $aiApiKey,
        private readonly string $aiModel,
        private readonly ?LoggerInterface $logger = null,
    ) {
        $this->aiApiKeyTrimmed = trim($aiApiKey ?? '');
    }

    public function analyzePostContent(string $content): EmotionAnalysisResult
    {
        $content = trim($content);
        if ($content === '') {
            return new EmotionAnalysisResult('neutral', 'low', false);
        }

        $provider = strtolower(trim($this->aiProvider ?? ''));
        if ($provider === 'openai' && $this->aiApiKeyTrimmed !== '') {
            try {
                return $this->analyzeWithOpenAi($content);
            } catch (\Throwable $e) {
                $this->logger?->warning('OpenAI emotional analysis failed, using heuristics', [
                    'message' => $e->getMessage(),
                    'exception' => $e,
                ]);
            }
        }

        return $this->analyzeWithHeuristics($content);
    }

    /**
     * Call OpenAI with a short test string. Throws on failure (use for debugging API key).
     */
    public function testOpenAIConnection(): EmotionAnalysisResult
    {
        if ($this->aiApiKeyTrimmed === '') {
            throw new \InvalidArgumentException('AI_API_KEY is empty. Set it in .env');
        }
        if (strtolower(trim($this->aiProvider ?? '')) !== 'openai') {
            throw new \InvalidArgumentException('AI_PROVIDER must be "openai" to test. Current: ' . ($this->aiProvider ?? 'empty'));
        }
        return $this->analyzeWithOpenAi('I feel a bit stressed today.');
    }

    private function analyzeWithOpenAi(string $content): EmotionAnalysisResult
    {
        $prompt = <<<PROMPT
You are an emotion and risk classifier.
Return ONLY valid JSON with keys:
emotion (happy|sad|neutral|stressed|anxious|angry|hopeful),
risk_level (low|medium|high),
is_sensitive (boolean),
matched_signals (array of short strings).
Text:
{$content}
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.openai.com/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->aiApiKeyTrimmed,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->aiModel,
                'messages' => [
                    ['role' => 'system', 'content' => 'You return compact JSON only.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0,
                'response_format' => ['type' => 'json_object'],
            ],
            'timeout' => 10,
        ]);

        $payload = $response->toArray(false);
        $raw = $payload['choices'][0]['message']['content'] ?? '{}';
        $decoded = json_decode((string) $raw, true);
        if (!is_array($decoded)) {
            throw new TransportException('Invalid AI JSON response.');
        }

        return new EmotionAnalysisResult(
            emotion: (string) ($decoded['emotion'] ?? 'neutral'),
            riskLevel: (string) ($decoded['risk_level'] ?? 'low'),
            isSensitive: (bool) ($decoded['is_sensitive'] ?? false),
            matchedSignals: is_array($decoded['matched_signals'] ?? null) ? $decoded['matched_signals'] : []
        );
    }

    private function analyzeWithHeuristics(string $content): EmotionAnalysisResult
    {
        $text = mb_strtolower($content);

        $emotionLexicon = [
            'happy' => ['happy', 'great', 'joy', 'excited', 'grateful', 'motivation'],
            'sad' => ['sad', 'depressed', 'cry', 'empty', 'hopeless'],
            'stressed' => ['stressed', 'overwhelmed', 'burnout', 'pressure', 'exhausted'],
            'anxious' => ['anxious', 'anxiety', 'panic', 'worried', 'fear'],
            'angry' => ['angry', 'furious', 'hate', 'rage', 'annoyed'],
            'hopeful' => ['hope', 'recover', 'improve', 'better', 'healing'],
        ];

        // High risk = email alert sent. Medium = no email (only high triggers alert).
        $riskLexicon = [
            'high' => [
                'suicide', 'suicidal',
                'kill myself', 'killing myself',
                'self-harm', 'self harm',
                'want to die', 'end my life', 'end it all', 'take my life',
                'hurt myself', 'harm myself',
                // Common English variants
                'i wanna die', 'wanna die', 'i want die', 'wish i was dead',
                // Common French variants
                'je veux mourir', 'envie de mourir', 'me suicider', 'je vais me tuer',
            ],
            'medium' => [
                'worthless', 'no reason to live', 'give up', 'toxic', 'violent',
                'hopeless', 'no way out', 'cant go on', "can't go on",
            ],
        ];

        $emotion = 'neutral';
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
                $emotion = $candidateEmotion;
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
            emotion: $emotion,
            riskLevel: $riskLevel,
            isSensitive: $riskLevel === 'high',
            matchedSignals: array_values(array_unique($matchedSignals))
        );
    }
}
