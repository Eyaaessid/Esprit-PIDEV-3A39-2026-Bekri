<?php

namespace App\Entity;

use App\Repository\ResultatTestRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ResultatTestRepository::class)]
class ResultatTest
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $score = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $interpretation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePassage = null;

    #[ORM\ManyToOne(inversedBy: 'resultatsTests')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(inversedBy: 'resultats')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TestMental $testMental = null;

    public function __construct()
    {
        $this->datePassage = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getScore(): ?int
    {
        return $this->score;
    }

    public function setScore(int $score): static
    {
        $this->score = $score;
        return $this;
    }

    public function getInterpretation(): ?string
    {
        return $this->interpretation;
    }

    public function setInterpretation(?string $interpretation): static
    {
        $this->interpretation = $interpretation;
        return $this;
    }

    public function getDatePassage(): ?\DateTimeInterface
    {
        return $this->datePassage;
    }

    public function setDatePassage(\DateTimeInterface $datePassage): static
    {
        $this->datePassage = $datePassage;
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

    public function getTestMental(): ?TestMental
    {
        return $this->testMental;
    }

    public function setTestMental(?TestMental $testMental): static
    {
        $this->testMental = $testMental;
        return $this;
    }
}