<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\OrderFilter;
use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Patch;
use ApiPlatform\Metadata\Post as ApiPost;
use App\Repository\PostRepository;
use App\State\PostCreationProcessor;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: PostRepository::class)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['post:read']]
        ),
        new Get(
            normalizationContext: ['groups' => ['post:read']]
        ),
        new ApiPost(
            denormalizationContext: ['groups' => ['post:write']],
            normalizationContext: ['groups' => ['post:read']],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN')",
            processor: PostCreationProcessor::class
        ),
        new Patch(
            denormalizationContext: ['groups' => ['post:write']],
            normalizationContext: ['groups' => ['post:read']],
            security: "is_granted('ROLE_ADMIN') or object.getUtilisateur() == user"
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or object.getUtilisateur() == user"
        ),
    ],
    normalizationContext: ['groups' => ['post:read']],
    denormalizationContext: ['groups' => ['post:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'emotion' => 'exact',
    'riskLevel' => 'exact',
    'categorie' => 'partial',
])]
#[ApiFilter(OrderFilter::class, properties: [
    'createdAt',
    'emotion',
    'riskLevel',
], arguments: ['orderParameterName' => 'order'])]
class Post
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['post:read', 'admin:read', 'comment:read', 'like:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'posts')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['post:read', 'admin:read'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(length: 255)]
    #[Groups(['post:read', 'post:write', 'admin:read'])]
    private string $titre;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['post:read', 'post:write', 'admin:read'])]
    private string $contenu;

    #[ORM\Column(length: 500, nullable: true)]
    #[Groups(['post:read', 'post:write', 'admin:read'])]
    private ?string $mediaUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Groups(['post:read', 'post:write', 'admin:read'])]
    private ?string $categorie = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Groups(['post:read', 'admin:read'])]
    private ?string $emotion = null;

    #[ORM\Column(name: 'risk_level', length: 20, options: ['default' => 'low'])]
    #[Groups(['post:read', 'admin:read'])]
    private string $riskLevel = 'low';

    #[ORM\Column(options: ['default' => false])]
    #[Groups(['post:read', 'admin:read'])]
    private bool $isSensitive = false;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['post:read', 'admin:read'])]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Groups(['post:read', 'admin:read'])]
    private ?\DateTimeInterface $updatedAt = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    /**
     * @var Collection<int, Commentaire>
     */
    #[ORM\OneToMany(targetEntity: Commentaire::class, mappedBy: 'post')]
    private Collection $commentaires;

    /**
     * @var Collection<int, Like>
     */
    #[ORM\OneToMany(targetEntity: Like::class, mappedBy: 'post')]
    private Collection $likes;

    public function __construct()
    {
        $this->commentaires = new ArrayCollection();
        $this->createdAt = new \DateTime();
        $this->likes = new ArrayCollection();
    }

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

    public function getTitre(): string
    {
        return $this->titre;
    }

    public function setTitre(string $titre): static
    {
        $this->titre = $titre;

        return $this;
    }

    public function getContenu(): string
    {
        return $this->contenu;
    }

    public function setContenu(string $contenu): static
    {
        $this->contenu = $contenu;

        return $this;
    }

    public function getMediaUrl(): ?string
    {
        return $this->mediaUrl;
    }

    public function setMediaUrl(?string $mediaUrl): static
    {
        $this->mediaUrl = $mediaUrl;

        return $this;
    }

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;

        return $this;
    }

    public function getEmotion(): ?string
    {
        return $this->emotion;
    }

    public function setEmotion(?string $emotion): static
    {
        $this->emotion = $emotion;

        return $this;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function setRiskLevel(string $riskLevel): static
    {
        $this->riskLevel = $riskLevel;

        return $this;
    }

    public function isSensitive(): bool
    {
        return $this->isSensitive;
    }

    public function setIsSensitive(bool $isSensitive): static
    {
        $this->isSensitive = $isSensitive;

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

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): static
    {
        $this->deletedAt = $deletedAt;

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
            $commentaire->setPost($this);
        }

        return $this;
    }

    public function removeCommentaire(Commentaire $commentaire): static
    {
        if ($this->commentaires->removeElement($commentaire)) {
            if ($commentaire->getPost() === $this) {
                $commentaire->setPost(null);
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
            $like->setPost($this);
        }

        return $this;
    }

    public function removeLike(Like $like): static
    {
        if ($this->likes->removeElement($like)) {
            if ($like->getPost() === $this) {
                $like->setPost(null);
            }
        }

        return $this;
    }

    #[Groups(['post:read', 'admin:read'])]
    public function getLikesCount(): int
    {
        return $this->likes->count();
    }

    #[Groups(['post:read', 'admin:read'])]
    public function getCommentsCount(): int
    {
        return $this->commentaires->count();
    }
}
