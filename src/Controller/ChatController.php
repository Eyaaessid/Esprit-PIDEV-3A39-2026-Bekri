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

        $message = $request->request->get('message');
        if (empty($message)) {
            return new Response('Message vide', 400);
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

        $systemPrompt = $this->buildSystemPrompt($moodAvg, $goals);

        $client = HttpClient::create();

        try {
            /** @var ResponseInterface $response */
            $response = $client->request('POST', 'https://api.groq.com/openai/v1/chat/completions', [
                'headers' => [
                    'Authorization' => 'Bearer ' . $_ENV['GROQ_API_KEY'],
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'model' => 'llama-3.1-8b-instant',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $message],
                    ],
                    'temperature' => 0.7,
                    'max_tokens' => 500,
                    'stream' => false,
                ],
            ]);

            $data = $response->toArray();
            $botResponse = $data['choices'][0]['message']['content'] ?? 'Pas de réponse.';

            return new Response($botResponse, 200, ['Content-Type' => 'text/plain']);

        } catch (TransportExceptionInterface $e) {
            return new Response('Erreur de connexion : ' . $e->getMessage(), 500);
        } catch (\Throwable $e) {
            return new Response('Erreur inattendue : ' . $e->getMessage(), 500);
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
            ? 'Aucun objectif actif'
            : 'Vos objectifs actuels : ' . implode(', ', $goals->map(fn($g) => $g->getTitre())->toArray());

        return "Tu es Eya, une coach bien-être bienveillante et encourageante de la plateforme Bekri.
Tu parles en français, chaleureusement, sans jugement.
Tu connais les données récentes de l'utilisateur :
- Humeur moyenne dernière semaine : {$moodAvg}/3
- {$goalsText}

Réponds de manière naturelle, propose des petites actions concrètes, pose des questions ouvertes.
Ne donne jamais de conseil médical. Si la personne semble en détresse, oriente vers un professionnel.
Garde les réponses courtes (3–5 phrases max). Termine toujours par une question pour continuer la conversation.";
    }
}