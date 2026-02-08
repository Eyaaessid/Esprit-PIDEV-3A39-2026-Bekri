<?php

namespace App\Entity;

use App\Enum\EvenementType;
use App\Enum\EvenementStatut;
use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50, enumType: EvenementType::class)]
    private ?EvenementType $type = null;

    #[ORM\Column]
    private ?int $capaciteMax = null;

    #[ORM\Column(length: 50, enumType: EvenementStatut::class)]
    private ?EvenementStatut $statut = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $lienSession = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'evenements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $coach = null;

    /**
     * @var Collection<int, ParticipationEvenement>
     */
    #[ORM\OneToMany(targetEntity: ParticipationEvenement::class, mappedBy: 'evenement', orphanRemoval: true)]
    private Collection $participations;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->statut = EvenementStatut::PLANNED;
        $this->participations = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getType(): ?EvenementType
    {
        return $this->type;
    }

    public function setType(EvenementType $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function getStatut(): ?EvenementStatut
    {
        return $this->statut;
    }

    public function setStatut(EvenementStatut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getLienSession(): ?string
    {
        return $this->lienSession;
    }

    public function setLienSession(?string $lienSession): static
    {
        $this->lienSession = $lienSession;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getCoach(): ?Utilisateur
    {
        return $this->coach;
    }

    public function setCoach(?Utilisateur $coach): static
    {
        $this->coach = $coach;
        return $this;
    }

    /**
     * @return Collection<int, ParticipationEvenement>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(ParticipationEvenement $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setEvenement($this);
        }
        return $this;
    }

    public function removeParticipation(ParticipationEvenement $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvenement() === $this) {
                $participation->setEvenement(null);
            }
        }
        return $this;
    }
}