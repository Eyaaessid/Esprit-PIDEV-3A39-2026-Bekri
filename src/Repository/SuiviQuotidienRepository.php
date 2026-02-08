<?php

namespace App\Repository;

use App\Entity\SuiviQuotidien;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<SuiviQuotidien>
 */
class SuiviQuotidienRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SuiviQuotidien::class);
    }

    /**
     * Find the daily follow-up for a specific user and date
     */
    public function findOneByUserAndDate(Utilisateur $user, \DateTimeInterface $date): ?SuiviQuotidien
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->andWhere('s.date = :date')
            ->setParameter('user', $user)
            ->setParameter('date', $date)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Get all follow-ups for a user in a date range (useful for history or charts)
     *
     * @return SuiviQuotidien[]
     */
    public function findByUserAndDateRange(
        Utilisateur $user,
        \DateTimeInterface $start,
        \DateTimeInterface $end
    ): array {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->andWhere('s.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
            ->orderBy('s.date', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get the most recent follow-up for a user
     */
    public function findMostRecentForUser(Utilisateur $user): ?SuiviQuotidien
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.utilisateur = :user')
            ->setParameter('user', $user)
            ->orderBy('s.date', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}