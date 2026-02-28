<?php

namespace App\Controller;

use App\Entity\ProfilPsychologique;
use App\Entity\Utilisateur;
use App\Service\AiEmotionalInsightService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\InvalidCsrfTokenException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Initial Wellbeing Assessment (20 questions, 2 sections).
 * Shown after signup / first login until completed.
 */
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class InitialAssessmentController extends AbstractController
{
    public const QUESTIONS = [
        'mental' => [
            'À quelle fréquence vous sentez-vous stressé(e) ?',
            'Vous sentez-vous anxieux(se) sans raison apparente ?',
            'Avez-vous des pensées négatives récurrentes ?',
            'Vous sentez-vous dépassé(e) par vos responsabilités ?',
            'Avez-vous des difficultés à vous concentrer ?',
            'Vous sentez-vous émotionnellement stable ?',
            'Vous ressentez-vous triste ou démotivé(e) ?',
            'Avez-vous des difficultés à gérer vos émotions ?',
            'Vous sentez-vous satisfait(e) de votre vie actuelle ?',
            'Vous arrive-t-il de vous sentir mentalement épuisé(e) ?',
        ],
        'physical' => [
            'Comment évaluez-vous la qualité de votre sommeil ?',
            'Vous sentez-vous fatigué(e) au réveil ?',
            'Faites-vous de l\'activité physique régulièrement ?',
            'Avez-vous des douleurs physiques fréquentes ?',
            'Votre alimentation est-elle équilibrée ?',
            'Buvez-vous suffisamment d\'eau chaque jour ?',
            'Ressentez-vous des tensions musculaires liées au stress ?',
            'Vous sentez-vous énergique durant la journée ?',
            'Prenez-vous du temps pour vous détendre physiquement ?',
            'Votre état de santé général vous semble-t-il bon ?',
        ],
    ];

    /** Likert labels: 0 = best, 4 = worst (same semantic for all questions) */
    public const LIKERT_LABELS = [
        0 => 'Jamais / Très bon / Très positif',
        1 => 'Rarement / Bon',
        2 => 'Parfois / Moyen',
        3 => 'Souvent / Mauvais',
        4 => 'Très souvent / Très mauvais',
    ];

    public const QUESTIONS_PER_PAGE = 5;
    public const TOTAL_QUESTIONS = 20;
    public const MAX_RAW_SCORE = 80;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private CsrfTokenManagerInterface $csrfTokenManager,
        private AiEmotionalInsightService $aiEmotionalInsightService
    ) {}

    #[Route('/initial-assessment', name: 'app_initial_assessment', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if ($user->getProfilPsychologique() !== null) {
            return $this->redirectToRoute('user_dashboard');
        }

        $session = $request->getSession();
        $savedAnswers = $session->get('initial_assessment_answers', []);
        if (!is_array($savedAnswers)) {
            $savedAnswers = [];
        }
        $savedAnswers = array_slice(array_replace(array_fill(0, self::TOTAL_QUESTIONS, null), $savedAnswers), 0, self::TOTAL_QUESTIONS);

        $allQuestions = $this->getAllQuestionsList();
        $page = max(1, (int) $request->query->get('page', $request->request->get('page', 1)));
        $totalPages = (int) ceil(self::TOTAL_QUESTIONS / self::QUESTIONS_PER_PAGE);
        $page = min($page, $totalPages);

        if ($request->isMethod('POST')) {
            if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('assessment', $request->request->get('_csrf_token', '')))) {
                throw new InvalidCsrfTokenException('Invalid CSRF token.');
            }
            $pageAnswers = $this->collectPageAnswersFromRequest($request, $page);
            foreach ($pageAnswers as $idx => $val) {
                $savedAnswers[$idx] = $val;
            }
            $session->set('initial_assessment_answers', $savedAnswers);

            $offset = ($page - 1) * self::QUESTIONS_PER_PAGE;
            $currentPageMissing = [];
            for ($i = 0; $i < self::QUESTIONS_PER_PAGE; $i++) {
                $idx = $offset + $i;
                if ($idx >= self::TOTAL_QUESTIONS) {
                    break;
                }
                if ($savedAnswers[$idx] === null) {
                    $currentPageMissing[] = $idx + 1;
                }
            }
            if (!empty($currentPageMissing)) {
                $this->addFlash('error', 'Veuillez répondre à toutes les questions de cette page (questions ' . implode(', ', $currentPageMissing) . ').');
                return $this->redirectToRoute('app_initial_assessment', ['page' => (string) $page]);
            }

            $isLastPage = $page >= $totalPages;
            if (!$isLastPage) {
                return $this->redirectToRoute('app_initial_assessment', ['page' => (string) ($page + 1)]);
            }

            $answers = array_filter($savedAnswers, fn($v) => $v !== null);
            $scoreFinal = $this->computeScore($answers);
            $profilType = $this->getProfilType($scoreFinal);

            $mentalScore = $this->sumSection($answers, 0, 9);
            $physicalScore = $this->sumSection($answers, 10, 19);
            $totalBrut = array_sum($answers);
            $distribution = $this->getAnswerDistribution($answers);

            $profile = new ProfilPsychologique();
            $profile->setScoreGlobal($scoreFinal);
            $profile->setProfilType($profilType);
            $profile->setDateEvaluation(new \DateTime());
            $profile->setUtilisateur($user);

            $aiFeedback = $this->aiEmotionalInsightService->generateFromAssessment([
                'mentalScore' => $mentalScore,
                'physicalScore' => $physicalScore,
                'totalScore' => $totalBrut,
                'finalScore' => $scoreFinal,
                'profilType' => $profilType,
                'distribution' => $distribution,
            ]);
            if ($aiFeedback !== null && $aiFeedback !== '') {
                $profile->setAiFeedback($aiFeedback);
            }

            $user->setProfilPsychologique($profile);
            $this->entityManager->persist($profile);
            $this->entityManager->flush();
            $session->remove('initial_assessment_answers');
            $this->addFlash('success', 'Évaluation enregistrée. Votre profil : ' . $profilType . '.');
            $this->addFlash('assessment_score', (string) $scoreFinal);
            $this->addFlash('assessment_profil_type', $profilType);
            return $this->redirectToRoute('user_dashboard');
        }

        $offset = ($page - 1) * self::QUESTIONS_PER_PAGE;
        $questionsOnPage = array_slice($allQuestions, $offset, self::QUESTIONS_PER_PAGE, true);

        return $this->render('assessment/initial_assessment.html.twig', [
            'questions' => $questionsOnPage,
            'likert_labels' => self::LIKERT_LABELS,
            'current_page' => $page,
            'total_pages' => $totalPages,
            'total_questions' => self::TOTAL_QUESTIONS,
            'question_offset' => $offset,
            'saved_answers' => $savedAnswers,
        ]);
    }

    private function getAllQuestionsList(): array
    {
        $list = [];
        $idx = 0;
        foreach (self::QUESTIONS['mental'] as $q) {
            $list[$idx++] = ['section' => 'mental', 'text' => $q];
        }
        foreach (self::QUESTIONS['physical'] as $q) {
            $list[$idx++] = ['section' => 'physical', 'text' => $q];
        }
        return $list;
    }

    private function collectPageAnswersFromRequest(Request $request, int $page): array
    {
        $offset = ($page - 1) * self::QUESTIONS_PER_PAGE;
        $answers = [];
        for ($i = 0; $i < self::QUESTIONS_PER_PAGE; $i++) {
            $idx = $offset + $i;
            if ($idx >= self::TOTAL_QUESTIONS) {
                break;
            }
            $key = 'q' . $idx;
            $val = $request->request->get($key);
            if ($val !== null && $val !== '') {
                $v = (int) $val;
                if ($v >= 0 && $v <= 4) {
                    $answers[$idx] = $v;
                }
            }
        }
        return $answers;
    }

    private function computeScore(array $answers): int
    {
        $totalBrut = array_sum($answers);
        $scoreFinal = (int) round(($totalBrut * 100) / self::MAX_RAW_SCORE);
        return max(0, min(100, $scoreFinal));
    }

    private function getProfilType(int $scoreFinal): string
    {
        if ($scoreFinal <= 25) {
            return 'Équilibre très bon';
        }
        if ($scoreFinal <= 50) {
            return 'Équilibre modéré';
        }
        if ($scoreFinal <= 75) {
            return 'Vulnérabilité moyenne';
        }
        return 'Risque élevé';
    }

    private function sumSection(array $answers, int $from, int $to): int
    {
        $sum = 0;
        for ($i = $from; $i <= $to; $i++) {
            $sum += $answers[$i] ?? 0;
        }
        return $sum;
    }

    /** @return array{low01: int, mid2: int, high34: int} */
    private function getAnswerDistribution(array $answers): array
    {
        $low01 = 0;
        $mid2 = 0;
        $high34 = 0;
        foreach ($answers as $v) {
            if ($v === 0 || $v === 1) {
                $low01++;
            } elseif ($v === 2) {
                $mid2++;
            } else {
                $high34++;
            }
        }
        return ['low01' => $low01, 'mid2' => $mid2, 'high34' => $high34];
    }
}
