<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Debug controller for previewing email templates.
 * Only available in dev environment.
 */
#[Route('/debug', name: 'debug_')]
class EmailDebugController extends AbstractController
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    #[Route('/email-test', name: 'email_test')]
    public function testEmail(): Response
    {
        if (!$this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException('Debug routes are only available in dev environment.');
        }

        $user = new Utilisateur();
        $user->setPrenom('Test');
        $user->setNom('User');
        $user->setEmail('test@example.com');
        $user->setCreatedAt(new \DateTime());

        return $this->render('emails/welcome_email.html.twig', [
            'user' => $user,
            'loginUrl' => $this->urlGenerator->generate('app_login', [], UrlGeneratorInterface::ABSOLUTE_URL),
        ]);
    }

    #[Route('/email-reset-password', name: 'email_reset_password')]
    public function testResetPasswordEmail(): Response
    {
        if (!$this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException('Debug routes are only available in dev environment.');
        }

        $user = new Utilisateur();
        $user->setPrenom('Test');
        $user->setNom('User');
        $user->setEmail('test@example.com');

        $resetUrl = $this->urlGenerator->generate('forgot_password_reset', ['token' => 'sample-token-123'], UrlGeneratorInterface::ABSOLUTE_URL);
        $expiresAt = new \DateTime('+1 hour');

        return $this->render('emails/reset_password.html.twig', [
            'resetUrl' => $resetUrl,
            'user' => $user,
            'expiresAt' => $expiresAt,
        ]);
    }

    #[Route('/email-reactivate', name: 'email_reactivate')]
    public function testReactivateEmail(): Response
    {
        if (!$this->getParameter('kernel.debug')) {
            throw $this->createNotFoundException('Debug routes are only available in dev environment.');
        }

        $user = new Utilisateur();
        $user->setPrenom('Test');
        $user->setNom('User');
        $user->setEmail('test@example.com');

        $reactivationUrl = $this->urlGenerator->generate('reactivate_account', ['token' => 'sample-token-123'], UrlGeneratorInterface::ABSOLUTE_URL);

        return $this->render('emails/reactivate_account.html.twig', [
            'user' => $user,
            'reactivationUrl' => $reactivationUrl,
            'deactivationReason' => 'Vous avez désactivé votre compte.',
        ]);
    }
}
