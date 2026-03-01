<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Entity\ObjectifBienEtre;
use App\Repository\SuiviQuotidienRepository;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;

#[Route('/chat', name: 'chat_')]
class ChatController extends AbstractController
{
    #[Route('', name: 'index', methods: ['GET'])]
    public function index(
        SuiviQuotidienRepository $suiviRepo
    ): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $today = new \DateTime();
        $startDate = clone $today;
        $startDate->modify('-6 days');

        $dailies = $suiviRepo->createQueryBuilder('s')
            ->where('s.utilisateur = :user')
            ->andWhere('s.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startDate)
            ->setParameter('end', $today)
            ->getQuery()
            ->getResult();

        $moodAvg = $this->getMoodAverage($dailies);

        /** @var Collection<int, ObjectifBienEtre> $goals */
        $goals = $user->getObjectifBienEtres();

        return $this->render('chat/index.html.twig', [
            'moodAvg' => $moodAvg,
            'goals' => $goals,
        ]);
    }

    #[Route('/message', name: 'send', methods: ['POST'])]
public function sendMessage(
    Request $request,
    SuiviQuotidienRepository $suiviRepo
): Response
{
    /** @var Utilisateur $user */
    $user = $this->getUser();

    if (!$user) {
        return new Response('Unauthorized', 401);
    }

    $message = trim((string) $request->request->get('message', ''));
    if (empty($message)) {
        return new Response('Message vide', 400);
    }

    $today     = new \DateTime();
    $startDate = clone $today;
    $startDate->modify('-6 days');

    $dailies = $suiviRepo->createQueryBuilder('s')
        ->where('s.utilisateur = :user')
        ->andWhere('s.date BETWEEN :start AND :end')
        ->setParameter('user', $user)
        ->setParameter('start', $startDate)
        ->setParameter('end', $today)
        ->getQuery()
        ->getResult();

    $moodAvg      = $this->getMoodAverage($dailies);
    $goals        = $user->getObjectifBienEtres();
    $systemPrompt = $this->buildSystemPrompt($moodAvg, $goals);

    // ── safely get API key ───────────────────────────────────────────
    $groqKey = $_ENV['GROQ_API_KEY'] ?? $_SERVER['GROQ_API_KEY'] ?? null;
    if (!$groqKey) {
        return new Response('GROQ_API_KEY non configurée dans .env.local', 500);
    }

    $client = HttpClient::create(['timeout' => 15]);

    try {
        $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $groqKey,
                'Content-Type'  => 'application/json',
            ],
            'json' => [
                'model'       => 'llama-3.1-8b-instant',
                'messages'    => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user',   'content' => $message],
                ],
                'temperature' => 0.7,
                'max_tokens'  => 500,
                'stream'      => false,
            ],
        ]);

        $statusCode = $response->getStatusCode();
        if ($statusCode !== 200) {
            $body = $response->getContent(false);
            return new Response('Groq API error ' . $statusCode . ': ' . $body, 500);
        }

        $data       = $response->toArray();
        $botResponse = $data['choices'][0]['message']['content'] ?? 'Pas de réponse.';

        return new Response($botResponse, 200, ['Content-Type' => 'text/plain; charset=utf-8']);

    } catch (TransportExceptionInterface $e) {
        return new Response('Erreur réseau: ' . $e->getMessage(), 500);
    } catch (\Throwable $e) {
        return new Response('Erreur: ' . $e->getMessage(), 500);
    }
}

    private function getMoodAverage(array $dailies): float
    {
        $totalMood = 0;
        $count = 0;

        foreach ($dailies as $daily) {
            foreach ($daily->getReponses() as $response) {
                if ($response->getQuestion()->getCategory() === 'humeur') {
                    $totalMood += $this->convertResponseToScore($response->getValeur());
                    $count++;
                }
            }
        }

        return $count > 0 ? $totalMood / $count : 0;
    }

    private function convertResponseToScore(string $valeur): float
    {
        return match (strtolower($valeur)) {
            'très bien' => 3.0,
            'correct' => 2.0,
            'pas terrible' => 1.0,
            default => (float) $valeur,
        };
    }

    private function buildSystemPrompt(float $moodAvg, Collection $goals): string
    {
        $goalsText = $goals->isEmpty()
            ? 'No active goals'
            : 'Current goals: ' . implode(', ', $goals->map(fn($g) => $g->getTitre())->toArray());
    
        return "You are an assistant, a caring and encouraging wellness coach on the Bekri platform.
    
    LANGUAGE RULE — THIS IS YOUR MOST IMPORTANT RULE:
    - You MUST detect the language of EACH user message
    - If the user writes in French → you MUST reply in French
    - If the user writes in English → you MUST reply in English
    - If the user writes in any other language → reply in that same language
    - NEVER mix languages in the same response
    - NEVER default to French if the user wrote in English
    - The language of YOUR response must ALWAYS match the language of the USER'S message
    
    EXPERTISE DOMAIN — You ONLY answer questions about:
    - Mental health (stress, anxiety, depression, emotions, psychological well-being)
    - Physical health (exercise, pain, fatigue, posture, recovery)
    - Sleep (quality, insomnia, evening routines)
    - Nutrition and hydration (balanced diet, eating habits)
    - Wellness goals and motivation
    - Stress management and relaxation
    - Mood tracking and emotional well-being
    
    OFF-TOPIC RULE — If the user asks about anything outside the above topics:
    - If they wrote in French, reply: \"Je suis spécialisée uniquement dans le bien-être et la santé. Je ne peux pas vous aider sur ce sujet. Avez-vous des questions sur votre santé ou votre bien-être ?\"
    - If they wrote in English, reply: \"I'm only specialized in wellness and health topics. I can't help with that subject. Do you have any questions about your health or well-being?\"
    
    Examples of forbidden topics: politics, finance, technology, cooking recipes, travel, news, mathematics, coding, etc.
    
    User's recent data:
    - Average mood last week: {$moodAvg}/3
    - {$goalsText}
    
    RESPONSE STYLE:
    - Warm, caring, non-judgmental
    - Never give direct medical advice (refer to a professional when needed)
    - If the person seems in distress, refer them to a healthcare professional
    - Keep responses short (3-5 sentences max)
    - Always end with a question to continue the conversation
    - Suggest small concrete actions when relevant";
    }
}