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
     * Find active objectives for a user (paginated & optimized)
     */
    public function findActiveByUser(Utilisateur $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'en cours')
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit) // ✅ LIMIT added
            ->getQuery()
            ->getResult();
    }

    /**
     * Find objectives for user (with pagination support)
     */
    public function findByUser(Utilisateur $user, int $limit = 20): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit) // ✅ LIMIT added
            ->getQuery()
            ->getResult();
    }

    /**
     * Get distinct active objective types
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
            ->getArrayResult();

        return array_column($result, 'type');
    }

    /**
     * Count active objectives
     */
    public function countActiveByUser(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.statut = :statut')
            ->setParameter('user', $user)
            ->setParameter('statut', 'en cours')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Find objectives by type (optimized + limited)
     */
    public function findByUserAndType(Utilisateur $user, string $type, int $limit = 20): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.type = :type')
            ->setParameter('user', $user)
            ->setParameter('type', $type)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit) // ✅ LIMIT added
            ->getQuery()
            ->getResult();
    }

    /**
     * Find recent objectives (optimized + limited)
     */
    public function findRecentByUser(Utilisateur $user, int $days = 7, int $limit = 20): array
    {
        $dateLimit = (new \DateTime())->modify("-$days days");

        return $this->createQueryBuilder('o')
            ->andWhere('o.utilisateur = :user')
            ->andWhere('o.createdAt >= :dateLimit')
            ->setParameter('user', $user)
            ->setParameter('dateLimit', $dateLimit)
            ->orderBy('o.createdAt', 'DESC')
            ->setMaxResults($limit) // ✅ LIMIT added
            ->getQuery()
            ->getResult();
    }
}