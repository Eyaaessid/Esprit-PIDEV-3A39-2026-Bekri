<?php

namespace App\Repository;

use App\Entity\Evenement;
use App\Entity\ParticipationEvenement;
use App\Entity\Utilisateur;
use App\Enum\ParticipationStatut;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ParticipationEvenement>
 */
class ParticipationEvenementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ParticipationEvenement::class);
    }

    public function countActiveByEvenement(Evenement $evenement): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.evenement = :evenement')
            ->andWhere('p.statut = :statut')
            ->setParameter('evenement', $evenement)
            ->setParameter('statut', ParticipationStatut::INSCRIT)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return ParticipationEvenement[]
     */
    public function findByUtilisateurWithEvenements(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.evenement', 'e')->addSelect('e')
            ->leftJoin('e.coach', 'c')->addSelect('c')
            ->where('p.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return ParticipationEvenement[]
     */
    public function findByEvenementWithUtilisateurs(Evenement $evenement): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('p.evenement = :evenement')
            ->setParameter('evenement', $evenement)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @param int[] $eventIds
     * @return array<int, int>
     */
    public function getActiveCountsByEventIds(array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));
        if ($eventIds === []) {
            return [];
        }

        $rows = $this->createQueryBuilder('p')
            ->select('IDENTITY(p.evenement) AS eventId')
            ->addSelect('COUNT(p.id) AS activeCount')
            ->where('p.evenement IN (:eventIds)')
            ->andWhere('p.statut = :statut')
            ->setParameter('eventIds', $eventIds)
            ->setParameter('statut', ParticipationStatut::INSCRIT)
            ->groupBy('p.evenement')
            ->getQuery()
            ->getArrayResult();

        $counts = array_fill_keys($eventIds, 0);
        foreach ($rows as $row) {
            $counts[(int) $row['eventId']] = (int) $row['activeCount'];
        }

        return $counts;
    }
}
