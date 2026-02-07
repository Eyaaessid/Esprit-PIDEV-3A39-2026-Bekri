<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegistrationFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Form\ResetPasswordFormType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Uid\Uuid;

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

        return $this->render('security/login.html.twig', [
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
            return $this->redirectToRoute('home');
        }

        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash the password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Set default role
            $user->setRoles(['ROLE_USER']);

            // Save to database
            $entityManager->persist($user);
            $entityManager->flush();

            // Add flash message
            $this->addFlash('success', 'Your account has been created successfully! Please login.');

            return $this->redirectToRoute('app_login');
        }

        return $this->render('admin\signup.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    // ==================== FORGOT PASSWORD ====================
    #[Route('/forgot-password', name: 'app_forgot_password')]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $email = $form->get('email')->getData();
            
            $user = $entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $email]);

            // Always show success message for security (don't reveal if email exists)
            $this->addFlash('success', 'If an account exists with that email, you will receive a password reset link.');

            if ($user) {
                // Generate reset token
                $resetToken = Uuid::v4()->toRfc4122();
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));
                
                $entityManager->flush();

                // Send email
                $resetUrl = $this->generateUrl('app_reset_password', [
                    'token' => $resetToken
                ], UrlGeneratorInterface::ABSOLUTE_URL);

                $emailMessage = (new TemplatedEmail())
                    ->from('noreply@yoursite.com')
                    ->to($user->getEmail())
                    ->subject('Password Reset Request')
                    ->htmlTemplate('emails/reset_password.html.twig')
                    ->context([
                        'resetUrl' => $resetUrl,
                        'user' => $user,
                    ]);

                try {
                    $mailer->send($emailMessage);
                } catch (\Exception $e) {
                    // Log error but don't show to user for security
                }
            }

            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/forgot_password.html.twig', [
            'requestForm' => $form->createView(),
        ]);
    }

    // ==================== RESET PASSWORD ====================
    #[Route('/reset-password/{token}', name: 'app_reset_password')]
    public function resetPassword(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $user = $entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['resetToken' => $token]);

        if (!$user || $user->getResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Invalid or expired reset token.');
            return $this->redirectToRoute('app_forgot_password');
        }

        $form = $this->createForm(ResetPasswordFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash new password
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            // Clear reset token
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);

            $entityManager->flush();

            $this->addFlash('success', 'Your password has been reset successfully! Please login.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig', [
            'resetForm' => $form->createView(),
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