<?php

namespace App\Controller;

use App\Entity\ReponseSuivi;
use App\Entity\SuiviQuotidien;
use App\Repository\ObjectifBienEtreRepository;
use App\Repository\QuestionEvaluationRepository;
use App\Repository\SuiviQuotidienRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/suivi')]
class SuiviQuotidienController extends AbstractController
{
    #[Route('/today', name: 'suivi_today')]
    public function today(
        Request $request,
        EntityManagerInterface $em,
        ObjectifBienEtreRepository $objectifRepo,
        QuestionEvaluationRepository $questionRepo,
        SuiviQuotidienRepository $suiviRepo
    ): Response {

        // ── 1. Get real logged-in user ────────────────────────────
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('app_login');
        }

        $today = new \DateTime('today');

        // ── 2. Block duplicate check-in ───────────────────────────
        $existingSuivi = $suiviRepo->findOneBy([
            'utilisateur' => $user,
            'date'        => $today,
        ]);

        // If already submitted today AND has responses → block
        if ($existingSuivi && $existingSuivi->getReponses()->count() > 0) {
            $this->addFlash(
                'warning',
                'Vous avez déjà complété votre check-in aujourd\'hui. Revenez demain ! 🌟'
            );
            return $this->redirectToRoute('insight_weekly');
        }

        // ── 3. Create new suivi if not exists ─────────────────────
        $suivi = $existingSuivi ?? new SuiviQuotidien();
        if (!$existingSuivi) {
            $suivi->setUtilisateur($user);
            $suivi->setDate($today);
        }

        // ── 4. Load questions based on user's active objectives ───
        $activeTypes = $objectifRepo->findActiveTypesByUser($user) ?? [];

        $questions = $questionRepo->createQueryBuilder('q')
            ->where('q.category IN (:cats)')
            ->setParameter('cats', $activeTypes)
            ->getQuery()
            ->getResult();

        // ── 5. Build form ─────────────────────────────────────────
        $formBuilder = $this->createFormBuilder($suivi);

        $formBuilder->add('commentaire', TextareaType::class, [
            'required' => false,
            'label'    => 'Commentaire libre (optionnel)',
            'constraints' => [
                new Assert\Length(
                    max: 1000,
                    maxMessage: "Le commentaire ne peut pas dépasser {{ limit }} caractères."
                ),
            ],
            'attr' => [
                'placeholder' => 'Comment s\'est passée votre journée ? (optionnel)',
                'rows' => 3,
            ],
        ]);

        foreach ($questions as $question) {
            $fieldName = 'question_' . $question->getId();

            $choices = array_filter([
                $question->getOption1() => $question->getOption1(),
                $question->getOption2() => $question->getOption2(),
                $question->getOption3() => $question->getOption3(),
            ]);

            $formBuilder->add($fieldName, ChoiceType::class, [
                'label'    => false,
                'choices'  => $choices,
                'expanded' => true,
                'multiple' => false,
                'required' => false, // HTML5 required disabled — we use Assert below
                'placeholder' => false,
                'empty_data'  => null,
                'mapped'      => false,
                'constraints' => [
                    new Assert\NotNull(
                        message: "Veuillez sélectionner une réponse pour cette question."
                    ),
                    new Assert\Choice(
                        choices: array_values($choices),
                        message: "La réponse sélectionnée n'est pas valide."
                    ),
                ],
            ]);
        }

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        // ── 6. Process submission ─────────────────────────────────
        if ($form->isSubmitted() && $form->isValid()) {

            foreach ($questions as $question) {
                $fieldName = 'question_' . $question->getId();
                $valeur    = $form->get($fieldName)->getData();

                $reponse = $suivi->getReponses()->filter(
                    fn(ReponseSuivi $r) => $r->getQuestion() === $question
                )->first();

                if (!$reponse) {
                    $reponse = new ReponseSuivi();
                    $reponse->setQuestion($question);
                    $suivi->addReponse($reponse);
                }

                $reponse->setValeur((string) $valeur);
            }

            $em->persist($suivi);
            $em->flush();

            $this->addFlash('success', 'Check-in du jour enregistré avec succès ! 🎉');
            return $this->redirectToRoute('insight_weekly');
        }

        return $this->render('suivi/today.html.twig', [
            'form'      => $form->createView(),
            'questions' => $questions,
            'suivi'     => $suivi,
        ]);
    }
}