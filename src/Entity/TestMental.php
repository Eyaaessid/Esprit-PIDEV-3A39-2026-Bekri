<?php

namespace App\Entity;

use App\Enum\NiveauTest;
use App\Repository\TestMentalRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: TestMentalRepository::class)]
class TestMental
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 100)]
    private ?string $typeTest = null;

    #[ORM\Column(length: 50, enumType: NiveauTest::class)]
    private ?NiveauTest $niveau = null;

    #[ORM\Column]
    private ?int $duree = null;

    /**
     * @var Collection<int, Question>
     */
    #[ORM\OneToMany(targetEntity: Question::class, mappedBy: 'testMental', orphanRemoval: true)]
    private Collection $questions;

    /**
     * @var Collection<int, ResultatTest>
     */
    #[ORM\OneToMany(targetEntity: ResultatTest::class, mappedBy: 'testMental')]
    private Collection $resultats;

    public function __construct()
    {
        $this->questions = new ArrayCollection();
        $this->resultats = new ArrayCollection();
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

    public function getTypeTest(): ?string
    {
        return $this->typeTest;
    }

    public function setTypeTest(string $typeTest): static
    {
        $this->typeTest = $typeTest;
        return $this;
    }

    public function getNiveau(): ?NiveauTest
    {
        return $this->niveau;
    }

    public function setNiveau(NiveauTest $niveau): static
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(int $duree): static
    {
        $this->duree = $duree;
        return $this;
    }

    /**
     * @return Collection<int, Question>
     */
    public function getQuestions(): Collection
    {
        return $this->questions;
    }

    public function addQuestion(Question $question): static
    {
        if (!$this->questions->contains($question)) {
            $this->questions->add($question);
            $question->setTestMental($this);
        }
        return $this;
    }

    public function removeQuestion(Question $question): static
    {
        if ($this->questions->removeElement($question)) {
            if ($question->getTestMental() === $this) {
                $question->setTestMental(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ResultatTest>
     */
    public function getResultats(): Collection
    {
        return $this->resultats;
    }

    public function addResultat(ResultatTest $resultat): static
    {
        if (!$this->resultats->contains($resultat)) {
            $this->resultats->add($resultat);
            $resultat->setTestMental($this);
        }
        return $this;
    }

    public function removeResultat(ResultatTest $resultat): static
    {
        if ($this->resultats->removeElement($resultat)) {
            if ($resultat->getTestMental() === $this) {
                $resultat->setTestMental(null);
            }
        }
        return $this;
    }
}