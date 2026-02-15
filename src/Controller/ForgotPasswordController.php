<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    #[Route('/mot-de-passe-oublie', name: 'forgot_password_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request, 
        EntityManagerInterface $entityManager,
        MailerInterface $mailer
    ): Response {
        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));
            
            // Validate email input
            if (empty($email)) {
                $this->addFlash('error', 'Veuillez entrer une adresse email.');
                return $this->redirectToRoute('forgot_password_request');
            }

            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'L\'adresse email n\'est pas valide.');
                return $this->redirectToRoute('forgot_password_request');
            }

            // Find user by email
            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if (!$user) {
                // For security, don't reveal if email exists
                $this->logger->info('Password reset requested for non-existent email', ['email' => $email]);
                
                $this->addFlash('info', 
                    'Si cette adresse email existe dans notre système, un lien de réinitialisation a été envoyé. ' .
                    'Veuillez vérifier votre boîte de réception (et vos spams).'
                );
                return $this->redirectToRoute('forgot_password_request');
            }

            try {
                // Generate secure reset token
                $resetToken = bin2hex(random_bytes(32));
                $expiresAt = new \DateTime('+1 hour');
                
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt($expiresAt);
                
                $entityManager->flush();
                
                $this->logger->info('Reset token generated', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'expires_at' => $expiresAt->format('Y-m-d H:i:s')
                ]);

                // Generate reset URL
                $resetUrl = $this->generateUrl(
                    'forgot_password_reset', 
                    ['token' => $resetToken], 
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $this->logger->info('Reset URL generated', ['url' => $resetUrl]);

                // Create email message
                $emailMessage = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Réinitialisation de votre mot de passe')
                    ->htmlTemplate('emails/reset_password.html.twig')
                    ->context([
                        'resetUrl' => $resetUrl,
                        'user' => $user,
                        'expiresAt' => $expiresAt,
                    ]);

                // Attempt to send email
                $this->logger->info('Attempting to send password reset email', [
                    'to' => $user->getEmail(),
                    'subject' => 'Réinitialisation de votre mot de passe'
                ]);

                try {
                    $mailer->send($emailMessage);
                    
                    $this->logger->info('Password reset email sent successfully', [
                        'to' => $user->getEmail()
                    ]);
                    
                    $this->addFlash('success', 
                        '✅ Un email de réinitialisation a été envoyé à <strong>' . htmlspecialchars($email) . '</strong>. ' .
                        'Veuillez vérifier votre boîte de réception (et vos spams). Le lien expire dans 1 heure.'
                    );
                    
                } catch (TransportExceptionInterface $e) {
                    // Specific transport/SMTP error
                    $this->logger->error('SMTP Transport error sending password reset email', [
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $this->addFlash('error', 
                        '❌ Erreur d\'envoi d\'email (SMTP). Veuillez contacter l\'administrateur. ' .
                        'Détails: ' . $e->getMessage()
                    );
                    
                } catch (\Exception $e) {
                    // Any other error
                    $this->logger->error('Unexpected error sending password reset email', [
                        'error' => $e->getMessage(),
                        'type' => get_class($e),
                        'trace' => $e->getTraceAsString()
                    ]);
                    
                    $this->addFlash('error', 
                        '❌ Une erreur inattendue est survenue lors de l\'envoi de l\'email. ' .
                        'Veuillez réessayer plus tard ou contacter le support.'
                    );
                }
                
            } catch (\Exception $e) {
                // Database or token generation error
                $this->logger->error('Error during password reset process', [
                    'error' => $e->getMessage(),
                    'type' => get_class($e)
                ]);
                
                $this->addFlash('error', 
                    '❌ Une erreur est survenue. Veuillez réessayer.'
                );
            }
            
            return $this->redirectToRoute('forgot_password_request');
        }

        return $this->render('security/forgot_password.html.twig');
    }

    #[Route('/reinitialiser-mot-de-passe/{token}', name: 'forgot_password_reset', methods: ['GET', 'POST'])]
    public function reset(
        string $token,
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        // Find user by reset token
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['resetToken' => $token]);

        if (!$user) {
            $this->logger->warning('Invalid reset token used', ['token' => substr($token, 0, 8) . '...']);
            
            $this->addFlash('error', 
                '❌ Ce lien de réinitialisation n\'est pas valide. ' .
                'Il a peut-être déjà été utilisé ou n\'existe pas. ' .
                'Veuillez demander un nouveau lien.'
            );
            return $this->redirectToRoute('forgot_password_request');
        }

        // Check if token is expired
        if (!$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTime()) {
            $this->logger->info('Expired reset token used', [
                'user_id' => $user->getId(),
                'expired_at' => $user->getResetTokenExpiresAt()?->format('Y-m-d H:i:s')
            ]);
            
            // Clear expired token
            $user->setResetToken(null);
            $user->setResetTokenExpiresAt(null);
            $entityManager->flush();
            
            $this->addFlash('error', 
                '❌ Ce lien de réinitialisation a expiré (validité : 1 heure). ' .
                'Veuillez demander un nouveau lien.'
            );
            return $this->redirectToRoute('forgot_password_request');
        }

        // Handle password reset form submission
        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validate passwords
            $errors = [];
            
            if (empty($newPassword) || empty($confirmPassword)) {
                $errors[] = 'Veuillez remplir tous les champs.';
            }
            
            if (strlen($newPassword) < 6) {
                $errors[] = 'Le mot de passe doit contenir au moins 6 caractères.';
            }
            
            if ($newPassword !== $confirmPassword) {
                $errors[] = 'Les mots de passe ne correspondent pas.';
            }

            if (!empty($errors)) {
                foreach ($errors as $error) {
                    $this->addFlash('error', $error);
                }
                
                return $this->render('security/reset_password.html.twig', [
                    'token' => $token,
                    'user' => $user,
                ]);
            }

            try {
                // Update password
                $hashedPassword = $passwordHasher->hashPassword($user, $newPassword);
                $user->setPassword($hashedPassword);
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $user->setUpdatedAt(new \DateTime());
                
                $entityManager->flush();

                $this->logger->info('Password successfully reset', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail()
                ]);

                $this->addFlash('success', 
                    '✅ Votre mot de passe a été réinitialisé avec succès ! ' .
                    'Vous pouvez maintenant vous connecter avec votre nouveau mot de passe.'
                );
                
                return $this->redirectToRoute('app_login');
                
            } catch (\Exception $e) {
                $this->logger->error('Error resetting password', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage()
                ]);
                
                $this->addFlash('error', 
                    '❌ Une erreur est survenue lors de la réinitialisation. Veuillez réessayer.'
                );
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'user' => $user,
        ]);
    }
}