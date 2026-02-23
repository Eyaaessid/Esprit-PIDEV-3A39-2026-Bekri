<?php

namespace App\Repository;

use App\Entity\ParticipationEvenement;
use App\Entity\Utilisateur;
use App\Entity\Evenement;
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

    /**
     * Trouve une participation active pour un utilisateur et un événement
     */
    public function findActiveParticipation(Utilisateur $utilisateur, Evenement $evenement): ?ParticipationEvenement
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.utilisateur = :utilisateur')
            ->andWhere('p.evenement = :evenement')
            ->andWhere('p.statut = :statut')
            ->setParameter('utilisateur', $utilisateur)
            ->setParameter('evenement', $evenement)
            ->setParameter('statut', 'confirmé')
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Récupère l'historique des participations d'un utilisateur
     */
    public function findUserHistory(Utilisateur $utilisateur): array
    {
        return $this->createQueryBuilder('p')
            ->andWhere('p.utilisateur = :utilisateur')
            ->setParameter('utilisateur', $utilisateur)
            ->orderBy('p.dateInscription', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
