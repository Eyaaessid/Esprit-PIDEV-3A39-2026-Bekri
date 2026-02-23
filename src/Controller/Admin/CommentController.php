<?php

namespace App\Controller\Admin;

use App\Entity\Commentaire;
use App\Repository\CommentaireRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/comments', name: 'admin_comment_')]
class CommentController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private CommentaireRepository $commentaireRepository
    ) {
    }

    /**
     * List all comments
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        $filter = $request->query->get('filter', 'all'); // all, pending, approved, spam
        
        // Build query
        $queryBuilder = $this->commentaireRepository->createQueryBuilder('c')
            ->leftJoin('c.post', 'p')
            ->leftJoin('c.utilisateur', 'u')
            ->where('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC');

        // Apply filters (you may need to add a status field to your entity)
        // For now, filtering by deletedAt
        
        $query = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();
        
        $comments = $query->getResult();
        
        // Get total count
        $totalComments = $this->commentaireRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalPages = ceil($totalComments / $limit);

        return $this->render('admin/comments/index.html.twig', [
            'comments' => $comments,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalComments' => $totalComments,
            'filter' => $filter,
        ]);
    }

    /**
     * View a single comment - redirects to the post page
     */
    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(Commentaire $comment): Response
    {
        if ($comment->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Comment not found');
        }

        // Redirect to the front-end post page where this comment is displayed
        // Change 'post_show' to match your actual front-end route name
        return $this->redirectToRoute('post_details', [
            'id' => $comment->getPost()->getId()
        ]);
        
        // OR if you want to stay in admin:
        // return $this->render('admin/comments/view.html.twig', [
        //     'comment' => $comment,
        // ]);
    }

    /**
     * Show edit comment form
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(Commentaire $comment): Response
    {
        if ($comment->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Comment not found');
        }

        // For now, redirect back to dashboard with a message
        $this->addFlash('info', 'Edit functionality coming soon. Comment ID: ' . $comment->getId());
        return $this->redirectToRoute('admin_tables');
        
        // TODO: Create the edit template later
        // return $this->render('admin/comments/edit.html.twig', [
        //     'comment' => $comment,
        // ]);
    }

    /**
     * Update comment
     */
    #[Route('/{id}/update', name: 'update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Commentaire $comment): Response
    {
        if ($comment->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Comment not found');
        }

        $contenu = $request->request->get('contenu');

        // Validation
        if (empty($contenu)) {
            $this->addFlash('error', 'Content is required.');
            return $this->redirectToRoute('admin_comment_edit', ['id' => $comment->getId()]);
        }

        $comment->setContenu($contenu);
        $comment->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->addFlash('success', 'Comment updated successfully!');
        
        return $this->redirectToRoute('admin_comment_view', ['id' => $comment->getId()]);
    }

    /**
     * Soft delete comment (AJAX)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Commentaire $comment): JsonResponse
    {
        try {
            if ($comment->getDeletedAt() !== null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Comment already deleted'
                ], 404);
            }

            // Soft delete - set deletedAt timestamp
            $comment->setDeletedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete comment
     */
    #[Route('/{id}/permanent-delete', name: 'permanent_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function permanentDelete(Commentaire $comment): JsonResponse
    {
        try {
            $this->entityManager->remove($comment);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment permanently deleted'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore soft-deleted comment
     */
    #[Route('/{id}/restore', name: 'restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Commentaire $comment): JsonResponse
    {
        try {
            if ($comment->getDeletedAt() === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Comment is not deleted'
                ], 400);
            }

            $comment->setDeletedAt(null);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment restored successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error restoring comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve comment (you may want to add a status field for this)
     */
    #[Route('/{id}/approve', name: 'approve', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function approve(Commentaire $comment): JsonResponse
    {
        try {
            if ($comment->getDeletedAt() !== null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Cannot approve deleted comment'
                ], 400);
            }

            // Note: You'll need to add a 'status' field to your Commentaire entity
            // For now, we'll just update the updatedAt field
            $comment->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment approved successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error approving comment: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark comment as spam
     */
    #[Route('/{id}/spam', name: 'spam', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function markAsSpam(Commentaire $comment): JsonResponse
    {
        try {
            // You'll need to add a 'status' field to track spam
            // For now, we'll soft delete it
            $comment->setDeletedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Comment marked as spam'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error marking comment as spam: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete comments (AJAX)
     */
    #[Route('/bulk-delete', name: 'bulk_delete', methods: ['POST'])]
    public function bulkDelete(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No comments selected'
                ], 400);
            }

            $comments = $this->commentaireRepository->findBy(['id' => $ids]);
            $count = 0;

            foreach ($comments as $comment) {
                if ($comment->getDeletedAt() === null) {
                    $comment->setDeletedAt(new \DateTime());
                    $count++;
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "$count comment(s) deleted successfully"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk approve comments
     */
    #[Route('/bulk-approve', name: 'bulk_approve', methods: ['POST'])]
    public function bulkApprove(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            $ids = $data['ids'] ?? [];

            if (empty($ids)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'No comments selected'
                ], 400);
            }

            $comments = $this->commentaireRepository->findBy(['id' => $ids]);
            $count = 0;

            foreach ($comments as $comment) {
                if ($comment->getDeletedAt() === null) {
                    // Add your approval logic here
                    $comment->setUpdatedAt(new \DateTime());
                    $count++;
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "$count comment(s) approved successfully"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error approving comments: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get comments for a specific post
     */
    #[Route('/post/{postId}', name: 'by_post', methods: ['GET'], requirements: ['postId' => '\d+'])]
    public function commentsByPost(int $postId): Response
    {
        $comments = $this->commentaireRepository->createQueryBuilder('c')
            ->where('c.post = :postId')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('postId', $postId)
            ->orderBy('c.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('admin/comments/by_post.html.twig', [
            'comments' => $comments,
            'postId' => $postId,
        ]);
    }

    /**
     * Get comment statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalComments = $this->commentaireRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $recentComments = $this->commentaireRepository->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.deletedAt IS NULL')
            ->andWhere('c.createdAt >= :date')
            ->setParameter('date', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        $commentsByPost = $this->commentaireRepository->createQueryBuilder('c')
            ->select('p.titre as postTitle, COUNT(c.id) as count')
            ->leftJoin('c.post', 'p')
            ->where('c.deletedAt IS NULL')
            ->groupBy('p.id')
            ->orderBy('count', 'DESC')
            ->setMaxResults(10)
            ->getQuery()
            ->getResult();

        return new JsonResponse([
            'totalComments' => $totalComments,
            'recentComments' => $recentComments,
            'commentsByPost' => $commentsByPost,
        ]);
    }
}