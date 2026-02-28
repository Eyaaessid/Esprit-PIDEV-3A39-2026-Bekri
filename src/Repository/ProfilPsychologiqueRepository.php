<?php

namespace App\Repository;

use App\Entity\ProfilPsychologique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ProfilPsychologique>
 */
class ProfilPsychologiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ProfilPsychologique::class);
    }
}
