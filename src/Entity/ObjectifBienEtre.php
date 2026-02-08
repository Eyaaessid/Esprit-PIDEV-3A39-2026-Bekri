<?php

namespace App\Entity;

use App\Repository\ObjectifBienEtreRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ObjectifBienEtreRepository::class)]
class ObjectifBienEtre
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'objectifBienEtres')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(type: Types::FLOAT)]
    private ?float $valeurCible = null;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $valeurActuelle = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(length: 50)]
    private ?string $statut = null;          // ← I renamed status → statut (more consistent with your other enums)

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, SuiviQuotidien>
     */
    #[ORM\OneToMany(targetEntity: SuiviQuotidien::class, mappedBy: 'objectif', orphanRemoval: true)]
    private Collection $suivisQuotidiens;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->suivisQuotidiens = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
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

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getValeurCible(): ?float
    {
        return $this->valeurCible;
    }

    public function setValeurCible(?float $valeurCible): static
    {
        $this->valeurCible = $valeurCible;
        return $this;
    }

    public function getValeurActuelle(): ?float
    {
        return $this->valeurActuelle;
    }

    public function setValeurActuelle(?float $valeurActuelle): static
    {
        $this->valeurActuelle = $valeurActuelle;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
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

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeInterface
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeInterface $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, SuiviQuotidien>
     */
    public function getSuivisQuotidiens(): Collection
    {
        return $this->suivisQuotidiens;
    }

    public function addSuiviQuotidien(SuiviQuotidien $suiviQuotidien): static
    {
        if (!$this->suivisQuotidiens->contains($suiviQuotidien)) {
            $this->suivisQuotidiens->add($suiviQuotidien);
            $suiviQuotidien->setObjectif($this);
        }

        return $this;
    }

    public function removeSuiviQuotidien(SuiviQuotidien $suiviQuotidien): static
    {
        if ($this->suivisQuotidiens->removeElement($suiviQuotidien)) {
            if ($suiviQuotidien->getObjectif() === $this) {
                $suiviQuotidien->setObjectif(null);
            }
        }

        return $this;
    }
}