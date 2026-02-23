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

    public function findMostPopular(int $limit = 6): array
    {
        return $this->createQueryBuilder('e')
            ->select('e as evenement', 'COUNT(p.id) as participantCount')
            ->leftJoin('e.participations', 'p', 'WITH', 'p.statut = :statut')
            ->setParameter('statut', 'confirmé')
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function searchByTerm(string $term): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.titre LIKE :term OR e.description LIKE :term OR e.lieu LIKE :term OR e.type LIKE :term')
            ->setParameter('term', '%' . $term . '%')
            ->orderBy('e.dateDebut', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findStatsByCategory(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type', 'COUNT(e.id) as count')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();
    }

    public function findStatsByMonth(): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = 'SELECT MONTH(date_debut) as month, COUNT(id) as count 
                FROM evenement 
                WHERE date_debut >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                GROUP BY month 
                ORDER BY month ASC';
        
        return $conn->executeQuery($sql)->fetchAllAssociative();
    }
}
