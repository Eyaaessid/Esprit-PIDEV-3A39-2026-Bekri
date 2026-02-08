<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Form\ResetPasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class SecurityController extends AbstractController
{
    // ==================== LOGIN ====================
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in, redirect based on role
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        // Get the login error if there is one
        $error = $authenticationUtils->getLastAuthenticationError();

        // Last username entered by the user
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('admin/signin.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // ==================== LOGOUT ====================
    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // This method can be blank - it will be intercepted by the logout key on your firewall
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // ==================== REGISTER ====================
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // If user is already logged in, redirect
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user, [
            'action' => $this->generateUrl('app_register'),
            'method' => 'POST',
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set default values for required fields
            $user->setRole(UtilisateurRole::USER);
            $user->setStatut(UtilisateurStatut::ACTIF);

            // Save to database
            $entityManager->persist($user);
            $entityManager->flush();

            // Add flash message
            $this->addFlash('success', 'Votre compte a été créé avec succès! Veuillez vous connecter.');

            // Redirect to login page
            return $this->redirectToRoute('app_login');
        }

        return $this->render('admin/signup.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    // ==================== FORGOT PASSWORD ====================
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            if ($email) {
                $user = $entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $email]);

                if ($user) {
                    // Generate reset token
                    $resetToken = bin2hex(random_bytes(32));
                    $user->setResetToken($resetToken);
                    $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));

                    $entityManager->flush();

                    // Generate reset URL
                    $resetUrl = $this->generateUrl('app_reset_password', [
                        'token' => $resetToken
                    ], UrlGeneratorInterface::ABSOLUTE_URL);

                    // Send email
                    try {
                        $emailMessage = (new TemplatedEmail())
                            ->from(new Address('no-reply@bekriwellbeing.com', 'Bekri Wellbeing'))
                            ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                            ->subject('Réinitialisation de votre mot de passe')
                            ->htmlTemplate('emails/reset_password.html.twig')
                            ->context([
                                'resetUrl' => $resetUrl,
                                'user' => $user,
                            ]);

                        $mailer->send($emailMessage);

                        $this->addFlash('success', ' Un email de réinitialisation a été envoyé à votre adresse.');
                    } catch (\Exception $e) {
                        // Show error and fallback link for development
                        $this->addFlash('error', ' Erreur email: ' . $e->getMessage());
                        $this->addFlash('reset_link', $resetUrl);
                    }
                } else {
                    // Security: don't reveal if email exists
                    $this->addFlash('info', 'Si un compte existe avec cet email, un lien sera envoyé.');
                }
            }

            return $this->redirectToRoute('app_forgot_password');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    // ==================== RESET PASSWORD ====================
    #[Route('/reset-password/{token}', name: 'app_reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['resetToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Token de réinitialisation invalide.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if (!$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Le token a expiré. Veuillez demander un nouveau lien.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            if (!$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
            } elseif (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                // Hash new password
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $user->setUpdatedAt(new \DateTime());

                $entityManager->flush();

                $this->addFlash('success', ' Votre mot de passe a été réinitialisé avec succès!');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'user' => $user,
        ]);
    }

    // ==================== HELPER METHOD ====================
    private function getRedirectRouteByRole(): string
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return 'admin_dashboard';
        }

        if ($this->isGranted('ROLE_COACH')) {
            return 'coach_dashboard';
        }

        return 'user_dashboard';
    }
}