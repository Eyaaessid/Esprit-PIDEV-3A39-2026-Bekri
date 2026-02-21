<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Post;
use App\Entity\Utilisateur;
use App\Service\PostInteractionNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/comments')]
class CommentController extends AbstractController
{
    #[Route('/create/{postId}', name: 'comment_create', methods: ['POST'])]
    public function create(
        int $postId,
        Request $request,
        EntityManagerInterface $entityManager,
        PostInteractionNotifier $postInteractionNotifier
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to comment.');
            return $this->redirectToRoute('posts_list');
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_comment', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $postId]);
        }

        $post = $entityManager->getRepository(Post::class)->find($postId);
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        $contenu = $request->request->get('contenu');
        if (empty(trim((string) $contenu))) {
            $this->addFlash('error', 'Comment cannot be empty.');
            return $this->redirectToRoute('post_details', ['id' => $postId]);
        }

        $commentaire = new Commentaire();
        $commentaire->setPost($post);
        $commentaire->setUtilisateur($authUser);
        $commentaire->setContenu($contenu);

        $entityManager->persist($commentaire);
        $entityManager->flush();

        $postInteractionNotifier->notifyPostCommented($post, $authUser, (string) $contenu);

        $this->addFlash('success', 'Comment added successfully!');
        return $this->redirectToRoute('post_details', ['id' => $postId]);
    }

    #[Route('/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(
        Commentaire $commentaire,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to delete comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $commentaire->getUtilisateur() !== $authUser) {
            $this->addFlash('error', 'You can only delete your own comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_comment' . $commentaire->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $postId = $commentaire->getPost()->getId();
        $entityManager->remove($commentaire);
        $entityManager->flush();

        $this->addFlash('success', 'Comment deleted successfully!');
        return $this->redirectToRoute('post_details', ['id' => $postId]);
    }

    #[Route('/{id}/edit', name: 'comment_edit', methods: ['POST'])]
    public function edit(
        Commentaire $commentaire,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to edit comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $commentaire->getUtilisateur() !== $authUser) {
            $this->addFlash('error', 'You can only edit your own comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit_comment' . $commentaire->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $contenu = $request->request->get('contenu');
        if (empty(trim((string) $contenu))) {
            $this->addFlash('error', 'Comment cannot be empty.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $commentaire->setContenu($contenu);
        $commentaire->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Comment updated successfully!');
        return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
    }
}
