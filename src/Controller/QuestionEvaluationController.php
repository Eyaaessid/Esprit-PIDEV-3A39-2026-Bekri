<?php

namespace App\Controller;

use App\Entity\QuestionEvaluation;
use App\Form\QuestionEvaluationType;
use App\Repository\QuestionEvaluationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/questions')]
#[IsGranted('ROLE_ADMIN')]  // ← Restrict the entire controller to admins
class QuestionEvaluationController extends AbstractController
{
    #[Route('', name: 'admin_question_index', methods: ['GET'])]
    public function index(
        Request $request,
        QuestionEvaluationRepository $repo
    ): Response
    {
        // No need to check user here anymore — the attribute takes care of it
        // But you can still add it if you want a custom message
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $search = $request->query->get('search', '');
        $sort = $request->query->get('sort', 'texte');
        $direction = $request->query->get('direction', 'asc');

        $query = $repo->createQueryBuilder('q');

        if ($search) {
            $query->andWhere('q.texte LIKE :search OR q.category LIKE :search')
                  ->setParameter('search', '%' . $search . '%');
        }

        $allowedSort = ['texte', 'category'];
        $sortField = in_array($sort, $allowedSort) ? 'q.' . $sort : 'q.texte';
        $sortDir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $query->orderBy($sortField, $sortDir);

        $questions = $query->getQuery()->getResult();

        return $this->render('admin/question/index.html.twig', [
            'questions' => $questions,
            'search' => $search,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'admin_question_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $question = new QuestionEvaluation();

        $form = $this->createForm(QuestionEvaluationType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($question);
            $em->flush();

            $this->addFlash('success', 'Question créée avec succès !');
            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('admin/question/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'admin_question_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request,
        QuestionEvaluation $question,
        EntityManagerInterface $em
    ): Response
    {
        $form = $this->createForm(QuestionEvaluationType::class, $question);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();

            $this->addFlash('success', 'Question modifiée avec succès !');
            return $this->redirectToRoute('admin_question_index');
        }

        return $this->render('admin/question/edit.html.twig', [
            'form'     => $form->createView(),
            'question' => $question,
        ]);
    }

    #[Route('/{id}/delete', name: 'admin_question_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        QuestionEvaluation $question,
        EntityManagerInterface $em
    ): Response
    {
        if (!$this->isCsrfTokenValid('delete' . $question->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide');
        }

        $em->remove($question);
        $em->flush();

        $this->addFlash('success', 'Question supprimée avec succès !');

        return $this->redirectToRoute('admin_question_index');
    }
}