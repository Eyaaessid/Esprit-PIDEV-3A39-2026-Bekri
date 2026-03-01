<?php

namespace App\Service;

use App\Entity\Post;
use App\Entity\Utilisateur;
use App\Repository\PostRepository;
use App\Repository\SavedPostRepository;

/**
 * Recommendation strategy (strict):
 * - Collaborative likes ("users like me")
 * - Same categories as posts created by current user
 * - Only engaged posts (likes/comments) for fallback
 */
class PostRecommendationService
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly SavedPostRepository $savedPostRepository,
    ) {
    }

    /**
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function getRecommendedForUser(?Utilisateur $user, int $limit = 8, array $excludePostIds = []): array
    {
        if ($user === null || $user->getId() === null) {
            $guestRecommendations = $this->postRepository->findEngagedRecent($limit, $excludePostIds);
            if ($guestRecommendations !== []) {
                return $guestRecommendations;
            }

            // Fallback for low-engagement datasets.
            return $this->postRepository->findMostPopularRecent($limit, $excludePostIds);
        }

        $userId = $user->getId();
        $likedPostIds = $this->postRepository->findLikedPostIdsByUser($userId);
        $savedPostIds = $this->savedPostRepository->findSavedPostIdsByUser($userId);
        $myPostIds = $this->postRepository->findPostIdsByAuthor($userId);

        $excludeIds = array_values(array_unique(array_filter(array_merge(
            $excludePostIds,
            $likedPostIds,
            $savedPostIds,
            $myPostIds
        ))));

        $scored = [];
        $seen = [];

        $push = static function (array &$scored, array &$seen, array $posts, int $score, string $reason): void {
            foreach ($posts as $post) {
                if (!$post instanceof Post || $post->getId() === null) {
                    continue;
                }
                $postId = $post->getId();
                if (!isset($scored[$postId])) {
                    $scored[$postId] = ['post' => $post, 'score' => 0, 'reasons' => []];
                }
                $scored[$postId]['score'] += $score;
                $scored[$postId]['reasons'][$reason] = true;
                $seen[$postId] = true;
            }
        };

        // 1) Collaborative likes: strongest signal
        $coLiked = $this->postRepository->findCoLikedCandidates($likedPostIds, $userId, $excludeIds, $limit * 2);
        $push($scored, $seen, $coLiked, 50, 'co-liked');

        // 2) Collaborative saves: second strongest signal
        $peerUsersFromSaved = $this->savedPostRepository->findPeerUserIdsFromSavedPosts($savedPostIds, $userId, 60);
        $coSaved = $this->postRepository->findSavedByPeerUsersCandidates($peerUsersFromSaved, $excludeIds, $limit * 2);
        $push($scored, $seen, $coSaved, 40, 'co-saved');

        // 3) Same category as posts created by current user
        $interestCategories = $this->postRepository->findDistinctCategoriesByAuthorIds([$userId]);
        $interestCategories = array_values(array_unique(array_filter($interestCategories)));
        $byCategory = $this->postRepository->findByCategoriesOrderedByComments($interestCategories, $excludeIds, $limit * 2, $userId);
        $push($scored, $seen, $byCategory, 30, 'same-category');

        // 4) Strict fallback: engaged posts only (likes/comments > 0)
        $engaged = $this->postRepository->findEngagedRecent($limit * 2, $excludeIds, $userId);
        $push($scored, $seen, $engaged, 20, 'engaged');

        if ($scored === []) {
            $popular = $this->postRepository->findMostPopularRecent($limit, $excludeIds);
            if ($popular !== []) {
                return $popular;
            }

            // Relax feed-only exclusions if the dataset is small.
            $strictPersonalExcludes = array_values(array_unique(array_filter(array_merge(
                $likedPostIds,
                $savedPostIds,
                $myPostIds
            ))));

            return $this->postRepository->findMostPopularRecent($limit, $strictPersonalExcludes);
        }

        uasort($scored, static function (array $a, array $b): int {
            if ($a['score'] === $b['score']) {
                return ($b['post']->getCreatedAt() <=> $a['post']->getCreatedAt());
            }
            return $b['score'] <=> $a['score'];
        });

        $result = [];
        foreach ($scored as $row) {
            $post = $row['post'];
            if ($post instanceof Post) {
                $result[] = $post;
            }
            if (\count($result) >= $limit) {
                break;
            }
        }

        if (\count($result) >= $limit) {
            return $result;
        }

        // Top up when scoring produced too few items.
        $currentIds = array_values(array_unique(array_filter(array_map(
            static fn (Post $post): ?int => $post->getId(),
            $result
        ))));

        $topUpExcludeIds = array_values(array_unique(array_merge($excludeIds, $currentIds)));
        $topUp = $this->postRepository->findMostPopularRecent($limit, $topUpExcludeIds);
        foreach ($topUp as $post) {
            if (!$post instanceof Post) {
                continue;
            }
            $result[] = $post;
            if (\count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    /**
     * @return Post[]
     */
    public function getRelatedToPost(Post $post, int $limit = 5): array
    {
        $id = $post->getId();
        if ($id === null) {
            return [];
        }
        $category = $post->getCategorie();
        $authorId = $post->getUtilisateur()?->getId();

        return $this->postRepository->findRelatedToPost($id, $category, $authorId, $limit);
    }
}
