<?php

namespace App\Repository;

use App\Entity\ReactivationRequest;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ReactivationRequest>
 */
class ReactivationRequestRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ReactivationRequest::class);
    }

    /**
     * @return ReactivationRequest[]
     */
    public function findPendingOrderByRequestedAt(): array
    {
        return $this->createQueryBuilder('r')
            ->andWhere('r.status = :status')
            ->setParameter('status', ReactivationRequest::STATUS_PENDING)
            ->orderBy('r.requestedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
