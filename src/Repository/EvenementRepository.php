<?php

namespace App\Repository;

use App\Entity\Evenement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Evenement>
 */
class EvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Evenement::class);
    }

    public function findMostPopular(): array
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.participations', 'p')
            ->addSelect('COUNT(p.id) as participantCount')
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findCategoryDistribution(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type as type, COUNT(e.id) as count')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();
    }

    public function findMonthlyTrends(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql  = "SELECT MONTH(created_at) as month, COUNT(id) as count
                 FROM evenement
                 WHERE created_at >= :sixMonthsAgo
                 GROUP BY MONTH(created_at)
                 ORDER BY month ASC";
        return $conn->executeQuery($sql, [
            'sixMonthsAgo' => (new \DateTime('-6 months'))->format('Y-m-d H:i:s'),
        ])->fetchAllAssociative();
    }
}