<?php

namespace App\Entity;

use App\Repository\SuiviQuotidienRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SuiviQuotidienRepository::class)]
class SuiviQuotidien
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'suivisQuotidiens')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ObjectifBienEtre $objectif = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $date;

    #[ORM\Column(type: Types::FLOAT)]
    private float $sommeil;

    #[ORM\Column(type: Types::INTEGER)]
    private int $humeur;

    #[ORM\Column(type: Types::INTEGER)]
    private int $energie;

    #[ORM\Column(type: Types::FLOAT, nullable: true)]
    private ?float $poids = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $nutrition = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getObjectif(): ?ObjectifBienEtre
    {
        return $this->objectif;
    }

    public function setObjectif(?ObjectifBienEtre $objectif): static
    {
        $this->objectif = $objectif;
        return $this;
    }

    public function getDate(): \DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getSommeil(): float
    {
        return $this->sommeil;
    }

    public function setSommeil(float $sommeil): static
    {
        $this->sommeil = $sommeil;
        return $this;
    }

    public function getHumeur(): int
    {
        return $this->humeur;
    }

    public function setHumeur(int $humeur): static
    {
        $this->humeur = $humeur;
        return $this;
    }

    public function getEnergie(): int
    {
        return $this->energie;
    }

    public function setEnergie(int $energie): static
    {
        $this->energie = $energie;
        return $this;
    }

    public function getPoids(): ?float
    {
        return $this->poids;
    }

    public function setPoids(?float $poids): static
    {
        $this->poids = $poids;
        return $this;
    }

    public function getNutrition(): ?string
    {
        return $this->nutrition;
    }

    public function setNutrition(?string $nutrition): static
    {
        $this->nutrition = $nutrition;
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
}