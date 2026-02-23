<?php

namespace App\Repository;

use App\Entity\PostNotification;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PostNotification>
 */
class PostNotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PostNotification::class);
    }

    /**
     * @return PostNotification[]
     */
    public function findForRecipient(Utilisateur $recipient): array
    {
        return $this->createQueryBuilder('n')
            ->leftJoin('n.actor', 'a')->addSelect('a')
            ->leftJoin('n.post', 'p')->addSelect('p')
            ->where('n.recipient = :recipient')
            ->setParameter('recipient', $recipient)
            ->orderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countUnreadForRecipient(Utilisateur $recipient): int
    {
        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->where('n.recipient = :recipient')
            ->andWhere('n.isRead = 0')
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function markAllReadForRecipient(Utilisateur $recipient): void
    {
        $this->createQueryBuilder('n')
            ->update()
            ->set('n.isRead', ':isRead')
            ->where('n.recipient = :recipient')
            ->andWhere('n.isRead = 0')
            ->setParameter('isRead', true)
            ->setParameter('recipient', $recipient)
            ->getQuery()
            ->execute();
    }
}
