<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * AI Emotional Insight Assistant.
 *
 * Generates a personalized psychological interpretation and mindset advice
 * from assessment data (and later: suivi quotidien, mood journal, etc.).
 * Does NOT provide medical diagnosis or replace professional advice.
 */
class AiEmotionalInsightService
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $groqApiKey,
        private ?LoggerInterface $logger = null,
    ) {
    }

    /**
     * Generate emotional insight text from structured assessment data.
     *
     * @param array{
     *   mentalScore: int,
     *   physicalScore: int,
     *   totalScore: int,
     *   finalScore: int,
     *   profilType: string,
     *   distribution: array{low01: int, mid2: int, high34: int},
     *   context?: array
     * } $data Structured input (context reserved for suivi/mood data later)
     */
    public function generateFromAssessment(array $data): ?string
    {
        $key = trim($this->groqApiKey ?? '');
        if ($key === '') {
            $this->logger?->debug('AiEmotionalInsightService: GROQ_API_KEY empty, skipping AI generation.');
            return null;
        }

        try {
            $text = $this->callGroq($data);
            if ($text !== '') {
                return $text;
            }
        } catch (\Throwable $e) {
            $this->logger?->warning('AiEmotionalInsightService: AI request failed.', [
                'message' => $e->getMessage(),
            ]);
        }
        try {
            $text = $this->callGroq($data);
            return $text !== '' ? $text : null;
        } catch (\Throwable $e) {
            $this->logger?->warning('AiEmotionalInsightService: AI retry failed.', [
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Generate insight from score and profil only (e.g. for regeneration when full assessment data is not stored).
     * Infers a plausible distribution and mental/physical split from the score.
     */
    public function generateFromScoreOnly(int $scoreGlobal, string $profilType): ?string
    {
        $totalBrut = (int) round($scoreGlobal * 80 / 100);
        $totalBrut = max(0, min(80, $totalBrut));
        $mentalScore = (int) round($totalBrut * 0.5);
        $physicalScore = $totalBrut - $mentalScore;
        $high34 = (int) round($totalBrut / 4);
        $high34 = min(20, max(0, $high34));
        $low01 = max(0, min(20, 20 - $high34 - (int) round(20 * 0.25)));
        $mid2 = 20 - $low01 - $high34;
        $distribution = ['low01' => $low01, 'mid2' => max(0, $mid2), 'high34' => $high34];
        return $this->generateFromAssessment([
            'mentalScore' => $mentalScore,
            'physicalScore' => $physicalScore,
            'totalScore' => $totalBrut,
            'finalScore' => $scoreGlobal,
            'profilType' => $profilType,
            'distribution' => $distribution,
        ]);
    }

    /**
     * @param array{mentalScore: int, physicalScore: int, totalScore: int, finalScore: int, profilType: string, distribution: array{low01: int, mid2: int, high34: int}, context?: array} $data
     */
    private function callGroq(array $data): string
    {
        $d = $data['distribution'];
        $contextBlock = '';
        if (!empty($data['context'])) {
            $contextBlock = "\nContexte additionnel (suivi, humeur, etc.) : " . json_encode($data['context'], \JSON_UNESCAPED_UNICODE);
        }

        $prompt = <<<PROMPT
Tu es un assistant bien-être (AI Emotional Insight Assistant), bienveillant et professionnel. Tu reçois les résultats d'une évaluation bien-être et tu dois rédiger une analyse psychologique personnalisée, dynamique, basée UNIQUEMENT sur ces chiffres. Aucun texte générique : chaque phrase doit refléter les données ci-dessous.

DONNÉES DE L'ÉVALUATION (utilise-les pour personnaliser) :
- Score mental / émotionnel (section 1, sur 40) : {$data['mentalScore']}
- Score physique (section 2, sur 40) : {$data['physicalScore']}
- Score total brut (sur 80) : {$data['totalScore']}
- Score final (sur 100) : {$data['finalScore']}
- Profil : {$data['profilType']}
- Répartition des réponses : réponses basses (0-1) = {$d['low01']}, moyennes (2) = {$d['mid2']}, hautes (3-4) = {$d['high34']}
{$contextBlock}

Tu DOIS générer un texte en français en TROIS parties distinctes (chaque partie en un ou deux paragraphes courts). Réponds UNIQUEMENT par ce texte, sans titre ni préambule.

1) RÉSUMÉ ÉMOTIONNEL PERSONNALISÉ
- Explique ce que le score signifie émotionnellement pour cette personne (stress, anxiété, fatigue, équilibre, etc.).
- Décris les patterns visibles dans les réponses (ex. : beaucoup de réponses hautes en mental = charge mentale ; déséquilibre mental/physique = …).
- Si stress, anxiété ou fatigue dominent, le dire avec bienveillance et précision en t’appuyant sur MentalScore, PhysicalScore et la répartition.

2) INTERPRÉTATION DU NIVEAU DE VULNÉRABILITÉ
- Si équilibré (score bas, peu de réponses 3-4) : souligner les points forts et les ressources.
- Si modéré : souligner la prévention et les petits ajustements possibles.
- Si vulnérabilité élevée (score haut, beaucoup de 3-4) : souligner le besoin de repos et de prise de conscience, sans alarmer.
- Ton : soutenant, jamais médical, jamais alarmant. Aucun diagnostic.

3) CONSEILS DE POSTURE MENTALE (PAS D’OBJECTIFS)
- Uniquement : posture mentale, conscience émotionnelle, recadrage cognitif, bienveillance envers soi, pauses mentales, gestion de la charge cognitive.
- INTERDIT : objectifs chiffrés, tâches à cocher, recommandations du type "boire plus d’eau", "faire du sport 3 fois par semaine", "dormir 8 h". Ces sujets relèvent d’un autre module (objectifs). Ici, seulement conseils psychologiques et posture.

Contraintes absolues :
- Pas de diagnostic médical ni de recommandation thérapeutique.
- Pas de liste d’objectifs ou de tâches. Uniquement interprétation et conseils de mindset.
- Ton : professionnel, soutenant, non jugeant.
- Réponds directement par le texte des trois parties, sans numérotation visible ni "Partie 1", sans introduction.
PROMPT;

        $response = $this->httpClient->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->groqApiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => 'llama-3.1-8b-instant',
                'messages' => [
                    ['role' => 'system', 'content' => 'Tu es l’AI Emotional Insight Assistant. Tu génères une analyse psychologique personnalisée en 3 parties (résumé émotionnel, interprétation du risque, conseils de posture mentale). Jamais de diagnostic médical. Jamais d’objectifs ou de tâches (pas de "faire X par semaine"). Réponds uniquement par le texte en français.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.5,
                'max_tokens' => 900,
            ],
            'timeout' => 25,
        ]);

        $body = $response->toArray(false);
        $content = $body['choices'][0]['message']['content'] ?? '';
        $text = trim((string) $content);
        return $text !== '' ? $text : '';
    }
}
