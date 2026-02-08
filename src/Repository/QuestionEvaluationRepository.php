<?php

namespace App\Repository;

use App\Entity\QuestionEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<QuestionEvaluation>
 */
class QuestionEvaluationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionEvaluation::class);
    }

    /**
     * Find questions by their category (used for dynamic daily follow-up)
     *
     * @param array $categories
     * @return QuestionEvaluation[]
     */
    public function findByCategories(array $categories): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.category IN (:categories)')
            ->setParameter('categories', $categories)
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('q.texte', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Optional: find questions of a specific type (e.g. only 'choice' questions)
     */
    public function findByType(string $type): array
    {
        return $this->createQueryBuilder('q')
            ->where('q.typeReponse = :type')
            ->setParameter('type', $type)
            ->getQuery()
            ->getResult();
    }
}