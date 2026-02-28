<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use App\Form\UserProfileType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/user', name: 'user_')]
#[IsGranted('IS_AUTHENTICATED_FULLY')]
class UserController extends AbstractController
{
    // ✅ FIX 1: route is '' not '/user' because prefix already adds /user
    #[Route('', name: 'dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('user/index.html.twig', [
            'user' => $user,
        ]);
    }

    // ✅ FIX 2: added missing #[Route] attribute
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        $user = $this->getUser();

        return $this->render('user/profile.html.twig', [
            'user' => $user,
        ]);
    }

    // ✅ FIX 3: added missing #[Route] attribute
    #[Route('/profile/edit', name: 'profile_edit', methods: ['GET', 'POST'])]
    public function profileEdit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $originalEmail = $user->getEmail();

        $form = $this->createForm(UserProfileType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
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

            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword(
                    $passwordHasher->hashPassword($user, $plainPassword)
                );
            }

            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$avatarFile->guessExtension();

                try {
                    $avatarsDirectory = $this->getParameter('avatars_directory');
                    $avatarFile->move($avatarsDirectory, $newFilename);

                    if ($user->getAvatar()) {
                        $oldAvatarPath = $avatarsDirectory.'/'.$user->getAvatar();
                        if (file_exists($oldAvatarPath)) {
                            unlink($oldAvatarPath);
                        }
                    }

                    $user->setAvatar($newFilename);
                    $this->addFlash('success', 'Avatar uploaded successfully!');

                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Unexpected error: ' . $e->getMessage());
                }
            }

            $user->setUpdatedAt(new \DateTime());

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Your profile has been updated successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to save profile: ' . $e->getMessage());
            }

            return $this->redirectToRoute('user_profile');
        }

        return $this->render('user/profile_edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }

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
        $user->setDeactivatedAt(new \DateTime());
        $user->setDeactivatedBy('user');
        $user->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Votre compte a été désactivé.');
        return $this->redirectToRoute('app_logout');
    }
}