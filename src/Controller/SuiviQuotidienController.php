<?php

namespace App\Controller;

use App\Entity\ReponseSuivi;
use App\Entity\SuiviQuotidien;
use App\Repository\ObjectifBienEtreRepository;
use App\Repository\QuestionEvaluationRepository;
use App\Repository\SuiviQuotidienRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\RangeType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/suivi')]
class SuiviQuotidienController extends AbstractController
{
    #[Route('/today', name: 'suivi_today')]
    public function today(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $userRepo,
        ObjectifBienEtreRepository $objectifRepo,
        QuestionEvaluationRepository $questionRepo,
        SuiviQuotidienRepository $suiviRepo
    ): Response {
        $today = new \DateTime('today');
        $testUser = $userRepo->find(1);

        if (!$testUser) {
            throw $this->createNotFoundException('Utilisateur test (id=1) introuvable.');
        }

        // Récupère ou crée le suivi du jour
        $suivi = $suiviRepo->findOneBy(['utilisateur' => $testUser, 'date' => $today]);
        if (!$suivi) {
            $suivi = new SuiviQuotidien();
            $suivi->setUtilisateur($testUser);
            $suivi->setDate($today);
        }

        // Récupère les types d'objectifs actifs du user
        $activeTypes = $objectifRepo->findActiveTypesByUser($testUser);

        // Récupère les questions correspondantes
        $questions = $questionRepo->findBy(['category' => $activeTypes]);

        // Construit le formulaire dynamique
        $formBuilder = $this->createFormBuilder($suivi);
        $formBuilder->add('commentaire', TextareaType::class, [
            'label' => 'Commentaire général (facultatif)',
            'required' => false,
            'attr' => ['rows' => 4],
        ]);

        foreach ($questions as $question) {
            $fieldName = 'question_' . $question->getId();

            // Pour le moment on force le type 'choice' avec 3 options fixes
            $choices = [
                $question->getOption1() => $question->getOption1(),
                $question->getOption2() => $question->getOption2(),
                $question->getOption3() => $question->getOption3(),
            ];

            // Supprime les options vides si l'admin n'a pas rempli les 3
            $choices = array_filter($choices, fn($value) => !empty($value));

            $formBuilder->add($fieldName, ChoiceType::class, [
                'label' => $question->getTexte(),
                'choices' => $choices,
                'expanded' => true,       // Affiche en radio buttons
                'multiple' => false,      // Une seule sélection
                'required' => false,
                'placeholder' => false,   // Pas de "Choisir une option"
                'attr' => ['class' => 'space-y-2'],
            ]);
        }

        $form = $formBuilder->getForm();
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            foreach ($questions as $question) {
                $fieldName = 'question_' . $question->getId();
                $valeur = $form->get($fieldName)->getData();

                // Trouve ou crée la réponse
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

            $this->addFlash('success', 'Suivi enregistré avec succès !');
            return $this->redirectToRoute('suivi_today'); // ou 'suivi_index' quand créé
        }

        return $this->render('suivi/today.html.twig', [
            'form' => $form->createView(),
            'questions' => $questions,
            'suivi' => $suivi,
        ]);
    }

    // À implémenter plus tard : liste des suivis passés
    #[Route('', name: 'suivi_index')]
    public function index(): Response
    {
        return $this->render('suivi/index.html.twig', [
            // Vous ajouterez la logique ici plus tard
        ]);
    }
}