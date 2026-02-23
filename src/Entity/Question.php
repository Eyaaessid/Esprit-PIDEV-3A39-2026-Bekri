<?php

namespace App\Entity;

use App\Enum\BonneReponse;
use App\Repository\QuestionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionRepository::class)]
class Question
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    private ?string $contenu = null;

    #[ORM\Column(length: 255)]
    private ?string $choixA = null;

    #[ORM\Column(length: 255)]
    private ?string $choixB = null;

    #[ORM\Column(length: 255)]
    private ?string $choixC = null;

    #[ORM\Column(length: 1, enumType: BonneReponse::class)]
    private ?BonneReponse $bonneReponse = null;

    #[ORM\ManyToOne(inversedBy: 'questions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?TestMental $testMental = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContenu(): ?string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;
        return $this;
    }

    public function getChoixA(): ?string
    {
        return $this->choixA;
    }

    public function setChoixA(string $choixA): static
    {
        $this->choixA = $choixA;
        return $this;
    }

    public function getChoixB(): ?string
    {
        return $this->choixB;
    }

    public function setChoixB(string $choixB): static
    {
        $this->choixB = $choixB;
        return $this;
    }

    public function getChoixC(): ?string
    {
        return $this->choixC;
    }

    public function setChoixC(string $choixC): static
    {
        $this->choixC = $choixC;
        return $this;
    }

    public function getBonneReponse(): ?BonneReponse
    {
        return $this->bonneReponse;
    }

    public function setBonneReponse(BonneReponse $bonneReponse): static
    {
        $this->bonneReponse = $bonneReponse;
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