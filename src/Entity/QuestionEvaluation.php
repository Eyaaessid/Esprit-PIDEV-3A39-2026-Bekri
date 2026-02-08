<?php

namespace App\Entity;

use App\Repository\QuestionEvaluationRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: QuestionEvaluationRepository::class)]
class QuestionEvaluation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $texte = null;

    #[ORM\Column(length: 50)]
    private ?string $category = null;

    #[ORM\Column(length: 50)]
    private string $typeReponse = 'choice'; // For now we force 'choice' – can be changed later

    #[ORM\Column(length: 255)]
    private ?string $option1 = null;

    #[ORM\Column(length: 255)]
    private ?string $option2 = null;

    #[ORM\Column(length: 255)]
    private ?string $option3 = null;

    #[ORM\Column(nullable: true)]
    private ?int $minValue = null;

    #[ORM\Column(nullable: true)]
    private ?int $maxValue = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTexte(): ?string
    {
        return $this->texte;
    }

    public function setTexte(string $texte): static
    {
        $this->texte = $texte;
        return $this;
    }

    public function getCategory(): ?string
    {
        return $this->category;
    }

    public function setCategory(string $category): static
    {
        $this->category = $category;
        return $this;
    }

    public function getTypeReponse(): string
    {
        return $this->typeReponse;
    }

    public function setTypeReponse(string $typeReponse): static
    {
        $this->typeReponse = $typeReponse;
        return $this;
    }

    public function getOption1(): ?string
    {
        return $this->option1;
    }

    public function setOption1(string $option1): static
    {
        $this->option1 = $option1;
        return $this;
    }

    public function getOption2(): ?string
    {
        return $this->option2;
    }

    public function setOption2(string $option2): static
    {
        $this->option2 = $option2;
        return $this;
    }

    public function getOption3(): ?string
    {
        return $this->option3;
    }

    public function setOption3(string $option3): static
    {
        $this->option3 = $option3;
        return $this;
    }

    public function getMinValue(): ?int
    {
        return $this->minValue;
    }

    public function setMinValue(?int $minValue): static
    {
        $this->minValue = $minValue;
        return $this;
    }

    public function getMaxValue(): ?int
    {
        return $this->maxValue;
    }

    public function setMaxValue(?int $maxValue): static
    {
        $this->maxValue = $maxValue;
        return $this;
    }
}