<?php

namespace App\Repository;

use App\Entity\Commentaire;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Commentaire>
 */
class CommentaireRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Commentaire::class);
    }

    public function createFeedQueryBuilder(string $sort = 'most_recent', ?int $postId = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.post', 'p')
            ->addSelect('p')
            ->where('c.deletedAt IS NULL')
            ->andWhere('p.deletedAt IS NULL');

        if ($postId !== null) {
            $qb->andWhere('p.id = :postId')
                ->setParameter('postId', $postId);
        }

        if ($sort === 'oldest') {
            $qb->orderBy('c.createdAt', 'ASC');
        } else {
            $qb->orderBy('c.createdAt', 'DESC');
        }

        return $qb;
    }
}
