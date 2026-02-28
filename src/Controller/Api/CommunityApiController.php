<?php

namespace App\Controller\Api;

use App\Entity\Commentaire;
use App\Entity\Post;
use App\Repository\CommentaireRepository;
use App\Repository\PostRepository;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/community', name: 'api_community_')]
class CommunityApiController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentaireRepository $commentaireRepository,
        private readonly PaginatorInterface $paginator,
    ) {
    }

    /**
     * KNP paginator integration for posts with sort/filter options:
     * - sort: most_recent|most_liked|most_commented
     * - emotion: optional emotion filter
     */
    #[Route('/posts', name: 'posts', methods: ['GET'])]
    public function posts(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(50, max(1, (int) $request->query->get('limit', 10)));
        $sort = (string) $request->query->get('sort', 'most_recent');
        $emotion = $request->query->get('emotion');
        $emotion = is_string($emotion) ? $emotion : null;

        $qb = $this->postRepository->createFeedQueryBuilder($sort, $emotion);
        $pagination = $this->paginator->paginate($qb, $page, $limit);

        $items = [];
        foreach ($pagination->getItems() as $post) {
            if (!$post instanceof Post) {
                continue;
            }
            $author = $post->getUtilisateur();
            $items[] = [
                'id' => $post->getId(),
                'titre' => $post->getTitre(),
                'contenu' => $post->getContenu(),
                'categorie' => $post->getCategorie(),
                'emotion' => $post->getEmotion(),
                'risk_level' => $post->getRiskLevel(),
                'is_sensitive' => $post->isSensitive(),
                'created_at' => $post->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'likes_count' => $post->getLikesCount(),
                'comments_count' => $post->getCommentsCount(),
                'author' => $author ? [
                    'id' => $author->getId(),
                    'nom' => $author->getNom(),
                    'prenom' => $author->getPrenom(),
                    'role' => $author->getRole()->value,
                ] : null,
            ];
        }

        return $this->json([
            'items' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $pagination->getTotalItemCount(),
                'pages' => (int) ceil($pagination->getTotalItemCount() / $limit),
                'sort' => $sort,
                'emotion' => $emotion,
            ],
        ]);
    }

    #[Route('/comments', name: 'comments', methods: ['GET'])]
    public function comments(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $sort = (string) $request->query->get('sort', 'most_recent');
        $postId = $request->query->getInt('post_id', 0);
        $postId = $postId > 0 ? $postId : null;

        $qb = $this->commentaireRepository->createFeedQueryBuilder($sort, $postId);
        $pagination = $this->paginator->paginate($qb, $page, $limit);

        $items = [];
        foreach ($pagination->getItems() as $comment) {
            if (!$comment instanceof Commentaire) {
                continue;
            }
            $author = $comment->getUtilisateur();
            $items[] = [
                'id' => $comment->getId(),
                'contenu' => $comment->getContenu(),
                'created_at' => $comment->getCreatedAt()->format(\DateTimeInterface::ATOM),
                'post_id' => $comment->getPost()?->getId(),
                'author' => $author ? [
                    'id' => $author->getId(),
                    'nom' => $author->getNom(),
                    'prenom' => $author->getPrenom(),
                    'role' => $author->getRole()->value,
                ] : null,
            ];
        }

        return $this->json([
            'items' => $items,
            'meta' => [
                'page' => $page,
                'limit' => $limit,
                'total' => $pagination->getTotalItemCount(),
                'pages' => (int) ceil($pagination->getTotalItemCount() / $limit),
                'sort' => $sort,
                'post_id' => $postId,
            ],
        ]);
    }

    #[Route('/dashboard', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): JsonResponse
    {
        return $this->json([
            'emotions' => $this->postRepository->getEmotionStats(),
        ]);
    }
}
