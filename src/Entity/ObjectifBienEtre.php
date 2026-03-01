<?php

namespace App\Entity;

use App\Repository\ObjectifBienEtreRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;

#[ORM\Entity(repositoryClass: ObjectifBienEtreRepository::class)]
#[ORM\HasLifecycleCallbacks]
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

    /**
     * Sluggable: automatically generates a unique URL-friendly slug from the titre.
     * Example: "Boire plus d'eau" → "boire-plus-deau"
     * The slug updates automatically when the titre changes.
     */
    #[Gedmo\Slug(fields: ['titre'])]
    #[ORM\Column(length: 255, unique: true, nullable: true)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
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
    private ?string $statut = null;

    /**
     * Timestampable: automatically set to NOW when the entity is first persisted.
     * You no longer need to call setCreatedAt() manually in your controller.
     */
    #[Gedmo\Timestampable(on: 'create')]
    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * Timestampable: automatically updated to NOW every time the entity is updated.
     * You no longer need to call setUpdatedAt() manually in your controller.
     */
    #[Gedmo\Timestampable(on: 'update')]
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

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

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    // No setSlug needed — Gedmo manages it automatically

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

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }
}