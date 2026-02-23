<?php

namespace App\Repository;

use App\Entity\ReponseSuivi;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReponseSuivi>
 */
class ReponseSuiviRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReponseSuivi::class);
    }

    /**
     * Optional: find all responses for a given suivi
     *
     * @return ReponseSuivi[]
     */
    public function findBySuivi(int $suiviId): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.suivi = :suiviId')
            ->setParameter('suiviId', $suiviId)
            ->leftJoin('r.question', 'q')
            ->addOrderBy('q.category', 'ASC')
            ->addOrderBy('q.texte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Optional: find responses for a specific question across all suivis of a user
     * (useful later for statistics or trends)
     */
    public function findResponsesForQuestionAndUser(int $questionId, int $userId): array
    {
        return $this->createQueryBuilder('r')
            ->join('r.suivi', 's')
            ->andWhere('r.question = :questionId')
            ->andWhere('s.utilisateur = :userId')
            ->setParameter('questionId', $questionId)
            ->setParameter('userId', $userId)
            ->orderBy('s.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}