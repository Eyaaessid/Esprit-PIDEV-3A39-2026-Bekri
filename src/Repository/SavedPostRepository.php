<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\SavedPost;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SavedPost>
 */
class SavedPostRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SavedPost::class);
    }

    public function findOneByUserAndPost(Utilisateur $user, Post $post): ?SavedPost
    {
        return $this->findOneBy([
            'utilisateur' => $user,
            'post' => $post,
        ]);
    }

    /**
     * @return Post[]
     */
    public function findSavedPostsForUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('sp')
            ->leftJoin('sp.post', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->leftJoin('p.likes', 'l')->addSelect('l')
            ->leftJoin('p.commentaires', 'c')->addSelect('c')
            ->where('sp.utilisateur = :userId')
            ->andWhere('p.deletedAt IS NULL')
            ->setParameter('userId', $userId)
            ->orderBy('sp.createdAt', 'DESC')
            ->getQuery()
            ->getResult();

        $posts = [];
        foreach ($rows as $row) {
            if ($row instanceof SavedPost && $row->getPost() !== null) {
                $posts[] = $row->getPost();
            }
        }

        return $posts;
    }

    /**
     * @return int[]
     */
    public function findSavedPostIdsByUser(int $userId): array
    {
        $rows = $this->createQueryBuilder('sp')
            ->select('IDENTITY(sp.post) AS postId')
            ->where('sp.utilisateur = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['postId'],
            $rows
        )));
    }

    /**
     * Users who saved at least one of the given posts.
     *
     * @param int[] $basePostIds
     * @return int[]
     */
    public function findPeerUserIdsFromSavedPosts(array $basePostIds, int $excludeUserId, int $limit = 50): array
    {
        if ($basePostIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('sp')
            ->select('IDENTITY(sp.utilisateur) AS userId')
            ->where('sp.post IN (:postIds)')
            ->andWhere('sp.utilisateur != :excludeUser')
            ->setParameter('postIds', $basePostIds)
            ->setParameter('excludeUser', $excludeUserId)
            ->groupBy('sp.utilisateur')
            ->orderBy('COUNT(sp.id)', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();

        return array_values(array_unique(array_map(
            static fn (array $row): int => (int) $row['userId'],
            $rows
        )));
    }
}
