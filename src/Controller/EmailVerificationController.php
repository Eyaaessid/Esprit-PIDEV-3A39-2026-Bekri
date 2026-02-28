<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Security\EmailVerifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mime\Address;
use Symfony\Component\Routing\Attribute\Route;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

class EmailVerificationController extends AbstractController
{
    #[Route('/verify/email', name: 'app_verify_email', methods: ['GET'])]
    public function verifyUserEmail(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EmailVerifier $emailVerifier,
        EntityManagerInterface $entityManager
    ): Response {
        $id = $request->query->get('id');
        if ($id === null) {
            $this->addFlash('error', 'Invalid verification link.');
            return $this->redirectToRoute('app_login');
        }

        /** @var Utilisateur|null $user */
        $user = $utilisateurRepository->find($id);
        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_login');
        }

        if ($user->isVerified()) {
            $this->addFlash('success', 'Your email is already verified. You can log in.');
            return $this->redirectToRoute('app_login');
        }

        try {
            $emailVerifier->handleEmailConfirmation($request, $user);
        } catch (VerifyEmailExceptionInterface $exception) {
            $this->addFlash('error', $exception->getReason());
            return $this->redirectToRoute('app_verify_resend', ['email' => $user->getEmail()]);
        }

        $user->setIsVerified(true);
        $entityManager->flush();

        $this->addFlash('success', 'Email verified successfully. You can now log in.');
        return $this->redirectToRoute('app_login');
    }

    #[Route('/verify/resend', name: 'app_verify_resend', methods: ['GET', 'POST'])]
    public function resendVerificationEmail(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        EmailVerifier $emailVerifier
    ): Response {
        $emailValue = trim((string) ($request->request->get('email') ?? $request->query->get('email') ?? ''));

        if ($request->isMethod('POST')) {
            if ($emailValue === '' || !str_contains($emailValue, '@')) {
                $this->addFlash('error', 'Please enter a valid email address.');
                return $this->redirectToRoute('app_verify_resend', ['email' => $emailValue]);
            }

            /** @var Utilisateur|null $user */
            $user = $utilisateurRepository->findOneBy(['email' => $emailValue]);

            // Always show a generic message to avoid email enumeration
            if (!$user) {
                $this->addFlash('success', 'If an account exists for that email, a verification email has been sent.');
                return $this->redirectToRoute('app_login');
            }

            if ($user->isVerified()) {
                $this->addFlash('success', 'Your email is already verified. You can log in.');
                return $this->redirectToRoute('app_login');
            }

            $email = (new TemplatedEmail())
                ->from(EmailVerifier::defaultFrom())
                ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                ->subject('Verify your email address')
                ->htmlTemplate('email/verification.html.twig')
                ->context([
                    'user' => $user,
                    'supportEmail' => 'support@bekri.com',
                ]);

            $emailVerifier->sendEmailConfirmation('app_verify_email', $user, $email);

            $this->addFlash('success', 'Verification email sent. Please check your inbox.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/resend_verification.html.twig', [
            'email' => $emailValue,
        ]);
    }
}

