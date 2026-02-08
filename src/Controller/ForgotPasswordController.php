<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class ForgotPasswordController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'forgot_password_request', methods: ['GET', 'POST'])]
    public function request(Request $request, EntityManagerInterface $entityManager): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            
            if (!$email) {
                $this->addFlash('error', 'Veuillez entrer une adresse email.');
                return $this->redirectToRoute('forgot_password_request');
            }

            $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Generate reset token
                $resetToken = bin2hex(random_bytes(32));
                $user->setResetToken($resetToken);
                $user->setResetTokenExpiresAt(new \DateTime('+1 hour'));
                
                $entityManager->flush();

                // Generate reset URL
                $resetUrl = $this->generateUrl('forgot_password_reset', 
                    ['token' => $resetToken], 
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                // FOR DEVELOPMENT: Show link in flash message
                $this->addFlash('success', 'Lien de réinitialisation généré avec succès !');
                $this->addFlash('reset_link', $resetUrl);
                
                return $this->redirectToRoute('forgot_password_request');
            } else {
                // Security: don't reveal if email exists
                $this->addFlash('info', 'Si un compte existe avec cet email, un lien de réinitialisation sera envoyé.');
                return $this->redirectToRoute('forgot_password_request');
            }
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
        $user = $entityManager->getRepository(Utilisateur::class)->findOneBy(['resetToken' => $token]);

        // Check if token is valid and not expired
        if (!$user) {
            $this->addFlash('error', 'Token de réinitialisation invalide.');
            return $this->redirectToRoute('forgot_password_request');
        }

        if (!$user->getResetTokenExpiresAt() || $user->getResetTokenExpiresAt() < new \DateTime()) {
            $this->addFlash('error', 'Le token a expiré. Veuillez demander un nouveau lien.');
            return $this->redirectToRoute('forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Validate passwords
            if (!$newPassword || !$confirmPassword) {
                $this->addFlash('error', 'Veuillez remplir tous les champs.');
            } elseif (strlen($newPassword) < 6) {
                $this->addFlash('error', 'Le mot de passe doit contenir au moins 6 caractères.');
            } elseif ($newPassword !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
            } else {
                // Update password
                $user->setPassword($passwordHasher->hashPassword($user, $newPassword));
                $user->setResetToken(null);
                $user->setResetTokenExpiresAt(null);
                $user->setUpdatedAt(new \DateTime());
                
                $entityManager->flush();

                $this->addFlash('success', 'Votre mot de passe a été réinitialisé avec succès ! Vous pouvez maintenant vous connecter.');
                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/reset_password.html.twig', [
            'token' => $token,
            'user' => $user,
        ]);
    }
}