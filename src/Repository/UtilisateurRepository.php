<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * Optimized: Load all users with ProfilPsychologique
     * Uses INNER JOIN because FK is NOT NULL.
     */
    public function findAllWithProfil(): array
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.profilPsychologique', 'p')
            ->addSelect('p')
            ->getQuery()
            ->getResult();
    }

    /**
     * Optimized: Load one user with ProfilPsychologique
     */
    public function findOneWithProfil(int $id): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->innerJoin('u.profilPsychologique', 'p')
            ->addSelect('p')
            ->where('u.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}