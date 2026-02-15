<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Form\RegistrationFormType;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
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
    public function __construct(
        private LoggerInterface $logger
    ) {}

    // ==================== LOGIN ====================
    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // If user is already logged in → redirect based on role
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        $error = $authenticationUtils->getLastAuthenticationError();
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
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    // ==================== REGISTER ====================
    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer  // ← ADDED
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute($this->getRedirectRouteByRole());
        }

        $user = new Utilisateur();
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            
            // ✅ VÉRIFIER SI L'EMAIL EXISTE DÉJÀ (AVANT persist)
            $existingUser = $entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $user->getEmail()]);
            
            if ($existingUser) {
                // ✅ Message user-friendly avec lien vers login
                $this->addFlash('error', 'Cet email est déjà utilisé. Vous avez déjà un compte ? <a href="' . $this->generateUrl('app_login') . '" class="alert-link">Connectez-vous ici</a>.');
                
                // ✅ Retourner au formulaire (pré-rempli)
                return $this->render('admin/signup.html.twig', [
                    'registrationForm' => $form->createView(),
                ]);
            }
            
            // ✅ Email unique → continuer avec l'inscription
            $user->setPassword(
                $passwordHasher->hashPassword(
                    $user,
                    $form->get('plainPassword')->getData()
                )
            );

            $user->setRole(UtilisateurRole::USER);
            $user->setStatut(UtilisateurStatut::ACTIF);

            $entityManager->persist($user);
            $entityManager->flush();

            $this->logger->info('New user registered', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'role' => $user->getRole()->value
            ]);

            // ============================================
            // 🎉 SEND WELCOME EMAIL
            // ============================================
            try {
                // Generate login URL
                $loginUrl = $this->generateUrl(
                    'app_login', 
                    [], 
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $this->logger->info('Attempting to send welcome email', [
                    'to' => $user->getEmail(),
                    'user_id' => $user->getId()
                ]);

                // Create welcome email
                $email = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Bienvenue sur Bekri Wellbeing! 🎉')
                    ->htmlTemplate('emails/welcome_email.html.twig')
                    ->context([
                        'user' => $user,
                        'loginUrl' => $loginUrl,
                    ]);

                // Send email
                $mailer->send($email);

                $this->logger->info('Welcome email sent successfully', [
                    'to' => $user->getEmail(),
                    'user_id' => $user->getId()
                ]);

                $this->addFlash('success', 
                    '✅ Votre compte a été créé avec succès! Un email de bienvenue vous a été envoyé à ' . 
                    htmlspecialchars($user->getEmail()) . '. Vous pouvez maintenant vous connecter.'
                );

            } catch (\Exception $e) {
                // Log error but don't prevent registration
                $this->logger->error('Failed to send welcome email', [
                    'user_id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'error' => $e->getMessage()
                ]);

                // Still show success message for registration
                $this->addFlash('success', 
                    '✅ Votre compte a été créé avec succès! Vous pouvez maintenant vous connecter.'
                );
                $this->addFlash('warning', 
                    '⚠️ Note: L\'email de bienvenue n\'a pas pu être envoyé, mais votre compte est bien actif.'
                );
            }
            // ============================================
            // END WELCOME EMAIL
            // ============================================

            return $this->redirectToRoute('app_login');
        }

        return $this->render('admin/signup.html.twig', [
            'registrationForm' => $form->createView(),
        ]);
    }

    // ==================== HELPER METHOD ====================
    private function getRedirectRouteByRole(): string
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return 'admin_dashboard';
        }

        if ($this->isGranted('ROLE_COACH')) {
            return 'home';
        }

        // Normal users (role USER) go to the public homepage
        return 'home';
    }
}