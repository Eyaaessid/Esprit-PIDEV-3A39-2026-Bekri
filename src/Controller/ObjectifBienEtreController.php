<?php

namespace App\Controller;

use App\Entity\ObjectifBienEtre;
use App\Form\ObjectifBienEtreType;
use App\Repository\ObjectifBienEtreRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

#[Route('/objectif')]
class ObjectifBienEtreController extends AbstractController
{
    #[Route('/', name: 'objectif_index')]
    public function index(
        Request $request,
        ObjectifBienEtreRepository $repo
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $search = $request->query->get('search', '');
        $searchField = $request->query->get('searchField', 'titre');
        $sort = $request->query->get('sort', 'createdAt');
        $direction = $request->query->get('direction', 'desc');

        $query = $repo->createQueryBuilder('o')
            ->where('o.utilisateur = :user')
            ->setParameter('user', $user);

        if ($search) {
            $allowedFields = ['titre', 'type', 'statut'];
            if (in_array($searchField, $allowedFields)) {
                $query->andWhere('o.' . $searchField . ' LIKE :search')
                      ->setParameter('search', '%' . $search . '%');
            }
        }

        $sortMapping = [
            'createdAt' => 'o.createdAt',
            'titre' => 'o.titre',
            'dateDebut' => 'o.dateDebut',
            'dateFin' => 'o.dateFin',
        ];

        $sortField = $sortMapping[$sort] ?? 'o.createdAt';
        $sortDir = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $query->orderBy($sortField, $sortDir);

        $objectifs = $query->getQuery()->getResult();

        return $this->render('objectif/index.html.twig', [
            'objectifs' => $objectifs,
            'search' => $search,
            'searchField' => $searchField,
            'sort' => $sort,
            'direction' => $direction,
        ]);
    }

    #[Route('/new', name: 'objectif_new', methods: ['GET', 'POST'])]
    public function new(
        Request $request,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        $objectif = new ObjectifBienEtre();

        $form = $this->createForm(ObjectifBienEtreType::class, $objectif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectif->setUtilisateur($user);
            $objectif->setCreatedAt(new \DateTimeImmutable());

            $em->persist($objectif);
            $em->flush();

            $this->addFlash('success', 'Objectif créé avec succès !');

            return $this->redirectToRoute('objectif_index');
        }

        return $this->render('objectif/new.html.twig', [
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'objectif_edit', methods: ['GET', 'POST'])]
    public function edit(
        ObjectifBienEtre $objectif,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if ($objectif->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à modifier cet objectif.');
        }

        $form = $this->createForm(ObjectifBienEtreType::class, $objectif);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $objectif->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();
            $this->addFlash('success', 'Objectif modifié !');
            return $this->redirectToRoute('objectif_index');
        }

        return $this->render('objectif/edit.html.twig', [
            'objectif' => $objectif,
            'form'     => $form->createView()
        ]);
    }

    #[Route('/{id}/delete', name: 'objectif_delete', methods: ['POST'])]
    public function delete(
        ObjectifBienEtre $objectif,
        EntityManagerInterface $em
    ): Response
    {
        $user = $this->getUser();

        if (!$user) {
            throw $this->createAccessDeniedException('Vous devez être connecté pour accéder à cette page.');
        }

        if ($objectif->getUtilisateur() !== $user) {
            throw $this->createAccessDeniedException('Vous n\'êtes pas autorisé à supprimer cet objectif.');
        }

        $em->remove($objectif);
        $em->flush();

        $this->addFlash('success', 'Objectif supprimé avec succès !');

        return $this->redirectToRoute('objectif_index');
    }
}