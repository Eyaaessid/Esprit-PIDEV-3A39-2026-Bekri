<?php

namespace App\Service;

use App\Entity\Post;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class PostRiskAlertNotifier
{
    public function __construct(
        private readonly MailerInterface $mailer,
        private readonly string $alertRecipient,
        private readonly string $senderEmail,
    ) {
    }

    /**
     * Sends an alert for high-risk posts. This is intentionally compact because
     * alerting should be non-blocking and easy to swap for Messenger later.
     */
    public function notifyHighRiskPost(Post $post, array $matchedSignals = []): void
    {
        $author = $post->getUtilisateur();
        $authorLabel = $author ? ($author->getPrenom() . ' ' . $author->getNom()) : 'Unknown';
        $signals = $matchedSignals === [] ? 'n/a' : implode(', ', $matchedSignals);
        $authorEmail = $author?->getEmail();

        $senderEmail = trim($this->senderEmail);
        if ($senderEmail === '' || filter_var($senderEmail, FILTER_VALIDATE_EMAIL) === false) {
            $senderEmail = 'noreply@localhost';
        }

        $recipients = [];
        if (is_string($authorEmail) && trim($authorEmail) !== '' && filter_var($authorEmail, FILTER_VALIDATE_EMAIL) !== false) {
            $recipients[] = $authorEmail;
        }
        $alertRecipient = trim($this->alertRecipient);
        if ($alertRecipient !== '' && filter_var($alertRecipient, FILTER_VALIDATE_EMAIL) !== false) {
            $recipients[] = $alertRecipient;
        }

        $recipients = array_values(array_unique($recipients));
        if ($recipients === []) {
            return;
        }

        $email = (new Email())
            ->from($senderEmail)
            ->to(...$recipients)
            ->subject(sprintf('High-risk post detected (post #%d)', $post->getId() ?? 0))
            ->text(
                "A high-risk post has been detected.\n\n" .
                "Post ID: " . ($post->getId() ?? 0) . "\n" .
                "Author: {$authorLabel}\n" .
                "Emotion: " . ($post->getEmotion() ?? 'unknown') . "\n" .
                "Risk level: " . $post->getRiskLevel() . "\n" .
                "Sensitive: " . ($post->isSensitive() ? 'yes' : 'no') . "\n" .
                "Signals: {$signals}\n\n" .
                "Content:\n" . $post->getContenu()
            );

        $this->mailer->send($email);
    }
}
