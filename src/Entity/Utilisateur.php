<?php

namespace App\Entity;

use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Repository\UtilisateurRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfigurationInterface;
use Scheb\TwoFactorBundle\Model\Totp\TotpConfiguration;
use Scheb\TwoFactorBundle\Model\Totp\TwoFactorInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[UniqueEntity(
    fields: ['email'],
    message: 'Cet email est déjà utilisé par un autre compte.'
)]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface, TwoFactorInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le nom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire.')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le prénom doit contenir au moins {{ limit }} caractères.',
        maxMessage: 'Le prénom ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire.')]
    #[Assert\Email(message: 'L\'adresse email "{{ value }}" n\'est pas valide.')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'L\'email ne peut pas dépasser {{ limit }} caractères.'
    )]
    private ?string $email = null;

    #[ORM\Column(length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Length(
        min: 8,
        max: 20,
        minMessage: 'Le numéro de téléphone doit contenir au moins {{ limit }} chiffres.',
        maxMessage: 'Le numéro de téléphone ne peut pas dépasser {{ limit }} caractères.'
    )]
    #[Assert\Regex(
        pattern: '/^[+]?[0-9\s\-()]+$/',
        message: 'Le numéro de téléphone n\'est pas valide. Utilisez uniquement des chiffres, espaces, +, - ou ().'
    )]
    private ?string $telephone = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de naissance est obligatoire.')]
    #[Assert\LessThan(
        value: '-13 years',
        message: 'Vous devez avoir au moins 13 ans pour vous inscrire.'
    )]
    #[Assert\GreaterThan(
        value: '-120 years',
        message: 'La date de naissance n\'est pas valide.'
    )]
    private ?\DateTimeInterface $dateNaissance = null;

    #[ORM\Column(length: 500, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(length: 50, enumType: UtilisateurRole::class)]
    #[Assert\NotNull(message: 'Le rôle est obligatoire.')]
    private UtilisateurRole $role = UtilisateurRole::USER;

    #[ORM\Column(length: 50, enumType: UtilisateurStatut::class)]
    #[Assert\NotNull(message: 'Le statut est obligatoire.')]
    private UtilisateurStatut $statut = UtilisateurStatut::ACTIF;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'Le score initial doit être un nombre positif.')]
    #[Assert\Range(
        min: 0,
        max: 100,
        notInRangeMessage: 'Le score initial doit être entre {{ min }} et {{ max }}.'
    )]
    private ?int $scoreInitial = null;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $dateEvaluationInitiale = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $updatedAt = null;

    // ==================== PASSWORD RESET FIELDS ====================
    
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $resetToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $resetTokenExpiresAt = null;

    // ==================== ACCOUNT STATUS / REACTIVATION FIELDS ====================

    /** When the account was deactivated (set to INACTIF). */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deactivatedAt = null;

    /** Who deactivated: 'user', 'admin', or 'system'. */
    #[ORM\Column(length: 50, nullable: true)]
    private ?string $deactivatedBy = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $reactivationToken = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $reactivationTokenExpiresAt = null;

    /** Last successful login timestamp. */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    // ==================== EMAIL VERIFICATION ====================

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isVerified = false;

    // ==================== FACIAL RECOGNITION FIELDS ====================

    /**
     * Encrypted face descriptor for facial recognition login.
     * Stores the face-api.js descriptor as JSON.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $faceDescriptor = null;

    /**
     * Whether the user has enabled facial recognition authentication.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $faceAuthEnabled = false;

    /**
     * When the user registered their face for authentication.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $faceRegisteredAt = null;

    /**
     * Number of failed face authentication attempts (for rate limiting).
     */
    #[ORM\Column(type: Types::INTEGER, options: ['default' => 0])]
    private int $faceAuthFailedAttempts = 0;

    /**
     * Timestamp of last failed face auth attempt (for rate limiting).
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $lastFaceAuthAttemptAt = null;

    // ==================== TWO-FACTOR AUTHENTICATION FIELDS ====================

    /**
     * TOTP secret for two-factor authentication.
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $totpSecret = null;

    /**
     * Whether two-factor authentication is enabled for this user.
     */
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isTwoFactorEnabled = false;

    /**
     * Hashed backup codes for 2FA recovery (stored as JSON array of hashed codes).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $backupCodes = null;

    /**
     * When 2FA was enabled.
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $twoFactorEnabledAt = null;

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

    // ==================== UserInterface Methods ====================
    
    /**
     * A visual identifier that represents this user.
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     * 
     * Converts enum value to Symfony role format
     * Enum values are lowercase ('admin', 'user', 'coach')
     * Symfony expects uppercase ('ROLE_ADMIN', 'ROLE_USER', 'ROLE_COACH')
     */
    public function getRoles(): array
    {
        // Convert enum to Symfony role format (uppercase)
        // 'admin' -> 'ROLE_ADMIN'
        // 'user' -> 'ROLE_USER'
        // 'coach' -> 'ROLE_COACH'
        return ['ROLE_' . strtoupper($this->role->value)];
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->motDePasse;
    }

    public function setPassword(string $password): static
    {
        $this->motDePasse = $password;
        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    // ==================== Regular Entity Methods ====================

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(?string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(?string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(?string $motDePasse): static
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

    public function getDateNaissance(): ?\DateTimeInterface
    {
        return $this->dateNaissance;
    }

    public function setDateNaissance(?\DateTimeInterface $dateNaissance): static
    {
        $this->dateNaissance = $dateNaissance;
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

    // ==================== PASSWORD RESET METHODS ====================

    public function getResetToken(): ?string
    {
        return $this->resetToken;
    }

    public function setResetToken(?string $resetToken): static
    {
        $this->resetToken = $resetToken;
        return $this;
    }

    public function getResetTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->resetTokenExpiresAt;
    }

    public function setResetTokenExpiresAt(?\DateTimeInterface $resetTokenExpiresAt): static
    {
        $this->resetTokenExpiresAt = $resetTokenExpiresAt;
        return $this;
    }

    // ==================== DEACTIVATION / REACTIVATION METHODS ====================

    public function getDeactivatedAt(): ?\DateTimeInterface
    {
        return $this->deactivatedAt;
    }

    public function setDeactivatedAt(?\DateTimeInterface $deactivatedAt): static
    {
        $this->deactivatedAt = $deactivatedAt;
        return $this;
    }

    public function getDeactivatedBy(): ?string
    {
        return $this->deactivatedBy;
    }

    public function setDeactivatedBy(?string $deactivatedBy): static
    {
        $this->deactivatedBy = $deactivatedBy;
        return $this;
    }

    public function getReactivationToken(): ?string
    {
        return $this->reactivationToken;
    }

    public function setReactivationToken(?string $reactivationToken): static
    {
        $this->reactivationToken = $reactivationToken;
        return $this;
    }

    public function getReactivationTokenExpiresAt(): ?\DateTimeInterface
    {
        return $this->reactivationTokenExpiresAt;
    }

    public function setReactivationTokenExpiresAt(?\DateTimeInterface $reactivationTokenExpiresAt): static
    {
        $this->reactivationTokenExpiresAt = $reactivationTokenExpiresAt;
        return $this;
    }

    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): static
    {
        $this->lastLoginAt = $lastLoginAt;
        return $this;
    }

    // ==================== EMAIL VERIFICATION METHODS ====================

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    // ==================== FACIAL RECOGNITION METHODS ====================

    public function getFaceDescriptor(): ?string
    {
        return $this->faceDescriptor;
    }

    public function setFaceDescriptor(?string $faceDescriptor): static
    {
        $this->faceDescriptor = $faceDescriptor;
        return $this;
    }

    public function isFaceAuthEnabled(): bool
    {
        return $this->faceAuthEnabled;
    }

    public function setFaceAuthEnabled(bool $faceAuthEnabled): static
    {
        $this->faceAuthEnabled = $faceAuthEnabled;
        return $this;
    }

    public function getFaceRegisteredAt(): ?\DateTimeInterface
    {
        return $this->faceRegisteredAt;
    }

    public function setFaceRegisteredAt(?\DateTimeInterface $faceRegisteredAt): static
    {
        $this->faceRegisteredAt = $faceRegisteredAt;
        return $this;
    }

    public function getFaceAuthFailedAttempts(): int
    {
        return $this->faceAuthFailedAttempts;
    }

    public function setFaceAuthFailedAttempts(int $faceAuthFailedAttempts): static
    {
        $this->faceAuthFailedAttempts = $faceAuthFailedAttempts;
        return $this;
    }

    public function getLastFaceAuthAttemptAt(): ?\DateTimeInterface
    {
        return $this->lastFaceAuthAttemptAt;
    }

    public function setLastFaceAuthAttemptAt(?\DateTimeInterface $lastFaceAuthAttemptAt): static
    {
        $this->lastFaceAuthAttemptAt = $lastFaceAuthAttemptAt;
        return $this;
    }

    /**
     * Reset face authentication data (used when disabling face auth).
     */
    public function resetFaceAuth(): static
    {
        $this->faceDescriptor = null;
        $this->faceAuthEnabled = false;
        $this->faceRegisteredAt = null;
        $this->faceAuthFailedAttempts = 0;
        $this->lastFaceAuthAttemptAt = null;
        return $this;
    }

    /**
     * Increment failed face auth attempts for rate limiting.
     */
    public function incrementFaceAuthFailedAttempts(): static
    {
        $this->faceAuthFailedAttempts++;
        $this->lastFaceAuthAttemptAt = new \DateTime();
        return $this;
    }

    /**
     * Reset failed face auth attempts after successful login.
     */
    public function resetFaceAuthFailedAttempts(): static
    {
        $this->faceAuthFailedAttempts = 0;
        $this->lastFaceAuthAttemptAt = null;
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

    // ==================== TWO-FACTOR AUTHENTICATION METHODS ====================

    public function isTotpAuthenticationEnabled(): bool
    {
        return $this->isTwoFactorEnabled;
    }

    public function getTotpAuthenticationUsername(): string
    {
        return $this->email;
    }

    public function getTotpAuthenticationConfiguration(): ?TotpConfiguration
    {
        if (!$this->totpSecret) {
            return null;
        }
        
        return new TotpConfiguration(
            $this->totpSecret,
            TotpConfiguration::ALGORITHM_SHA1,
            30,
            6
        );
    }

    // Required by TotpConfigurationInterface (delegated to TotpConfiguration)
    public function getSecret(): string
    {
        return $this->totpSecret ?? '';
    }

    public function getAlgorithm(): string
    {
        return TotpConfiguration::ALGORITHM_SHA1;
    }

    public function getPeriod(): int
    {
        return 30;
    }

    public function getDigits(): int
    {
        return 6;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;
        return $this;
    }

    public function isTwoFactorEnabled(): bool
    {
        return $this->isTwoFactorEnabled;
    }

    public function setIsTwoFactorEnabled(bool $isTwoFactorEnabled): static
    {
        $this->isTwoFactorEnabled = $isTwoFactorEnabled;
        if ($isTwoFactorEnabled && $this->twoFactorEnabledAt === null) {
            $this->twoFactorEnabledAt = new \DateTime();
        }
        return $this;
    }

    public function getBackupCodes(): ?string
    {
        return $this->backupCodes;
    }

    public function setBackupCodes(?string $backupCodes): static
    {
        $this->backupCodes = $backupCodes;
        return $this;
    }

    public function getTwoFactorEnabledAt(): ?\DateTimeInterface
    {
        return $this->twoFactorEnabledAt;
    }

    public function setTwoFactorEnabledAt(?\DateTimeInterface $twoFactorEnabledAt): static
    {
        $this->twoFactorEnabledAt = $twoFactorEnabledAt;
        return $this;
    }

    /**
     * Reset 2FA data (used when disabling 2FA).
     */
    public function resetTwoFactorAuth(): static
    {
        $this->totpSecret = null;
        $this->isTwoFactorEnabled = false;
        $this->backupCodes = null;
        $this->twoFactorEnabledAt = null;
        return $this;
    }
}