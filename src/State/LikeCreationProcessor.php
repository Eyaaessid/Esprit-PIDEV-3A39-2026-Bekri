<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Like;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class LikeCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Like
    {
        if (!$data instanceof Like) {
            throw new \InvalidArgumentException('LikeCreationProcessor expects a Like entity.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if ($data->getPost()?->getDeletedAt() !== null) {
            throw new UnprocessableEntityHttpException('Cannot like a deleted post.');
        }

        $existing = $this->entityManager->getRepository(Like::class)->findOneBy([
            'post' => $data->getPost(),
            'utilisateur' => $user,
        ]);

        if ($existing instanceof Like) {
            return $existing;
        }

        $data->setUtilisateur($user);
        $data->setCreatedAt(new \DateTime());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
