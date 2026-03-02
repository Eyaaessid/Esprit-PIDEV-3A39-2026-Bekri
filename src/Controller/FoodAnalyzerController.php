<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/food-analyzer')]
class FoodAnalyzerController extends AbstractController
{
    #[Route('/', name: 'food_analyzer_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté.');
        }

        $input    = '';
        $analysis = null;
        $error    = null;

        if ($request->isMethod('POST')) {

            $input = trim((string) $request->request->get('ingredients', ''));

            if ($input === '') {
                $error = 'Veuillez décrire au moins un repas ou des ingrédients.';
            } else {
                try {
                    $analysis = $this->analyzeFood($input);
                } catch (\Throwable $e) {
                    $error = 'Erreur API : ' . $e->getMessage();
                }
            }
        }

        return $this->render('food-analyzer/index.html.twig', [
            'input'    => $input,
            'analysis' => $analysis,
            'error'    => $error,
        ]);
    }

    private function analyzeFood(string $input): array
    {
        // Use $_SERVER as fallback — more reliable than $_ENV in Symfony
        $apiKey = $_SERVER['GROQ_API_KEY'] ?? $_ENV['GROQ_API_KEY'] ?? null;

        if (!$apiKey) {
            throw new \RuntimeException('Clé API Groq manquante. Vérifiez votre .env.local');
        }

        $client = HttpClient::create();

        $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'    => 'llama-3.1-8b-instant', // ✅ Updated model (llama3-8b-8192 is deprecated)
                'messages' => [
                    [
                        'role'    => 'system',
                        'content' => 'Tu es un nutritionniste expert. Réponds UNIQUEMENT en JSON brut, sans markdown, sans balises ```, sans texte avant ou après. Le JSON doit contenir exactement ces clés : estimated_calories (string), macronutrients (array of strings), vitamins_minerals (array of strings), health_benefits (array of strings), health_risks (array of strings), tips (array of strings).',
                    ],
                    [
                        'role'    => 'user',
                        'content' => "Analyse ce repas : $input",
                    ],
                ],
                'temperature' => 0.3,
            ],
            'timeout' => 60,
        ]);

        $statusCode = $response->getStatusCode();

        if ($statusCode >= 400) {
            // Get the real error body from Groq for proper debugging
            $errorBody = $response->getContent(false);
            throw new \RuntimeException(
                'HTTP ' . $statusCode . ' — ' . $errorBody
            );
        }

        $data = $response->toArray(false);

        $content = $data['choices'][0]['message']['content'] ?? '';

        if (empty($content)) {
            throw new \RuntimeException('La réponse de l\'IA est vide.');
        }

        // ✅ Strip markdown code fences if the AI wraps JSON in ```json ... ```
        $content = trim($content);
        $content = preg_replace('/^```(?:json)?\s*/i', '', $content);
        $content = preg_replace('/\s*```$/i', '', $content);
        $content = trim($content);

        $decoded = json_decode($content, true);

        if (!is_array($decoded)) {
            throw new \RuntimeException(
                'Réponse IA invalide (JSON mal formé). Reçu : ' . mb_substr($content, 0, 300)
            );
        }

        return $decoded;
    }
}