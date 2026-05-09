<?php

namespace App\Entity;

use App\Enum\ParticipationStatut;
use App\Repository\ParticipationEvenementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ParticipationEvenementRepository::class)]
#[ORM\Table(indexes: [
    new ORM\Index(name: 'idx_participation_event_status', columns: ['evenement_id', 'statut']),
    new ORM\Index(name: 'idx_participation_user_event', columns: ['utilisateur_id', 'evenement_id']),
    new ORM\Index(name: 'idx_participation_date', columns: ['date_inscription']),
])]
class ParticipationEvenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'participations')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Evenement $evenement = null;

    #[ORM\ManyToOne(inversedBy: 'participationsEvenements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateInscription = null;

    #[ORM\Column(length: 50, enumType: ParticipationStatut::class)]
    private ?ParticipationStatut $statut = null;

    public function __construct()
    {
        $this->dateInscription = new \DateTime();
        $this->statut = ParticipationStatut::INSCRIT;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;
        return $this;
    }

    public function getDateInscription(): ?\DateTimeInterface
    {
        return $this->dateInscription;
    }

    public function setDateInscription(\DateTimeInterface $dateInscription): static
    {
        $this->dateInscription = $dateInscription;
        return $this;
    }

    public function getStatut(): ?ParticipationStatut
    {
        return $this->statut;
    }

    public function setStatut(ParticipationStatut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }
}
