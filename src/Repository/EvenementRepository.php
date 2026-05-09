<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use App\Enum\EvenementStatut;
use Doctrine\ORM\QueryBuilder;
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

    public function createPublicListQueryBuilder(?string $search = null): QueryBuilder
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.coach', 'c')->addSelect('c')
            ->where('e.statut IN (:statuts)')
            ->setParameter('statuts', [EvenementStatut::OPEN, EvenementStatut::PLANNED, EvenementStatut::FULL])
            ->orderBy('e.dateDebut', 'ASC');

        if ($search !== null && $search !== '') {
            $qb->andWhere('LOWER(e.titre) LIKE :search OR LOWER(COALESCE(e.description, \'\')) LIKE :search')
                ->setParameter('search', '%' . mb_strtolower($search) . '%');
        }

        return $qb;
    }

    public function createCoachListQueryBuilder(Utilisateur $coach): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->where('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('e.dateDebut', 'DESC');
    }

    public function createAdminSupervisionQueryBuilder(): QueryBuilder
    {
        return $this->createQueryBuilder('e')
            ->leftJoin('e.coach', 'c')->addSelect('c')
            ->orderBy('e.createdAt', 'DESC');
    }

    public function findOneDetailed(int $id): ?Evenement
    {
        return $this->createQueryBuilder('e')
            ->distinct()
            ->leftJoin('e.coach', 'c')->addSelect('c')
            ->leftJoin('e.participations', 'p')->addSelect('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('e.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Evenement[]
     */
    public function findRecentForCoach(Utilisateur $coach, int $limit = 5): array
    {
        return $this->createQueryBuilder('e')
            ->where('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->orderBy('e.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array{totalEvenements:int, evenementsAVenir:int, evenementsTermines:int, totalParticipations:int}
     */
    public function getCoachDashboardStats(Utilisateur $coach): array
    {
        $eventStats = $this->createQueryBuilder('e')
            ->select('COUNT(e.id) AS totalEvenements')
            ->addSelect('SUM(CASE WHEN e.dateDebut > :now THEN 1 ELSE 0 END) AS evenementsAVenir')
            ->addSelect('SUM(CASE WHEN e.statut = :finished THEN 1 ELSE 0 END) AS evenementsTermines')
            ->where('e.coach = :coach')
            ->setParameter('coach', $coach)
            ->setParameter('now', new \DateTime())
            ->setParameter('finished', EvenementStatut::FINISHED)
            ->getQuery()
            ->getSingleResult();

        $totalParticipations = (int) $this->getEntityManager()->createQueryBuilder()
            ->select('COUNT(p.id)')
            ->from(\App\Entity\ParticipationEvenement::class, 'p')
            ->innerJoin('p.evenement', 'e')
            ->where('e.coach = :coach')
            ->andWhere('p.statut = :statut')
            ->setParameter('coach', $coach)
            ->setParameter('statut', \App\Enum\ParticipationStatut::INSCRIT)
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'totalEvenements' => (int) ($eventStats['totalEvenements'] ?? 0),
            'evenementsAVenir' => (int) ($eventStats['evenementsAVenir'] ?? 0),
            'evenementsTermines' => (int) ($eventStats['evenementsTermines'] ?? 0),
            'totalParticipations' => $totalParticipations,
        ];
    }

    public function findCalendarEventsData(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id, e.titre, e.dateDebut, e.dateFin, e.type')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getArrayResult();
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

    public function findMostPopularData(int $limit = 10): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.id AS id, e.titre AS titre, COUNT(p.id) AS participantCount')
            ->leftJoin('e.participations', 'p')
            ->groupBy('e.id')
            ->orderBy('participantCount', 'DESC')
            ->addOrderBy('e.dateDebut', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function findCategoryDistributionData(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type AS type, COUNT(e.id) AS count')
            ->groupBy('e.type')
            ->getQuery()
            ->getArrayResult();
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
