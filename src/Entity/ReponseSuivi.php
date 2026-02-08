<?php

namespace App\Entity;

use App\Repository\ReponseSuiviRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReponseSuiviRepository::class)]
class ReponseSuivi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reponses')]
    #[ORM\JoinColumn(nullable: false)]
    private ?SuiviQuotidien $suivi = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?QuestionEvaluation $question = null;

    #[ORM\Column(length: 255)]
    private ?string $valeur = null; // Stocke la réponse (string pour tout : 'great', '50', '3.5', etc.)

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getSuivi(): ?SuiviQuotidien { return $this->suivi; }
    public function setSuivi(?SuiviQuotidien $suivi): static { $this->suivi = $suivi; return $this; }
    public function getQuestion(): ?QuestionEvaluation { return $this->question; }
    public function setQuestion(?QuestionEvaluation $question): static { $this->question = $question; return $this; }
    public function getValeur(): ?string { return $this->valeur; }
    public function setValeur(string $valeur): static { $this->valeur = $valeur; return $this; }
}