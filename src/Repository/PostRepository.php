<?php

namespace App\Repository;

use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Post>
 */
class PostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Post::class);
    }

    /**
     * All non-deleted posts for the list page, with author, likes and comments eager-loaded
     * to avoid N+1 queries when rendering the template.
     */
    public function findAllForList(): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Query builder for paginated posts list.
     */
    public function createListQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC');
    }

    /**
     * One post by id for the detail page, with author, likes, comments and comment authors
     * eager-loaded to avoid N+1 queries.
     */
    public function findOneForShow(int $id): ?Post
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->leftJoin('c.utilisateur', 'cu')->addSelect('cu')
            ->where('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Query used by community feed API with sorting and emotion filtering.
     */
    public function createFeedQueryBuilder(string $sort = 'most_recent', ?string $emotion = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.likes', 'l')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('COUNT(DISTINCT l.id) AS HIDDEN likesCount')
            ->addSelect('COUNT(DISTINCT c.id) AS HIDDEN commentsCount')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.id');

        if ($emotion !== null && $emotion !== '') {
            $qb->andWhere('p.emotion = :emotion')
                ->setParameter('emotion', $emotion);
        }

        switch ($sort) {
            case 'most_liked':
                $qb->orderBy('likesCount', 'DESC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;
            case 'most_commented':
                $qb->orderBy('commentsCount', 'DESC')
                    ->addOrderBy('p.createdAt', 'DESC');
                break;
            case 'most_recent':
            default:
                $qb->orderBy('p.createdAt', 'DESC');
                break;
        }

        return $qb;
    }

    public function getEmotionStats(): array
    {
        return $this->createQueryBuilder('p')
            ->select('COALESCE(p.emotion, :fallback) AS emotion, COUNT(p.id) AS total')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.emotion')
            ->setParameter('fallback', 'neutral')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }

    /**
     * Most popular recent posts (by likes + comments count), for guests or fallback.
     */
    public function findMostPopularRecent(int $limit, array $excludeIds = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')
            ->leftJoin('p.commentaires', 'c')
            ->addSelect('COUNT(DISTINCT l.id) AS HIDDEN likesCnt')
            ->addSelect('COUNT(DISTINCT c.id) AS HIDDEN commentsCnt')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.id')
            ->addGroupBy('u.id')
            ->orderBy('likesCnt', 'DESC')
            ->addOrderBy('commentsCnt', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludeIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludeIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Posts in given categories or by given author IDs, excluding given post IDs and optional author (e.g. current user).
     */
    public function findByCategoriesOrAuthors(array $categories, array $authorIds, array $excludePostIds, ?int $excludeAuthorId, int $limit): array
    {
        $categories = array_filter($categories);
        $authorIds = array_filter(array_map('intval', $authorIds));

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL');

        if ($excludePostIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludeIds)')->setParameter('excludeIds', $excludePostIds);
        }
        if ($excludeAuthorId !== null) {
            $qb->andWhere('u.id != :excludeAuthor')->setParameter('excludeAuthor', $excludeAuthorId);
        }

        $or = $qb->expr()->orX();
        if ($categories !== []) {
            $or->add($qb->expr()->in('p.categorie', ':categories'));
        }
        if ($authorIds !== []) {
            $or->add($qb->expr()->in('u.id', ':authorIds'));
        }
        if ($or->count() === 0) {
            return [];
        }
        $qb->andWhere($or);
        if ($categories !== []) {
            $qb->setParameter('categories', $categories);
        }
        if ($authorIds !== []) {
            $qb->setParameter('authorIds', $authorIds);
        }

        $qb->orderBy('p.createdAt', 'DESC')->setMaxResults($limit * 2);

        return $qb->getQuery()->getResult();
    }

    /**
     * Related to a post: same category OR same author, then recent. Excludes the given post.
     */
    public function findRelatedToPost(int $excludePostId, ?string $category, ?int $authorId, int $limit): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->andWhere('p.id != :excludeId')
            ->setParameter('excludeId', $excludePostId)
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        $or = $qb->expr()->orX();
        if ($category !== null && $category !== '') {
            $or->add('p.categorie = :category');
        }
        if ($authorId !== null) {
            $or->add('u.id = :authorId');
        }
        if ($or->count() > 0) {
            $qb->andWhere($or);
            if ($category !== null && $category !== '') {
                $qb->setParameter('category', $category);
            }
            if ($authorId !== null) {
                $qb->setParameter('authorId', $authorId);
            }
            return $qb->getQuery()->getResult();
        }

        return $this->findMostPopularRecent($limit, [$excludePostId]);
    }

    /**
     * @return int[]
     */
    public function findLikedPostIdsByUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id AS id')
            ->innerJoin('p.likes', 'l')
            ->innerJoin('l.utilisateur', 'u')
            ->where('u.id = :userId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        )));
    }

    /**
     * Collaborative filtering from likes:
     * users who liked my liked posts also liked these candidates.
     *
     * @param int[] $baseLikedPostIds
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function findCoLikedCandidates(array $baseLikedPostIds, int $userId, array $excludePostIds, int $limit): array
    {
        if ($baseLikedPostIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('candidate')
            ->leftJoin('candidate.utilisateur', 'author')->addSelect('author')
            ->leftJoin('candidate.likes', 'candidateLikes')->addSelect('candidateLikes')
            ->leftJoin('candidate.commentaires', 'candidateComments')->addSelect('candidateComments')
            ->innerJoin('candidate.likes', 'peerLike')
            ->innerJoin('peerLike.utilisateur', 'peerUser')
            ->innerJoin('peerUser.likes', 'peerBaseLike')
            ->innerJoin('peerBaseLike.post', 'basePost')
            ->where('candidate.deletedAt IS NULL')
            ->andWhere('basePost.id IN (:baseLikedPostIds)')
            ->andWhere('peerUser.id != :userId')
            ->groupBy('candidate.id')
            ->addGroupBy('author.id')
            ->orderBy('COUNT(DISTINCT peerUser.id)', 'DESC')
            ->addOrderBy('COUNT(DISTINCT candidateComments.id)', 'DESC')
            ->addOrderBy('candidate.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('baseLikedPostIds', $baseLikedPostIds)
            ->setParameter('userId', $userId);

        if ($excludePostIds !== []) {
            $qb->andWhere('candidate.id NOT IN (:excludePostIds)')
                ->setParameter('excludePostIds', $excludePostIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $peerUserIds
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function findSavedByPeerUsersCandidates(array $peerUserIds, array $excludePostIds, int $limit): array
    {
        if ($peerUserIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->innerJoin(\App\Entity\SavedPost::class, 'sp', 'WITH', 'sp.post = p')
            ->where('p.deletedAt IS NULL')
            ->andWhere('sp.utilisateur IN (:peerUserIds)')
            ->groupBy('p.id')
            ->addGroupBy('u.id')
            ->orderBy('COUNT(sp.id)', 'DESC')
            ->addOrderBy('COUNT(DISTINCT c.id)', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setParameter('peerUserIds', $peerUserIds)
            ->setMaxResults($limit);

        if ($excludePostIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludePostIds)')
                ->setParameter('excludePostIds', $excludePostIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param string[] $categories
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function findByCategoriesOrderedByComments(array $categories, array $excludePostIds, int $limit, ?int $excludeAuthorId = null): array
    {
        $categories = array_values(array_unique(array_filter($categories)));
        if ($categories === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->andWhere('p.categorie IN (:categories)')
            ->setParameter('categories', $categories)
            ->groupBy('p.id')
            ->addGroupBy('u.id')
            ->orderBy('COUNT(DISTINCT c.id)', 'DESC')
            ->addOrderBy('COUNT(DISTINCT l.id)', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludePostIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludePostIds)')
                ->setParameter('excludePostIds', $excludePostIds);
        }
        if ($excludeAuthorId !== null) {
            $qb->andWhere('u.id != :excludeAuthorId')
                ->setParameter('excludeAuthorId', $excludeAuthorId);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function findMostCommentedRecent(int $limit, array $excludePostIds = []): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.id')
            ->addGroupBy('u.id')
            ->orderBy('COUNT(DISTINCT c.id)', 'DESC')
            ->addOrderBy('COUNT(DISTINCT l.id)', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludePostIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludePostIds)')
                ->setParameter('excludePostIds', $excludePostIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[] $userIds
     * @return string[]
     */
    public function findDistinctCategoriesByAuthorIds(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('DISTINCT p.categorie AS categorie')
            ->innerJoin('p.utilisateur', 'u')
            ->where('u.id IN (:userIds)')
            ->andWhere('p.deletedAt IS NULL')
            ->andWhere('p.categorie IS NOT NULL')
            ->setParameter('userIds', $userIds)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_filter(array_map(
            static fn (array $row): string => (string) $row['categorie'],
            $rows
        ))));
    }

    /**
     * @return int[]
     */
    public function findPostIdsByAuthor(int $userId): array
    {
        $rows = $this->createQueryBuilder('p')
            ->select('p.id AS id')
            ->innerJoin('p.utilisateur', 'u')
            ->where('u.id = :userId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['id'],
            $rows
        )));
    }

    /**
     * Posts with real engagement only (likes or comments).
     *
     * @param int[] $excludePostIds
     * @return Post[]
     */
    public function findEngagedRecent(int $limit, array $excludePostIds = [], ?int $excludeAuthorId = null): array
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('p.deletedAt IS NULL')
            ->groupBy('p.id')
            ->addGroupBy('u.id')
            ->having('COUNT(DISTINCT l.id) > 0 OR COUNT(DISTINCT c.id) > 0')
            ->orderBy('COUNT(DISTINCT c.id)', 'DESC')
            ->addOrderBy('COUNT(DISTINCT l.id)', 'DESC')
            ->addOrderBy('p.createdAt', 'DESC')
            ->setMaxResults($limit);

        if ($excludePostIds !== []) {
            $qb->andWhere('p.id NOT IN (:excludePostIds)')
                ->setParameter('excludePostIds', $excludePostIds);
        }
        if ($excludeAuthorId !== null) {
            $qb->andWhere('u.id != :excludeAuthorId')
                ->setParameter('excludeAuthorId', $excludeAuthorId);
        }

        return $qb->getQuery()->getResult();
    }
}
