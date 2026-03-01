<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/plan')]
class PlanSemaineController extends AbstractController
{
    #[Route('/', name: 'plan_index')]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $plan = null;
        $formData = [];
        $error = null;

        if ($request->isMethod('POST')) {
            $formData = [
                'poids'       => $request->request->get('poids'),
                'taille'      => $request->request->get('taille'),
                'age'         => $request->request->get('age'),
                'sexe'        => $request->request->get('sexe'),
                'objectif'    => $request->request->get('objectif'),
                'exercice'    => $request->request->get('exercice'),
                'restrictions'=> $request->request->get('restrictions', ''),
            ];

            try {
                $plan = $this->generatePlan($formData);
            } catch (\Throwable $e) {
                $error = 'Une erreur est survenue lors de la génération du plan. Veuillez réessayer.';
            }
        }

        return $this->render('plan/index.html.twig', [
            'plan'     => $plan,
            'formData' => $formData,
            'error'    => $error,
        ]);
    }

    #[Route('/pdf', name: 'plan_pdf', methods: ['POST'])]
    public function pdf(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException();
        }

        $formData = [
            'poids'        => $request->request->get('poids'),
            'taille'       => $request->request->get('taille'),
            'age'          => $request->request->get('age'),
            'sexe'         => $request->request->get('sexe'),
            'objectif'     => $request->request->get('objectif'),
            'exercice'     => $request->request->get('exercice'),
            'restrictions' => $request->request->get('restrictions', ''),
        ];

        $plan = $this->generatePlan($formData);

        $html = $this->renderView('plan/pdf.html.twig', [
            'plan'        => $plan,
            'formData'    => $formData,
            'user'        => $user,
            'generatedAt' => new \DateTime(),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'bekri-plan-semaine-' . (new \DateTime())->format('Y-m-d') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    private function generatePlan(array $data): array
    {
        // Calculate BMI
        $tailleM = $data['taille'] / 100;
        $imc = round($data['poids'] / ($tailleM * $tailleM), 1);

        $objectifLabels = [
            'perte_poids'   => 'perte de poids',
            'prise_masse'   => 'prise de masse musculaire',
            'maintien'      => 'maintien du poids',
            'sante'         => 'amélioration de la santé générale',
        ];
        $objectifLabel = $objectifLabels[$data['objectif']] ?? $data['objectif'];

        $exerciceLabels = [
            'sedentaire'    => 'sédentaire (pas d\'exercice)',
            'leger'         => 'légèrement actif (1-2 fois/semaine)',
            'modere'        => 'modérément actif (3-4 fois/semaine)',
            'actif'         => 'très actif (5+ fois/semaine)',
        ];
        $exerciceLabel = $exerciceLabels[$data['exercice']] ?? $data['exercice'];

        $restrictions = !empty($data['restrictions']) ? "Restrictions alimentaires: {$data['restrictions']}" : "Aucune restriction alimentaire";

        $prompt = "Tu es un coach bien-être et nutritionniste expert. Génère un plan hebdomadaire personnalisé en JSON uniquement.

Profil utilisateur:
- Âge: {$data['age']} ans
- Sexe: {$data['sexe']}
- Poids: {$data['poids']} kg
- Taille: {$data['taille']} cm
- IMC: {$imc}
- Objectif: {$objectifLabel}
- Niveau d'activité: {$exerciceLabel}
- {$restrictions}

Réponds UNIQUEMENT avec ce JSON valide (rien d'autre, pas de markdown, pas de backticks):
{
  \"resume\": \"Résumé personnalisé du plan en 2-3 phrases\",
  \"imc\": {$imc},
  \"calories_journalieres\": 0,
  \"conseils_generaux\": [\"conseil1\", \"conseil2\", \"conseil3\"],
  \"repas\": {
    \"lundi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"mardi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"mercredi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"jeudi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"vendredi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"samedi\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"},
    \"dimanche\": {\"petit_dejeuner\": \"...\", \"dejeuner\": \"...\", \"diner\": \"...\", \"collation\": \"...\"}
  },
  \"exercices\": {
    \"lundi\": {\"type\": \"...\", \"duree\": \"...\", \"intensite\": \"...\", \"description\": \"...\"},
    \"mardi\": {\"type\": \"repos\", \"duree\": \"-\", \"intensite\": \"-\", \"description\": \"Journée de récupération\"},
    \"mercredi\": {\"type\": \"...\", \"duree\": \"...\", \"intensite\": \"...\", \"description\": \"...\"},
    \"jeudi\": {\"type\": \"...\", \"duree\": \"...\", \"intensite\": \"...\", \"description\": \"...\"},
    \"vendredi\": {\"type\": \"...\", \"duree\": \"...\", \"intensite\": \"...\", \"description\": \"...\"},
    \"samedi\": {\"type\": \"...\", \"duree\": \"...\", \"intensite\": \"...\", \"description\": \"...\"},
    \"dimanche\": {\"type\": \"repos\", \"duree\": \"-\", \"intensite\": \"-\", \"description\": \"Journée de récupération active\"}
  },
  \"hydratation\": {
    \"litres_par_jour\": 0.0,
    \"conseils\": [\"conseil1\", \"conseil2\", \"conseil3\"]
  },
  \"sommeil\": {
    \"heures_recommandees\": \"7-9h\",
    \"conseils\": [\"conseil1\", \"conseil2\", \"conseil3\"]
  }
}";

        $client   = HttpClient::create();
        $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $_ENV['GROQ_API_KEY'],
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => 'llama-3.1-8b-instant',
                'messages'    => [
                    [
                        'role'    => 'system',
                        'content' => 'Tu es un coach bien-être et nutritionniste expert. Tu réponds UNIQUEMENT en JSON valide, sans texte libre, sans markdown, sans backticks.'
                    ],
                    ['role' => 'user', 'content' => $prompt],
                ],
                'temperature' => 0.6,
                'max_tokens'  => 3000,
                'stream'      => false,
            ],
        ]);

        $data    = $response->toArray();
        $content = $data['choices'][0]['message']['content'] ?? '';

        // Clean any markdown formatting
        $content = preg_replace('/```json\s*/i', '', $content);
        $content = preg_replace('/```\s*/i', '', $content);
        $content = trim($content);

        $plan = json_decode($content, true);

        if (!is_array($plan)) {
            throw new \RuntimeException('Invalid AI response');
        }

        return $plan;
    }
}