<?php

namespace App\Entity;

use ApiPlatform\Doctrine\Orm\Filter\SearchFilter;
use ApiPlatform\Metadata\ApiFilter;
use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Delete;
use ApiPlatform\Metadata\Get;
use ApiPlatform\Metadata\GetCollection;
use ApiPlatform\Metadata\Post as ApiPost;
use App\Repository\LikeRepository;
use App\State\LikeCreationProcessor;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: LikeRepository::class)]
#[ORM\Table(
    name: '`like`',
    uniqueConstraints: [
        new ORM\UniqueConstraint(name: 'uniq_like_post_user', columns: ['post_id', 'utilisateur_id']),
    ]
)]
#[ApiResource(
    operations: [
        new GetCollection(
            normalizationContext: ['groups' => ['like:read']],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN')"
        ),
        new Get(
            normalizationContext: ['groups' => ['like:read']],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN')"
        ),
        new ApiPost(
            denormalizationContext: ['groups' => ['like:write']],
            normalizationContext: ['groups' => ['like:read']],
            security: "is_granted('ROLE_USER') or is_granted('ROLE_ADMIN')",
            processor: LikeCreationProcessor::class
        ),
        new Delete(
            security: "is_granted('ROLE_ADMIN') or object.getUtilisateur() == user"
        ),
    ],
    normalizationContext: ['groups' => ['like:read']],
    denormalizationContext: ['groups' => ['like:write']]
)]
#[ApiFilter(SearchFilter::class, properties: [
    'post.id' => 'exact',
    'utilisateur.id' => 'exact',
])]
class Like
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['like:read', 'admin:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['like:read', 'like:write'])]
    private ?Post $post = null;

    #[ORM\ManyToOne(inversedBy: 'likes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Groups(['like:read', 'admin:read'])]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    #[Groups(['like:read', 'admin:read'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;
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

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
