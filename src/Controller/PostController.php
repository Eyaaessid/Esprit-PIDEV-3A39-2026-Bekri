<?php

namespace App\Controller;

use App\Entity\Like;
use App\Entity\Post;
use App\Entity\SavedPost;
use App\Entity\Utilisateur;
use App\Repository\PostNotificationRepository;
use App\Repository\PostRepository;
use App\Repository\SavedPostRepository;
use App\Service\EmotionalAnalysisService;
use App\Service\PostInteractionNotifier;
use App\Service\PostRecommendationService;
use App\Service\PostRiskAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/posts')]
class PostController extends AbstractController
{
    #[Route('', name: 'posts_list', methods: ['GET'])]
    public function index(
        Request $request,
        PostRepository $postRepository,
        PostRecommendationService $recommendationService,
        SavedPostRepository $savedPostRepository,
        PaginatorInterface $paginator,
        PostNotificationRepository $postNotificationRepository
    ): Response
    {
        $postsQuery = $postRepository->createListQueryBuilder();
        $posts = $paginator->paginate(
            $postsQuery,
            max(1, $request->query->getInt('page', 1)),
            6
        );
        $savedPostIds = [];
        $unreadNotificationsCount = 0;
        $authUser = $this->getUser();
        if ($authUser instanceof Utilisateur && $authUser->getId() !== null) {
            $savedPostIds = $savedPostRepository->findSavedPostIdsByUser($authUser->getId());
            $unreadNotificationsCount = $postNotificationRepository->countUnreadForRecipient($authUser);
        }

        /** @var Post[] $currentPagePosts */
        $currentPagePosts = $posts->getItems();
        $mainIds = array_filter(array_map(fn (Post $p) => $p->getId(), $currentPagePosts));
        $recommended = $recommendationService->getRecommendedForUser(
            $authUser instanceof Utilisateur ? $authUser : null,
            6,
            array_slice($mainIds, 0, 12)
        );

        return $this->render('posts.html.twig', [
            'posts' => $posts,
            'recommendedPosts' => $recommended,
            'savedPostIds' => $savedPostIds,
            'unreadNotificationsCount' => $unreadNotificationsCount,
        ]);
    }

    #[Route('/notifications', name: 'posts_notifications', methods: ['GET'])]
    public function notifications(
        Request $request,
        PostNotificationRepository $postNotificationRepository,
        PaginatorInterface $paginator
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur || $authUser->getId() === null) {
            $this->addFlash('error', 'You must be logged in to view notifications.');
            return $this->redirectToRoute('app_login');
        }

        $notifications = $postNotificationRepository->findForRecipient($authUser);
        $pagination = $paginator->paginate(
            $notifications,
            max(1, $request->query->getInt('page', 1)),
            10
        );

        return $this->render('post_notifications.html.twig', [
            'notifications' => $pagination,
            'unreadNotificationsCount' => $postNotificationRepository->countUnreadForRecipient($authUser),
        ]);
    }

    #[Route('/notifications/read-all', name: 'posts_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(PostNotificationRepository $postNotificationRepository): Response
    {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur || $authUser->getId() === null) {
            $this->addFlash('error', 'You must be logged in.');
            return $this->redirectToRoute('app_login');
        }

        $postNotificationRepository->markAllReadForRecipient($authUser);
        $this->addFlash('success', 'All notifications marked as read.');

        return $this->redirectToRoute('posts_notifications');
    }

    #[Route('/create', name: 'post_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        EmotionalAnalysisService $emotionalAnalysisService,
        PostRiskAlertNotifier $postRiskAlertNotifier
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to create a post.');
            return $this->redirectToRoute('posts_list');
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_post', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        $titre = trim((string) $request->request->get('titre', ''));
        $contenu = trim((string) $request->request->get('contenu', ''));
        if ($titre === '' || $contenu === '') {
            $this->addFlash('error', 'Title and content are required.');
            return $this->redirectToRoute('posts_list');
        }

        $post = new Post();
        $post->setUtilisateur($authUser);
        $post->setTitre($titre);
        $post->setContenu($contenu);

        $categorie = $request->request->get('categorie');
        if ($categorie) {
            $post->setCategorie($categorie);
        }

        // Apply AI analysis in website flow (same behavior as API flow).
        // If AI_PROVIDER=openai, this HTTP call can take 2–10+ seconds and blocks the response.
        // For faster create/edit, set AI_PROVIDER=heuristic in .env to use local keyword analysis only.
        $analysis = $emotionalAnalysisService->analyzePostContent($post->getContenu());
        $post->setEmotion($analysis->emotion);
        $post->setRiskLevel($analysis->riskLevel);
        $post->setIsSensitive($analysis->isSensitive);

        $mediaFile = $request->files->get('media');
        if ($mediaFile) {
            $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $mediaFile->guessExtension();

            try {
                $mediaFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
                $post->setMediaUrl('/uploads/posts/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Failed to upload image. Please try again.');
            }
        }

        $entityManager->persist($post);
        $entityManager->flush();

        if ($analysis->riskLevel === 'high') {
            $postRiskAlertNotifier->notifyHighRiskPost($post, $analysis->matchedSignals);
            $this->addFlash('warning', 'Your post was flagged as sensitive and the admin was notified.');
        }

        $this->addFlash('success', 'Post created successfully!');
        return $this->redirectToRoute('posts_list');
    }

    #[Route('/saved', name: 'posts_saved', methods: ['GET'])]
    public function saved(
        SavedPostRepository $savedPostRepository,
        PostRecommendationService $recommendationService
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur || $authUser->getId() === null) {
            $this->addFlash('error', 'You must be logged in to view saved posts.');
            return $this->redirectToRoute('app_login');
        }

        $savedPosts = $savedPostRepository->findSavedPostsForUser($authUser->getId());
        $savedPostIds = $savedPostRepository->findSavedPostIdsByUser($authUser->getId());

        $recommended = $recommendationService->getRecommendedForUser(
            $authUser,
            6,
            $savedPostIds
        );

        return $this->render('saved_posts.html.twig', [
            'posts' => $savedPosts,
            'recommendedPosts' => $recommended,
            'savedPostIds' => $savedPostIds,
        ]);
    }

    #[Route('/{id}', name: 'post_details', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        PostRepository $postRepository,
        EntityManagerInterface $entityManager,
        PostRecommendationService $recommendationService,
        SavedPostRepository $savedPostRepository
    ): Response
    {
        $post = $postRepository->findOneForShow($id);
        if ($post === null || $post->getDeletedAt() !== null) {
            throw $this->createNotFoundException('Post not found');
        }

        $userHasLiked = false;
        $userHasSaved = false;

        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if ($authUser instanceof Utilisateur) {
            $like = $entityManager->getRepository(Like::class)->findOneBy([
                'post' => $post,
                'utilisateur' => $authUser,
            ]);
            $userHasLiked = ($like !== null);

            $saved = $savedPostRepository->findOneBy([
                'post' => $post,
                'utilisateur' => $authUser,
            ]);
            $userHasSaved = ($saved !== null);
        }

        $relatedPosts = $recommendationService->getRelatedToPost($post, 5);

        return $this->render('post_details.html.twig', [
            'post' => $post,
            'userHasLiked' => $userHasLiked,
            'userHasSaved' => $userHasSaved,
            'relatedPosts' => $relatedPosts,
        ]);
    }

    #[Route('/{id}/edit', name: 'post_edit', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger,
        EmotionalAnalysisService $emotionalAnalysisService,
        PostRiskAlertNotifier $postRiskAlertNotifier
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to edit a post.');
            return $this->redirectToRoute('posts_list');
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $post->getUtilisateur() !== $authUser) {
            $this->addFlash('error', 'You can only edit your own posts.');
            return $this->redirectToRoute('posts_list');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit' . $post->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        $titre = trim((string) $request->request->get('titre', ''));
        $contenu = trim((string) $request->request->get('contenu', ''));
        if ($titre === '' || $contenu === '') {
            $this->addFlash('error', 'Title and content are required.');
            return $this->redirectToRoute('posts_list');
        }

        $post->setTitre($titre);
        $post->setContenu($contenu);

        $categorie = $request->request->get('categorie');
        $post->setCategorie($categorie ?: null);

        $analysis = $emotionalAnalysisService->analyzePostContent($post->getContenu());
        $post->setEmotion($analysis->emotion);
        $post->setRiskLevel($analysis->riskLevel);
        $post->setIsSensitive($analysis->isSensitive);

        $mediaFile = $request->files->get('media');
        if ($mediaFile) {
            if ($post->getMediaUrl()) {
                $oldFile = $this->getParameter('kernel.project_dir') . '/public' . $post->getMediaUrl();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename . '-' . uniqid() . '.' . $mediaFile->guessExtension();

            try {
                $mediaFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
                $post->setMediaUrl('/uploads/posts/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Failed to upload new image.');
            }
        }

        $post->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        if ($analysis->riskLevel === 'high') {
            $postRiskAlertNotifier->notifyHighRiskPost($post, $analysis->matchedSignals);
            $this->addFlash('warning', 'This post is now flagged as sensitive and the admin was notified.');
        }

        $this->addFlash('success', 'Post updated successfully!');
        return $this->redirectToRoute('posts_list');
    }

    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            $this->addFlash('error', 'You must be logged in to delete a post.');
            return $this->redirectToRoute('posts_list');
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Access denied.');
        }

        if (!$this->isGranted('ROLE_ADMIN') && $post->getUtilisateur() !== $authUser) {
            $this->addFlash('error', 'You can only delete your own posts.');
            return $this->redirectToRoute('posts_list');
        }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $post->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        $post->setDeletedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Post deleted successfully!');
        return $this->redirectToRoute('posts_list');
    }

    #[Route('/{id}/like', name: 'post_like', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function like(
        Post $post,
        Request $request,
        EntityManagerInterface $entityManager,
        PostInteractionNotifier $postInteractionNotifier
    ): Response {
        if ($post->getDeletedAt() !== null) {
            return $this->json(['error' => 'Post not found'], 404);
        }

        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $token = (string) ($request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token', ''));
        if (!$this->isCsrfTokenValid('like' . $post->getId(), $token)) {
            return $this->json(['error' => 'Invalid security token'], 400);
        }

        $existingLike = $entityManager->getRepository(Like::class)->findOneBy([
            'post' => $post,
            'utilisateur' => $authUser,
        ]);

        if ($existingLike) {
            $entityManager->remove($existingLike);
            $liked = false;
        } else {
            $like = new Like();
            $like->setPost($post);
            $like->setUtilisateur($authUser);
            $entityManager->persist($like);
            $liked = true;
        }

        $entityManager->flush();

        if ($liked) {
            $postInteractionNotifier->notifyPostLiked($post, $authUser);
        }

        $likeCount = $entityManager->getRepository(Like::class)->count(['post' => $post]);

        return $this->json([
            'liked' => $liked,
            'count' => $likeCount,
        ]);
    }

    #[Route('/{id}/save', name: 'post_save', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function save(
        Post $post,
        Request $request,
        EntityManagerInterface $entityManager,
        SavedPostRepository $savedPostRepository
    ): Response {
        if ($post->getDeletedAt() !== null) {
            return $this->json(['error' => 'Post not found'], 404);
        }

        /** @var mixed $authUser */
        $authUser = $this->getUser();
        if (!$authUser instanceof Utilisateur) {
            return $this->json(['error' => 'Authentication required'], 401);
        }

        if (!$this->isGranted('ROLE_USER') && !$this->isGranted('ROLE_ADMIN')) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $token = (string) ($request->headers->get('X-CSRF-TOKEN') ?? $request->request->get('_token', ''));
        if (!$this->isCsrfTokenValid('save' . $post->getId(), $token)) {
            return $this->json(['error' => 'Invalid security token'], 400);
        }

        $existing = $savedPostRepository->findOneByUserAndPost($authUser, $post);

        if ($existing instanceof SavedPost) {
            $entityManager->remove($existing);
            $saved = false;
        } else {
            $savedPost = new SavedPost();
            $savedPost->setPost($post);
            $savedPost->setUtilisateur($authUser);
            $entityManager->persist($savedPost);
            $saved = true;
        }

        $entityManager->flush();

        return $this->json([
            'saved' => $saved,
        ]);
    }
}
