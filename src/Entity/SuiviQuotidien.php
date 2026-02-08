<?php

namespace App\Entity;

use App\Repository\SuiviQuotidienRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
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
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: 'date')]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $commentaire = null;

    #[ORM\OneToMany(mappedBy: 'suivi', targetEntity: ReponseSuivi::class, orphanRemoval: true)]
    private Collection $reponses;

    public function __construct()
    {
        $this->reponses = new ArrayCollection();
    }

    // Getters/Setters
    public function getId(): ?int { return $this->id; }
    public function getUtilisateur(): ?Utilisateur { return $this->utilisateur; }
    public function setUtilisateur(?Utilisateur $utilisateur): static { $this->utilisateur = $utilisateur; return $this; }
    public function getDate(): ?\DateTimeInterface { return $this->date; }
    public function setDate(\DateTimeInterface $date): static { $this->date = $date; return $this; }
    public function getCommentaire(): ?string { return $this->commentaire; }
    public function setCommentaire(?string $commentaire): static { $this->commentaire = $commentaire; return $this; }
    public function getReponses(): Collection { return $this->reponses; }
    public function addReponse(ReponseSuivi $reponse): static { if (!$this->reponses->contains($reponse)) { $this->reponses->add($reponse); $reponse->setSuivi($this); } return $this; }
    public function removeReponse(ReponseSuivi $reponse): static { if ($this->reponses->removeElement($reponse)) { if ($reponse->getSuivi() === $this) { $reponse->setSuivi(null); } } return $this; }
}