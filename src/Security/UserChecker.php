<?php
// src/Security/UserChecker.php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Doctrine\ORM\EntityManagerInterface;

class UserChecker implements UserCheckerInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer,
        private UrlGeneratorInterface $urlGenerator,
        private LoggerInterface $logger
    ) {
    }

    public function checkPreAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        // ── 1. Inactive account ───────────────────────────────────────────────
        if ($user->getStatut() === UtilisateurStatut::INACTIF) {
            $deactivatedBy = $user->getDeactivatedBy();

            // For 'user' and 'system' deactivation: generate a reactivation token
            // and send an email, then show a message with a clickable resend link.
            if ($deactivatedBy === 'user' || $deactivatedBy === 'system' || $deactivatedBy === null) {
                $token = bin2hex(random_bytes(32));
                $expiresAt = new \DateTime('+24 hours');
                $user->setReactivationToken($token);
                $user->setReactivationTokenExpiresAt($expiresAt);
                $this->entityManager->flush();

                $reactivationUrl = $this->urlGenerator->generate(
                    'reactivate_account',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $deactivationReason = match ($deactivatedBy) {
                    'user'   => 'Vous avez désactivé votre compte.',
                    'system' => 'Votre compte a été marqué inactif après 30 jours sans connexion.',
                    default  => 'Votre compte est inactif.',
                };

                $emailMessage = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Réactivation de votre compte Bekri')
                    ->htmlTemplate('emails/reactivate_account.html.twig')
                    ->context([
                        'user'              => $user,
                        'reactivationUrl'   => $reactivationUrl,
                        'deactivationReason' => $deactivationReason,
                    ]);

                try {
                    $this->mailer->send($emailMessage);
                } catch (\Throwable $e) {
                    // Log the link so the flow stays testable in dev even if SMTP is unavailable
                    $this->logger->error('UserChecker: Failed to send reactivation email', [
                        'email'           => $user->getEmail(),
                        'error'           => $e->getMessage(),
                        'reactivationUrl' => $reactivationUrl,
                    ]);
                }

                if ($deactivatedBy === 'system') {
                    throw new CustomUserMessageAccountStatusException(
                        'Votre compte a été désactivé automatiquement après 30 jours sans connexion. ' .
                        'Un email de réactivation vous a été envoyé. ' .
                        '<a href="' . htmlspecialchars($reactivationUrl) . '" class="alert-link">Réactiver mon compte</a>'
                    );
                }

                // deactivatedBy === 'user' or null
                throw new CustomUserMessageAccountStatusException(
                    'Votre compte a été désactivé par vous-même. ' .
                    'Un email de réactivation vous a été envoyé. ' .
                    '<a href="/verify/resend?email=' . urlencode($user->getEmail()) . '" class="alert-link">' .
                    'Renvoyer l\'email de réactivation</a>'
                );
            }

            // deactivatedBy === 'admin'
            throw new CustomUserMessageAccountStatusException(
                'Votre compte a été désactivé par un administrateur. ' .
                '<a href="/request-reactivation?email=' . urlencode($user->getEmail()) . '" class="alert-link">' .
                'Demander la réactivation</a> ou contactez le support à ' .
                '<a href="mailto:support@bekri.com" class="alert-link">support@bekri.com</a>.'
            );
        }

        // ── 2. Unverified email ───────────────────────────────────────────────
        if (!$user->isVerified()) {
            throw new CustomUserMessageAccountStatusException(
                'Votre compte n\'est pas encore vérifié. ' .
                '<a href="/verify/resend?email=' . urlencode($user->getEmail()) . '" class="alert-link">' .
                'Renvoyer l\'email de vérification</a>'
            );
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            return;
        }

        // Additional post-authentication checks can go here if needed
    }
}