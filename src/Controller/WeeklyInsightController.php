<?php

namespace App\Controller;

use App\Repository\SuiviQuotidienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/insight')]
class WeeklyInsightController extends AbstractController
{
    #[Route('/weekly', name: 'insight_weekly')]
    public function weekly(
        SuiviQuotidienRepository $suiviRepo,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        $startDate = clone $today;
        $startDate->modify('-6 days');

        $endDate = clone $today;
        $endDate->modify('+1 day');

        $dailies = $suiviRepo->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();

        $insights = $this->calculateWeeklyInsights($dailies);
        $recommendations = $this->getAIRecommendations($dailies, $insights);

        return $this->render('insight/weekly.html.twig', [
            'dailies' => $dailies,
            'insights' => $insights,
            'recommendations' => $recommendations,
            'periodStart' => $startDate,
            'periodEnd' => $today,
        ]);
    }
    #[Route('/weekly/pdf', name: 'insight_weekly_pdf')]
    public function downloadPdf(
        SuiviQuotidienRepository $suiviRepo
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $startDate = clone $today;
        $startDate->modify('-6 days');
        $endDate = clone $today;
        $endDate->modify('+1 day');

        $dailies = $suiviRepo->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $endDate)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();

        $insights = $this->calculateWeeklyInsights($dailies);
        $recommendations = $this->getAIRecommendations($dailies, $insights);

        // Compute global average
        $globalAvg = 0;
        if (!empty($insights['averageScores'])) {
            $globalAvg = round(array_sum($insights['averageScores']) / count($insights['averageScores']), 1);
        }

        // Render the PDF Twig template to HTML
        $html = $this->renderView('insight/weekly_pdf.html.twig', [
            'user'          => $user,
            'insights'      => $insights,
            'recommendations' => $recommendations,
            'globalAvg'     => $globalAvg,
            'periodStart'   => $startDate,
            'periodEnd'     => $today,
            'generatedAt'   => new \DateTime(),
        ]);

        // Setup Dompdf
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'bekri-resume-' . $today->format('Y-m-d') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }
    private function calculateWeeklyInsights(array $dailies): array
    {
        $insights = [
            'totalDays' => count($dailies),
            'averageScores' => [],
            'trends' => [],
            'highlights' => [],
        ];

        if (empty($dailies)) {
            return $insights;
        }

        $categoryScores = [];
        foreach ($dailies as $daily) {
            foreach ($daily->getReponses() as $response) {
                $category = $response->getQuestion()->getCategory();
                $score = $this->convertResponseToScore($response->getValeur());

                if (!isset($categoryScores[$category])) {
                    $categoryScores[$category] = [];
                }
                $categoryScores[$category][] = $score;
            }
        }

        foreach ($categoryScores as $category => $scores) {
            $insights['averageScores'][$category] = round(array_sum($scores) / count($scores), 1);
        }

        // Trend: first half vs second half
        $midPoint = (int)(count($dailies) / 2);
        $firstHalfScores = array_slice($categoryScores['humeur'] ?? [], 0, $midPoint);
        $secondHalfScores = array_slice($categoryScores['humeur'] ?? [], $midPoint);

        if (!empty($firstHalfScores) && !empty($secondHalfScores)) {
            $firstAvg = array_sum($firstHalfScores) / count($firstHalfScores);
            $secondAvg = array_sum($secondHalfScores) / count($secondHalfScores);
            $insights['trends']['humeur'] = $secondAvg > $firstAvg ? 'Improving' : 'Declining';
        }

        // Best/worst day
        $bestDay = null;
        $worstDay = null;
        $bestScore = -1;
        $worstScore = INF;

        foreach ($dailies as $daily) {
            $dayScores = [];
            foreach ($daily->getReponses() as $response) {
                $dayScores[] = $this->convertResponseToScore($response->getValeur());
            }
            $avgDayScore = count($dayScores) > 0 ? array_sum($dayScores) / count($dayScores) : 0;

            if ($avgDayScore > $bestScore) {
                $bestScore = $avgDayScore;
                $bestDay = $daily->getDate();
            }

            if ($avgDayScore < $worstScore) {
                $worstScore = $avgDayScore;
                $worstDay = $daily->getDate();
            }
        }

        $insights['highlights'] = [
            'bestDay' => $bestDay,
            'worstDay' => $worstDay,
            'bestScore' => round($bestScore, 1),
            'worstScore' => round($worstScore, 1),
        ];

        return $insights;
    }

    /**
     * Converts a text response to a percentage score (0–100).
     * All options from the DB are mapped here.
     */
    private function convertResponseToScore(string $valeur): float
    {
        $raw = match (strtolower(trim($valeur))) {
            // ── Positive / best options → 100%
            'positive', 'positif',
            'très bien',
            'oui, plusieurs',
            'très optimiste',
            'oui, beaucoup',
            'excellente', 'excellent',
            'oui, très',
            'plus de 8h',
            'très motivé', 'très motivée',
            'très équilibrée', 'très équilibré',
            'intense',
            'élevé', 'élevée',
            'plus de 30 min',
            'énergique',
            'oui',
            'non',         // e.g. "Avez-vous consommé des aliments transformés ?" → Non = good
            'tous',
            'jamais',
            '0 fois'
                => 100.0,

            // ── Middle options → 66%
            'neutre',
            'correct', 'correcte',
            'oui, un peu',
            'un peu',
            'oui, moyennement', 'moyennement',
            '6-8h',
            'partiellement',
            'presque', 'presque (1.5–2l)',
            'moyenne',
            'la plupart',
            'modérée', 'modéré',
            'moyen', 'moyenne',
            '10–30 min', '10-30 min',
            'normal',
            'légers',
            'oui, modérées',
            '1–2 fois', '1-2 fois',
            'oui, un peu'
                => 66.0,

            // ── Negative / worst options → 33%
            'négative', 'négatif',
            'pas terrible',
            'plutôt pessimiste',
            'mauvaise', 'mauvais',
            'oui, beaucoup',   // e.g. "Avez-vous eu des difficultés à vous endormir ?" → Oui, beaucoup = bad
            'moins de 6h',
            'démotivé', 'démotivée',
            'peu équilibrée', 'peu équilibré',
            'aucune', 'aucun',
            'faible',
            'moins de 10 min',
            'fatigué', 'fatiguée',
            'non',             // context-dependent; handled by 100 above for "good non" questions
            'oui, très fortes',
            '3 fois ou plus',
            'souvent', 'toujours'
                => 33.0,

            default => 0.0,
        };

        return $raw;
    }

    private function getAIRecommendations(array $dailies, array $insights): array
    {
        $fallback = [
            [
                'title' => 'Continuez votre suivi',
                'description' => 'Maintenez votre routine de suivi quotidien pour de meilleures recommandations personnalisées.',
                'icon' => 'fa-chart-line',
                'color' => 'text-teal-600',
                'suggestedObjectif' => 'Remplir le suivi quotidien chaque jour'
            ]
        ];

        if (empty($dailies)) {
            return $fallback;
        }

        $moodAvg   = $insights['averageScores']['humeur'] ?? 0;
        $sleepAvg  = $insights['averageScores']['sommeil'] ?? null;
        $actAvg    = $insights['averageScores']['activite'] ?? null;
        $nutriAvg  = $insights['averageScores']['nutrition'] ?? null;
        $poidsAvg  = $insights['averageScores']['poids'] ?? null;
        $hydraAvg  = $insights['averageScores']['hydratation'] ?? null;
        $stressAvg = $insights['averageScores']['stress'] ?? null;
        $trend     = $insights['trends']['humeur'] ?? 'stable';
        $totalDays = $insights['totalDays'];

        $lines = [
            "Données utilisateur sur les 7 derniers jours (scores en %) :",
            "- Jours complétés : {$totalDays}/7",
            "- Humeur moyenne : {$moodAvg}%",
            "- Tendance humeur : {$trend}",
        ];
        if ($sleepAvg !== null)  $lines[] = "- Sommeil moyen : {$sleepAvg}%";
        if ($actAvg !== null)    $lines[] = "- Activité physique : {$actAvg}%";
        if ($nutriAvg !== null)  $lines[] = "- Nutrition : {$nutriAvg}%";
        if ($poidsAvg !== null)  $lines[] = "- Suivi poids : {$poidsAvg}%";
        if ($hydraAvg !== null)  $lines[] = "- Hydratation : {$hydraAvg}%";
        if ($stressAvg !== null) $lines[] = "- Stress : {$stressAvg}%";

        $dataSummary = implode("\n", $lines);

        $prompt = $dataSummary . "

Génère exactement 3 recommandations personnalisées basées sur ces données, en JSON uniquement.
Format attendu (tableau JSON, rien d'autre, pas de markdown) :
[
  {
    \"title\": \"...\",
    \"description\": \"...\",
    \"icon\": \"fa-...\",
    \"color\": \"text-...-600\",
    \"suggestedObjectif\": \"...\"
  }
]

Règles :
- Réponds UNIQUEMENT avec le JSON valide, sans texte avant ou après, sans backticks
- Chaque description : concrète, bienveillante, max 2 phrases
- Icônes Font Awesome valides (ex: fa-heartbeat, fa-person-running, fa-moon, fa-apple-whole, fa-brain, fa-droplet, fa-dumbbell)
- Couleurs Tailwind valides (ex: text-teal-600, text-red-600, text-amber-700, text-blue-600, text-purple-600, text-indigo-600)
- Priorise les catégories avec les scores les plus bas
- Rédige en français";

        try {
            $client = HttpClient::create();
            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['GROQ_API_KEY'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        [
                            'role' => 'system',
                            'content' => 'Tu es un coach bien-être expert. Tu analyses des données de santé et génères des recommandations personnalisées. Tu réponds UNIQUEMENT en JSON valide, jamais en texte libre, jamais en markdown.'
                        ],
                        ['role' => 'user', 'content' => $prompt],
                    ],
                    'temperature' => 0.5,
                    'max_tokens' => 700,
                    'stream' => false,
                ],
            ]);

            $data = $response->toArray();
            $content = $data['choices'][0]['message']['content'] ?? '';

            // Strip markdown code fences if present
            $content = preg_replace('/```json\s*/i', '', $content);
            $content = preg_replace('/```\s*/i', '', $content);
            $content = trim($content);

            $recommendations = json_decode($content, true);

            if (is_array($recommendations) && count($recommendations) > 0) {
                foreach ($recommendations as $rec) {
                    if (!isset($rec['title'], $rec['description'], $rec['icon'], $rec['color'], $rec['suggestedObjectif'])) {
                        return $fallback;
                    }
                }
                return $recommendations;
            }

            return $fallback;

        } catch (\Throwable $e) {
            return $fallback;
        }
    }

    private function getMoodAverageLastDays(array $dailies, int $minDays = 3): ?float
    {
        $moodScores = [];
        $daysWithMood = 0;

        foreach ($dailies as $daily) {
            $dayMoodScores = [];
            foreach ($daily->getReponses() as $response) {
                if ($response->getQuestion()->getCategory() === 'humeur') {
                    $dayMoodScores[] = $this->convertResponseToScore($response->getValeur());
                }
            }
            if (!empty($dayMoodScores)) {
                $moodScores[] = array_sum($dayMoodScores) / count($dayMoodScores);
                $daysWithMood++;
            }
        }

        if ($daysWithMood < $minDays) {
            return null;
        }

        return array_sum($moodScores) / count($moodScores);
    }
}