<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;

class EmailVerifier
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private MailerInterface $mailer
    ) {}

    /**
     * @throws \Symfony\Component\Mailer\Exception\TransportExceptionInterface
     */
    public function sendEmailConfirmation(string $verifyEmailRouteName, Utilisateur $user, TemplatedEmail $email): void
    {
        $signatureComponents = $this->verifyEmailHelper->generateSignature(
            $verifyEmailRouteName,
            (string) $user->getId(),
            $user->getEmail(),
            ['id' => $user->getId()]
        );

        $email->context(array_merge($email->getContext(), [
            'signedUrl' => $signatureComponents->getSignedUrl(),
            'expiresAtMessageKey' => $signatureComponents->getExpirationMessageKey(),
            'expiresAtMessageData' => $signatureComponents->getExpirationMessageData(),
        ]));

        $this->mailer->send($email);
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, Utilisateur $user): void
    {
        $this->verifyEmailHelper->validateEmailConfirmation(
            $request->getUri(),
            (string) $user->getId(),
            $user->getEmail()
        );
    }

    public static function defaultFrom(): Address
    {
        return new Address('noreply@bekri.com', 'Bekri Wellbeing');
    }
}

