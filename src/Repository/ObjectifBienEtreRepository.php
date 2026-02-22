<?php

namespace App\Repository;

use App\Entity\ObjectifBienEtre;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ObjectifBienEtre>
 */
class ObjectifBienEtreRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ObjectifBienEtre::class);
    }

    /**
     * Find all active objectives for a specific user, ordered by creation date (newest first)
     *
     * @return ObjectifBienEtre[]
     */
    public function findActiveByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'en cours')
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find all objectives for a specific user, regardless of status
     * Useful for index page, history, etc.
     *
     * @return ObjectifBienEtre[]
     */
    public function findByUser(Utilisateur $user, array $orderBy = ['createdAt' => 'DESC']): array
    {
        return $this->findBy(
            ['utilisateur' => $user],
            $orderBy
        );
    }

    /**
     * Find all distinct types of active objectives for a user
     * Very useful for dynamic daily follow-up questions filtering
     *
     * @return string[] Array of unique types (e.g. ['nutrition', 'sommeil', 'humeur'])
     */
    public function findActiveTypesByUser(Utilisateur $user): array
    {
        $result = $this->createQueryBuilder('o')
            ->select('DISTINCT o.type')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'en cours')
            ->getQuery()
            ->getResult();

        return array_column($result, 'type');
    }

    /**
     * Count the number of active objectives for a user
     */
    public function countActiveByUser(Utilisateur $user): int
    {
        return $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'en cours')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find objectives by type and user (useful for filtering or stats)
     *
     * @return ObjectifBienEtre[]
     */
    public function findByUserAndType(Utilisateur $user, string $type): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Optional: Find recently created objectives (last 7 days for example)
     */
    public function findRecentByUser(Utilisateur $user, int $days = 7): array
    {
        $dateLimit = (new \DateTime())->modify("-$days days");

        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.createdAt >= :dateLimit')
            ->setParameter('user', $user)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('o.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}