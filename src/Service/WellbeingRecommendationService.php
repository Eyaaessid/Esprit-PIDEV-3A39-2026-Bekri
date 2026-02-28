<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Generates personalized wellbeing objective recommendations using AI (Groq).
 * Falls back to rule-based recommendations if API is unavailable.
 */
class WellbeingRecommendationService
{
    private const VALID_TYPES = ['humeur', 'sommeil', 'activite', 'nutrition', 'hydratation', 'poids'];
    private const TYPE_ICONS = [
        'humeur' => 'bi-emoji-smile',
        'sommeil' => 'bi-moon-stars',
        'activite' => 'bi-bicycle',
        'nutrition' => 'bi-nutrition',
        'hydratation' => 'bi-droplet',
        'poids' => 'bi-speedometer2',
    ];

    public function __construct(
        private HttpClientInterface $httpClient,
        private string $groqApiKey,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * @return array<int, array{type: string, label: string, reason: string, icon: string}>
     */
    public function getRecommendations(int $scoreGlobal, string $profilType, string $prenom = ''): array
    {
        $key = trim($this->groqApiKey ?? '');
        if ($key === '') {
            $this->logger?->debug('WellbeingRecommendationService: GROQ_API_KEY empty, using fallback.');
            return $this->getFallbackRecommendations($profilType);
        }

        try {
            $recommendations = $this->callGroq($scoreGlobal, $profilType, $prenom);
            if ($recommendations !== []) {
                return $recommendations;
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('WellbeingRecommendationService: AI request failed, using fallback.', [
                'message' => $e->getMessage(),
            ]);
        }

        return $this->getFallbackRecommendations($profilType);
    }

    /**
     * @return array<int, array{type: string, label: string, reason: string, icon: string}>
     */
    private function callGroq(int $scoreGlobal, string $profilType, string $prenom): array
    {
        $name = $prenom !== '' ? " pour {$prenom}" : '';
        $prompt = <<<PROMPT
Tu es un coach bien-être. Un utilisateur vient de faire une évaluation bien-être avec:
- Score global: {$scoreGlobal}/100
- Profil: {$profilType}

Propose 3 ou 4 objectifs personnalisés{$name} pour l'aider à améliorer ou maintenir son bien-être.
Types d'objectifs possibles (utilise EXACTEMENT ces valeurs pour "type"): humeur, sommeil, activite, nutrition, hydratation, poids.

Réponds UNIQUEMENT avec un JSON valide, sans markdown, de la forme:
[{"type":"sommeil","label":"Sommeil","reason":"Une courte phrase en français expliquant pourquoi cet objectif."},{"type":"activite","label":"Activité physique","reason":"..."}]
- "label" doit être le nom du type en français (Humeur, Sommeil, Activité physique, Nutrition, Hydratation, Poids).
- "reason" une seule phrase courte et bienveillante en français.
- Entre 3 et 4 objets dans le tableau.
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu réponds uniquement par un tableau JSON valide, sans texte avant ou après.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.6,
                'max_tokens' => 600,
            ],
            'timeout' => 15,
        ]);

        $data = $response->toArray(false);
        $content = $data['choices'][0]['message']['content'] ?? '';
        $content = trim($content);
        $content = preg_replace('/^```\w*\s*|\s*```$/m', '', $content);
        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            return [];
        }

        $out = [];
        foreach ($decoded as $item) {
            if (!is_array($item)) {
                continue;
            }
            $type = isset($item['type']) && in_array($item['type'], self::VALID_TYPES, true)
                ? $item['type']
                : null;
            if ($type === null) {
                continue;
            }
            $label = isset($item['label']) ? (string) $item['label'] : $type;
            $reason = isset($item['reason']) ? (string) $item['reason'] : '';
            $out[] = [
                'type' => $type,
                'label' => $label,
                'reason' => $reason !== '' ? $reason : $this->getDefaultReason($type),
                'icon' => self::TYPE_ICONS[$type],
            ];
        }

        return array_slice($out, 0, 4);
    }

    private function getDefaultReason(string $type): string
    {
        return match ($type) {
            'humeur' => 'Stabiliser et améliorer votre état émotionnel.',
            'sommeil' => 'Améliorer la qualité de votre sommeil.',
            'activite' => 'Intégrer un mouvement régulier pour le corps et l\'esprit.',
            'nutrition' => 'Équilibrer votre alimentation.',
            'hydratation' => 'Maintenir une bonne hydratation.',
            'poids' => 'Suivre votre poids de façon saine.',
            default => 'Objectif personnalisé pour votre bien-être.',
        };
    }

    /**
     * @return array<int, array{type: string, label: string, reason: string, icon: string}>
     */
    private function getFallbackRecommendations(string $profilType): array
    {
        $all = [
            'humeur' => ['type' => 'humeur', 'label' => 'Humeur', 'reason' => 'Stabiliser et améliorer votre état émotionnel au quotidien.', 'icon' => 'bi-emoji-smile'],
            'sommeil' => ['type' => 'sommeil', 'label' => 'Sommeil', 'reason' => 'Améliorer la qualité et la régularité de votre sommeil.', 'icon' => 'bi-moon-stars'],
            'activite' => ['type' => 'activite', 'label' => 'Activité physique', 'reason' => 'Intégrer un mouvement régulier pour le corps et l\'esprit.', 'icon' => 'bi-bicycle'],
            'nutrition' => ['type' => 'nutrition', 'label' => 'Nutrition', 'reason' => 'Équilibrer votre alimentation pour plus d\'énergie.', 'icon' => 'bi-nutrition'],
            'hydratation' => ['type' => 'hydratation', 'label' => 'Hydratation', 'reason' => 'Maintenir une bonne hydratation tout au long de la journée.', 'icon' => 'bi-droplet'],
            'poids' => ['type' => 'poids', 'label' => 'Poids', 'reason' => 'Suivre votre poids de façon saine et progressive.', 'icon' => 'bi-speedometer2'],
        ];

        return match ($profilType) {
            'Risque élevé' => [$all['humeur'], $all['sommeil'], $all['activite'], $all['nutrition']],
            'Vulnérabilité moyenne' => [$all['sommeil'], $all['activite'], $all['humeur']],
            'Équilibre modéré' => [$all['activite'], $all['sommeil'], $all['nutrition']],
            default => [$all['activite'], $all['nutrition'], $all['hydratation']],
        };
    }
}
