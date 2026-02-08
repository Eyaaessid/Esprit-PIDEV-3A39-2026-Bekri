<?php

namespace App\Controller;

use App\Entity\Commentaire;
use App\Entity\Post;
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
        EntityManagerInterface $entityManager
    ): Response {
        // === TEMP TEST MODE: Force a hardcoded user (no login needed) ===
        $forcedUserId = 1;  // ← CHANGE THIS TO THE REAL ID OF john.doe@test.com !!!
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found in DB.');
            return $this->redirectToRoute('posts_list');
        }

        $this->addFlash('warning', 'TEST MODE: Using hardcoded user ID ' . $forcedUserId . ' for comment (no real login)');

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_comment', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $postId]);
        }

        // Find the post
        $post = $entityManager->getRepository(Post::class)->find($postId);
        
        if (!$post) {
            throw $this->createNotFoundException('Post not found');
        }

        // Get comment content
        $contenu = $request->request->get('contenu');
        
        if (empty(trim($contenu))) {
            $this->addFlash('error', 'Comment cannot be empty.');
            return $this->redirectToRoute('post_details', ['id' => $postId]);
        }

        // Create new comment
        $commentaire = new Commentaire();
        $commentaire->setPost($post);
        $commentaire->setUtilisateur($user);  // ← forced user
        $commentaire->setContenu($contenu);

        $entityManager->persist($commentaire);
        $entityManager->flush();

        $this->addFlash('success', 'Comment added successfully!');
        return $this->redirectToRoute('post_details', ['id' => $postId]);
    }

    

    #[Route('/{id}/delete', name: 'comment_delete', methods: ['POST'])]
    public function delete(
        Commentaire $commentaire,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        // === TEMP TEST MODE: Force a hardcoded user ===
        $forcedUserId = 1;  // ← CHANGE THIS TO THE REAL ID OF john.doe@test.com !!!
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found in DB.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $this->addFlash('warning', 'TEST MODE: Using hardcoded user ID ' . $forcedUserId . ' for comment delete (no real login)');

        // Check if user is the comment author
        if ($commentaire->getUtilisateur() !== $user) {
            $this->addFlash('error', 'You can only delete your own comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_comment' . $commentaire->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $postId = $commentaire->getPost()->getId();

        // Delete the comment
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
        // === TEMP TEST MODE: Force a hardcoded user ===
        $forcedUserId = 1;  // ← CHANGE THIS TO THE REAL ID OF john.doe@test.com !!!
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found in DB.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        $this->addFlash('warning', 'TEST MODE: Using hardcoded user ID ' . $forcedUserId . ' for comment edit (no real login)');

        // Check if user is the comment author
        if ($commentaire->getUtilisateur() !== $user) {
            $this->addFlash('error', 'You can only edit your own comments.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit_comment' . $commentaire->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        // Get new content
        $contenu = $request->request->get('contenu');
        
        if (empty(trim($contenu))) {
            $this->addFlash('error', 'Comment cannot be empty.');
            return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
        }

        // Update comment
        $commentaire->setContenu($contenu);
        $commentaire->setUpdatedAt(new \DateTime());

        $entityManager->flush();

        $this->addFlash('success', 'Comment updated successfully!');
        return $this->redirectToRoute('post_details', ['id' => $commentaire->getPost()->getId()]);
    }
}