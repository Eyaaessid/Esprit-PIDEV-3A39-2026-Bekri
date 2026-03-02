<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Entity\Utilisateur;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/posts', name: 'admin_post_')]
class PostController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private PostRepository $postRepository
    ) {
    }

    /**
     * List all posts
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $limit = 20;
        
        // Get posts with pagination
        $query = $this->postRepository->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery();
        
        $posts = $query->getResult();
        
        // Get total count for pagination
        $totalPosts = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();
        
        $totalPages = ceil($totalPosts / $limit);

        return $this->render('admin/posts/index.html.twig', [
            'posts' => $posts,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalPosts' => $totalPosts,
        ]);
    }

    /**
     * View a single post - redirects to front-end
     */
    #[Route('/{id}', name: 'view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function view(Post $post): Response
    {
        if ($post->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Post not found');
        }

        // Redirect to front-end post page
        // Change 'post_show' to match your actual front-end route name
        return $this->redirectToRoute('posts_list', ['id' => $post->getId()]);
        
        // OR if you want to stay in admin and show basic info:
        // return $this->render('admin/posts/view.html.twig', [
        //     'post' => $post,
        // ]);
    }

    /**
     * Show create post form
     */
    #[Route('/create', name: 'create', methods: ['GET'])]
    public function create(): Response
    {
        // For now, redirect back to dashboard with a message
        $this->addFlash('info', 'Create post functionality coming soon.');
        return $this->redirectToRoute('admin_tables');
        
        // TODO: Create the form template later
        // return $this->render('admin/posts/create.html.twig');
    }

    /**
     * Store new post
     */
    #[Route('/store', name: 'store', methods: ['POST'])]
    public function store(Request $request): Response
    {
        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');
        $categorie = $request->request->get('categorie');
        $mediaUrl = $request->request->get('mediaUrl');

        // Validation
        if (empty($titre) || empty($contenu)) {
            $this->addFlash('error', 'Title and content are required.');
            return $this->redirectToRoute('admin_post_create');
        }

        // Get current user (you'll need to implement authentication)
        /** @var \App\Entity\Utilisateur $user */
        $user = $this->getUser();
        if (!$user) {
            throw $this->createAccessDeniedException('You must be logged in to create a post.');
        }

        $post = new Post();
        $post->setTitre($titre);
        $post->setContenu($contenu);
        $post->setCategorie($categorie);
        $post->setMediaUrl($mediaUrl);
        $post->setUtilisateur($user);

        $this->entityManager->persist($post);
        $this->entityManager->flush();

        $this->addFlash('success', 'Post created successfully!');
        
        return $this->redirectToRoute('admin_post_view', ['id' => $post->getId()]);
    }

    /**
     * Show edit post form
     */
    #[Route('/{id}/edit', name: 'edit', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function edit(Post $post): Response
    {
        if ($post->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Post not found');
        }

        // For now, redirect back to dashboard with a message
        $this->addFlash('info', 'Edit functionality coming soon. Post ID: ' . $post->getId());
        return $this->redirectToRoute('admin_tables');
        
        // TODO: Create the edit template later
        // return $this->render('admin/posts/edit.html.twig', [
        //     'post' => $post,
        // ]);
    }

    /**
     * Update post
     */
    #[Route('/{id}/update', name: 'update', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function update(Request $request, Post $post): Response
    {
        if ($post->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Post not found');
        }

        $titre = $request->request->get('titre');
        $contenu = $request->request->get('contenu');
        $categorie = $request->request->get('categorie');
        $mediaUrl = $request->request->get('mediaUrl');

        // Validation
        if (empty($titre) || empty($contenu)) {
            $this->addFlash('error', 'Title and content are required.');
            return $this->redirectToRoute('admin_post_edit', ['id' => $post->getId()]);
        }

        $post->setTitre($titre);
        $post->setContenu($contenu);
        $post->setCategorie($categorie);
        $post->setMediaUrl($mediaUrl);
        $post->setUpdatedAt(new \DateTime());

        $this->entityManager->flush();

        $this->addFlash('success', 'Post updated successfully!');
        
        return $this->redirectToRoute('admin_post_view', ['id' => $post->getId()]);
    }

    /**
     * Soft delete post (AJAX)
     */
    #[Route('/{id}/delete', name: 'delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function delete(Post $post): JsonResponse
    {
        try {
            if ($post->getDeletedAt() !== null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Post already deleted'
                ], 404);
            }

            // Soft delete - set deletedAt timestamp
            $post->setDeletedAt(new \DateTime());
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Post deleted successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Permanently delete post (use with caution)
     */
    #[Route('/{id}/permanent-delete', name: 'permanent_delete', methods: ['DELETE'], requirements: ['id' => '\d+'])]
    public function permanentDelete(Post $post): JsonResponse
    {
        try {
            // Remove all associated comments first
            foreach ($post->getCommentaires() as $commentaire) {
                $this->entityManager->remove($commentaire);
            }

            // Remove all associated likes
            foreach ($post->getLikes() as $like) {
                $this->entityManager->remove($like);
            }

            // Remove the post
            $this->entityManager->remove($post);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Post permanently deleted'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Restore soft-deleted post
     */
    #[Route('/{id}/restore', name: 'restore', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function restore(Post $post): JsonResponse
    {
        try {
            if ($post->getDeletedAt() === null) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Post is not deleted'
                ], 400);
            }

            $post->setDeletedAt(null);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => 'Post restored successfully'
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error restoring post: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Bulk delete posts
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
                    'message' => 'No posts selected'
                ], 400);
            }

            $posts = $this->postRepository->findBy(['id' => $ids]);
            $count = 0;

            foreach ($posts as $post) {
                if ($post->getDeletedAt() === null) {
                    $post->setDeletedAt(new \DateTime());
                    $count++;
                }
            }

            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => "$count post(s) deleted successfully"
            ]);
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Error deleting posts: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get post statistics
     */
    #[Route('/stats', name: 'stats', methods: ['GET'])]
    public function stats(): JsonResponse
    {
        $totalPosts = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->getQuery()
            ->getSingleScalarResult();

        $postsByCategory = $this->postRepository->createQueryBuilder('p')
            ->select('p.categorie, COUNT(p.id) as count')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.categorie')
            ->getQuery()
            ->getResult();

        $recentPosts = $this->postRepository->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.deletedAt IS NULL')
            ->andWhere('p.createdAt >= :date')
            ->setParameter('date', new \DateTime('-7 days'))
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse([
            'totalPosts' => $totalPosts,
            'postsByCategory' => $postsByCategory,
            'recentPosts' => $recentPosts,
        ]);
    }
}