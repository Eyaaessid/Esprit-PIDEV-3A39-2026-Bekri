<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use App\Form\UserProfileType;
use App\Service\AiEmotionalInsightService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/user', name: 'user_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserController extends AbstractController
{
    #[Route('', name: 'home')]
    public function dashboard(): Response
    {
        return $this->render('index.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/regenerate-insight', name: 'regenerate_insight', methods: ['POST'])]
    public function regenerateInsight(
        Request $request,
        AiEmotionalInsightService $aiEmotionalInsightService,
        EntityManagerInterface $entityManager
    ): Response {
        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        if (!$user instanceof Utilisateur || $user->getProfilPsychologique() === null) {
            $this->addFlash('error', 'Profil non trouvé.');
            return $this->redirectToRoute('user_dashboard');
        }
        if (!$this->isCsrfTokenValid('regenerate_insight', $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('user_dashboard');
        }
        $profile = $user->getProfilPsychologique();
        $feedback = $aiEmotionalInsightService->generateFromScoreOnly(
            $profile->getScoreGlobal(),
            $profile->getProfilType()
        );
        if ($feedback !== null && $feedback !== '') {
            $profile->setAiFeedback($feedback);
            $entityManager->flush();
            $this->addFlash('success', 'Votre analyse bien-être a été générée.');
        } else {
            $this->addFlash('error', 'La génération de l’analyse a échoué. Vérifiez que GROQ_API_KEY est configurée ou réessayez plus tard.');
        }
        return $this->redirectToRoute('user_dashboard');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        $user = $this->getUser();
        
        return $this->render('user/profile.html.twig', [
            'user' => $user,
        ]);
    }

    #[Route('/profile/edit', name: 'profile_edit', methods: ['GET', 'POST'])]
    public function profileEdit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if email changed and is already taken
            $newEmail = $form->get('email')->getData();
            if ($newEmail !== $originalEmail) {
                $existingUser = $entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $newEmail]);

                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'This email is already in use by another account.');
                    return $this->render('user/profile_edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            // Handle avatar upload
            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                try {
                    $uploadDir = $this->getParameter('avatars_directory');
                    
                    // Delete old avatar
                    if ($user->getAvatar()) {
                        $oldFile = $uploadDir . '/' . $user->getAvatar();
                        if (file_exists($oldFile)) {
                            unlink($oldFile);
                        }
                    }
                    
                    // Generate unique filename
                    $newFilename = 'avatar_' . uniqid() . '.' . $avatarFile->guessExtension();
                    
                    // Move file
                    $avatarFile->move($uploadDir, $newFilename);
                    
                    // Set filename
                    $user->setAvatar($newFilename);
                    
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                }
            }

            // Handle password change with validation
            $plainPassword = $form->get('plainPassword')->getData();
            if (!empty($plainPassword)) {
                // Validate password strength
                if (strlen($plainPassword) < 6) {
                    $this->addFlash('error', 'Password must be at least 6 characters long.');
                    return $this->render('user/profile_edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
                
                if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/', $plainPassword)) {
                    $this->addFlash('error', 'Password must contain at least one uppercase letter, one lowercase letter, and one number.');
                    return $this->render('user/profile_edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
                
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
            }

            $user->setUpdatedAt(new \DateTimeImmutable());
            $entityManager->flush();

            $this->addFlash('success', 'Your profile has been updated successfully!');
            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/profile_edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

    /**
     * User self-deactivation: set INACTIF, deactivated_by=user, then logout.
     */
    #[Route('/profile/deactivate', name: 'profile_deactivate', methods: ['POST'])]
    public function deactivateAccount(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        if (!$user instanceof Utilisateur) {
            return $this->redirectToRoute('user_profile');
        }

        if (!$this->isCsrfTokenValid('deactivate-account', $request->request->get('_token'))) {
            $this->addFlash('error', 'Token de sécurité invalide.');
            return $this->redirectToRoute('user_profile');
        }

        $user->setStatut(UtilisateurStatut::INACTIF);
        $user->setDeactivatedAt(new \DateTimeImmutable());
        $user->setDeactivatedBy('user');
        $user->setUpdatedAt(new \DateTimeImmutable());
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte a été désactivé. Vous pouvez le réactiver à tout moment en vous connectant et en suivant le lien envoyé par email.');
        return $this->redirectToRoute('app_logout');
    }
}