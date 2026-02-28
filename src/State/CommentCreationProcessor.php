<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Commentaire;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

class CommentCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Commentaire
    {
        if (!$data instanceof Commentaire) {
            throw new \InvalidArgumentException('CommentCreationProcessor expects a Commentaire entity.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        if ($data->getPost()?->getDeletedAt() !== null) {
            throw new UnprocessableEntityHttpException('Cannot comment on a deleted post.');
        }

        $data->setUtilisateur($user);
        $data->setCreatedAt(new \DateTime());

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        return $data;
    }
}
