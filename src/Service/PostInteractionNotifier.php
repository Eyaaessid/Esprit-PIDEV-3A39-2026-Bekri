<?php

namespace App\Service;

use App\Entity\PostNotification;
use App\Entity\Post;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;

class PostInteractionNotifier
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function notifyPostLiked(Post $post, Utilisateur $actor): void
    {
        $this->notifyPostOwner($post, $actor, 'like');
    }

    public function notifyPostCommented(Post $post, Utilisateur $actor, string $comment): void
    {
        $this->notifyPostOwner($post, $actor, 'comment', $comment);
    }

    private function notifyPostOwner(Post $post, Utilisateur $actor, string $interaction, ?string $comment = null): void
    {
        $owner = $post->getUtilisateur();
        if (!$owner instanceof Utilisateur) {
            return;
        }

        if ($owner->getId() === $actor->getId()) {
            return;
        }

        $actorLabel = trim((string) (($actor->getPrenom() ?? '') . ' ' . ($actor->getNom() ?? '')));
        if ($actorLabel === '') {
            $actorLabel = 'A user';
        }

        $message = '';
        if ($interaction === 'like') {
            $message = sprintf('%s liked your post "%s".', $actorLabel, $post->getTitre());
        } else {
            $message = sprintf('%s commented on your post "%s".', $actorLabel, $post->getTitre());
        }

        if ($interaction === 'comment' && $comment !== null && trim($comment) !== '') {
            $message .= ' "' . substr(trim($comment), 0, 120) . '"';
        }

        $notification = new PostNotification();
        $notification->setRecipient($owner);
        $notification->setActor($actor);
        $notification->setPost($post);
        $notification->setType($interaction === 'comment' ? 'comment' : 'like');
        $notification->setMessage($message);
        $notification->setIsRead(false);

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
