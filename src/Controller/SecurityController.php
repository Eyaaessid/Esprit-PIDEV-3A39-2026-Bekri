<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurRole;
use App\Enum\UtilisateurStatut;
use App\Form\RegistrationFormType;
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
        EntityManagerInterface $entityManager
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

            $this->addFlash('success', 'Votre compte a été créé avec succès ! Vous pouvez maintenant vous connecter.');

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