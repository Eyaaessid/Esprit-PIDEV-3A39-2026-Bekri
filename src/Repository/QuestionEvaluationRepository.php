<?php

namespace App\Repository;

use App\Entity\QuestionEvaluation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @return QuestionEvaluation[]
     */
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('q')
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('q.texte', 'ASC')
            ->getQuery()
            ->enableResultCache(900, 'question_evaluation_all_ordered')
            ->getResult();
    }

    /**
     * @param string[] $categories
     * @return QuestionEvaluation[]
     */
    public function findByNormalizedCategories(array $categories): array
    {
        $normalizedCategories = array_values(array_unique(array_filter(array_map(
            static fn (string $category): string => mb_strtolower(trim($category)),
            $categories
        ))));

        if ($normalizedCategories === []) {
            return [];
        }

        $cacheKey = 'question_evaluation_categories_' . md5(implode('|', $normalizedCategories));

        return $this->createQueryBuilder('q')
            ->where('LOWER(TRIM(q.category)) IN (:categories)')
            ->setParameter('categories', $normalizedCategories)
            ->orderBy('q.category', 'ASC')
            ->addOrderBy('q.texte', 'ASC')
            ->getQuery()
            ->enableResultCache(900, $cacheKey)
            ->getResult();
    }

    public function createAdminListQueryBuilder(
        ?string $search = null,
        string $sort = 'texte',
        string $direction = 'ASC'
    ): QueryBuilder {
        $allowedSort = ['texte', 'category'];
        $sortField = in_array($sort, $allowedSort, true) ? 'q.' . $sort : 'q.texte';
        $sortDirection = strtoupper($direction) === 'DESC' ? 'DESC' : 'ASC';

        $qb = $this->createQueryBuilder('q')
            ->orderBy($sortField, $sortDirection);

        if ($search !== null && $search !== '') {
            $qb->andWhere('q.texte LIKE :search OR q.category LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        return $qb;
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
