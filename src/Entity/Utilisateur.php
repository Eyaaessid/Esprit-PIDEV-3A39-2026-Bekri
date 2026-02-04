<?php

namespace App\Entity;

use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
class Utilisateur
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private string $nom;

    #[ORM\Column(length: 100)]
    private string $prenom;

    #[ORM\Column(length: 255, unique: true)]
    private string $email;

    #[ORM\Column(length: 255)]
    private string $motDePasse;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private \DateTimeInterface $dateNaissance;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $pays = 'Tunisie';

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 50, enumType: UtilisateurRole::class)]
    private UtilisateurRole $role = UtilisateurRole::USER;

    #[ORM\Column(length: 50, enumType: UtilisateurStatut::class)]
    private UtilisateurStatut $statut = UtilisateurStatut::ACTIF;

    #[ORM\Column(nullable: true)]
    private ?int $scoreInitial = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEvaluationInitiale = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    /**
     * @var Collection<int, Post>
     */
    #[ORM\OneToMany(targetEntity: Post::class, mappedBy: 'utilisateur')]
    private Collection $posts;

    /**
     * @var Collection<int, Commentaire>
     */
    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'utilisateur')]
    private Collection $commentaires;

    /**
     * @var Collection<int, Like>
     */
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'utilisateur')]
    private Collection $likes;

    /**
     * @var Collection<int, ObjectifBienEtre>
     */
    #[ORM\OneToMany(targetEntity: ObjectifBienEtre::class, mappedBy: 'utilisateur')]
    private Collection $objectifBienEtres;

    /**
     * @var Collection<int, Evenement>
     */
    #[ORM\OneToMany(targetEntity: Evenement::class, mappedBy: 'coach')]
    private Collection $evenements;

    /**
     * @var Collection<int, ParticipationEvenement>
     */
    #[ORM\OneToMany(targetEntity: ParticipationEvenement::class, mappedBy: 'utilisateur')]
    private Collection $participationsEvenements;

    /**
     * @var Collection<int, ResultatTest>
     */
    #[ORM\OneToMany(targetEntity: ResultatTest::class, mappedBy: 'utilisateur')]
    private Collection $resultatsTests;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->posts = new ArrayCollection();
        $this->commentaires = new ArrayCollection();
        $this->likes = new ArrayCollection();
        $this->objectifBienEtres = new ArrayCollection();
        $this->evenements = new ArrayCollection();
        $this->participationsEvenements = new ArrayCollection();
        $this->resultatsTests = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMotDePasse(): string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;
        return $this;
    }

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getDateNaissance(): \DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
        return $this;
    }

    public function getPays(): ?string
    {
        return $this->pays;
    }

    public function setPays(?string $pays): static
    {
        $this->pays = $pays;
        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;
        return $this;
    }

    public function getRole(): UtilisateurRole
    {
        return $this->role;
    }

    public function setRole(UtilisateurRole $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getStatut(): UtilisateurStatut
    {
        return $this->statut;
    }

    public function setStatut(UtilisateurStatut $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getScoreInitial(): ?int
    {
        return $this->scoreInitial;
    }

    public function setScoreInitial(?int $scoreInitial): static
    {
        $this->scoreInitial = $scoreInitial;
        return $this;
    }

    public function getDateEvaluationInitiale(): ?\DateTimeInterface
    {
        return $this->dateEvaluationInitiale;
    }

    public function setDateEvaluationInitiale(?\DateTimeInterface $dateEvaluationInitiale): static
    {
        $this->dateEvaluationInitiale = $dateEvaluationInitiale;
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
     * @return Collection<int, Post>
     */
    public function getPosts(): Collection
    {
        return $this->posts;
    }

    public function addPost(Post $post): static
    {
        if (!$this->posts->contains($post)) {
            $this->posts->add($post);
            $post->setUtilisateur($this);
        }
        return $this;
    }

    public function removePost(Post $post): static
    {
        if ($this->posts->removeElement($post)) {
            if ($post->getUtilisateur() === $this) {
                $post->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Commentaire>
     */
    public function getCommentaires(): Collection
    {
        return $this->commentaires;
    }

    public function addCommentaire(Commentaire $commentaire): static
    {
        if (!$this->commentaires->contains($commentaire)) {
            $this->commentaires->add($commentaire);
            $commentaire->setUtilisateur($this);
        }
        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
        if ($this->commentaires->removeElement($commentaire)) {
            if ($commentaire->getUtilisateur() === $this) {
                $commentaire->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Like>
     */
    public function getLikes(): Collection
    {
        return $this->likes;
    }

    public function addLike(Like $like): static
    {
        if (!$this->likes->contains($like)) {
            $this->likes->add($like);
            $like->setUtilisateur($this);
        }
        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getUtilisateur() === $this) {
                $like->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ObjectifBienEtre>
     */
    public function getObjectifBienEtres(): Collection
    {
        return $this->objectifBienEtres;
    }

    public function addObjectifBienEtre(ObjectifBienEtre $objectifBienEtre): static
    {
        if (!$this->objectifBienEtres->contains($objectifBienEtre)) {
            $this->objectifBienEtres->add($objectifBienEtre);
            $objectifBienEtre->setUtilisateur($this);
        }
        return $this;
    }

    public function removeObjectifBienEtre(ObjectifBienEtre $objectifBienEtre): static
    {
        if ($this->objectifBienEtres->removeElement($objectifBienEtre)) {
            if ($objectifBienEtre->getUtilisateur() === $this) {
                $objectifBienEtre->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Evenement>
     */
    public function getEvenements(): Collection
    {
        return $this->evenements;
    }

    public function addEvenement(Evenement $evenement): static
    {
        if (!$this->evenements->contains($evenement)) {
            $this->evenements->add($evenement);
            $evenement->setCoach($this);
        }
        return $this;
    }

    public function removeEvenement(Evenement $evenement): static
    {
        if ($this->evenements->removeElement($evenement)) {
            if ($evenement->getCoach() === $this) {
                $evenement->setCoach(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ParticipationEvenement>
     */
    public function getParticipationsEvenements(): Collection
    {
        return $this->participationsEvenements;
    }

    public function addParticipationsEvenement(ParticipationEvenement $participationEvenement): static
    {
        if (!$this->participationsEvenements->contains($participationEvenement)) {
            $this->participationsEvenements->add($participationEvenement);
            $participationEvenement->setUtilisateur($this);
        }
        return $this;
    }

    public function removeParticipationsEvenement(ParticipationEvenement $participationEvenement): static
    {
        if ($this->participationsEvenements->removeElement($participationEvenement)) {
            if ($participationEvenement->getUtilisateur() === $this) {
                $participationEvenement->setUtilisateur(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, ResultatTest>
     */
    public function getResultatsTests(): Collection
    {
        return $this->resultatsTests;
    }

    public function addResultatTest(ResultatTest $resultatTest): static
    {
        if (!$this->resultatsTests->contains($resultatTest)) {
            $this->resultatsTests->add($resultatTest);
            $resultatTest->setUtilisateur($this);
        }

        return $this;
    }

    public function removeResultatTest(ResultatTest $resultatTest): static
    {
        if ($this->resultatsTests->removeElement($resultatTest)) {
            // set the owning side to null (unless already changed)
            if ($resultatTest->getUtilisateur() === $this) {
                $resultatTest->setUtilisateur(null);
            }
        }

        return $this;
    }
}