<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UserProfileType;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationEvenementRepository;
use App\Enum\ParticipationStatut;
use App\Enum\EvenementStatut;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/coach', name: 'coach_')]
#[IsGranted('ROLE_COACH')]
class CoachController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo,
        CacheInterface $cache
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        $stats = $cache->get(sprintf('coach_dashboard_stats_%d', $coach->getId()), function (ItemInterface $item) use ($evenementRepo, $coach) {
            $item->expiresAfter(300);

            return $evenementRepo->getCoachDashboardStats($coach);
        });
        $evenementsRecents = $evenementRepo->findRecentForCoach($coach, 5);

        return $this->render('evenement/coach/dashboard.html.twig', [
            'coach'               => $coach,
            'totalEvenements'     => $stats['totalEvenements'],
            'totalParticipations' => $stats['totalParticipations'],
            'evenementsAVenir'    => $stats['evenementsAVenir'],
            'evenementsTermines'  => $stats['evenementsTermines'],
            'evenementsRecents'   => $evenementsRecents,
        ]);
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('user/profile.html.twig', [
            'user' => $this->getUser(),
        ]);
    }

    #[Route('/profile/edit', name: 'profile_edit')]
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
                $safeFilename     = $slugger->slug($originalFilename);
                $newFilename      = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarsDirectory = $this->getParameter('avatars_directory');
                    $avatarFile->move($avatarsDirectory, $newFilename);

                    if ($user->getAvatar()) {
                        $oldAvatarPath = $avatarsDirectory . '/' . $user->getAvatar();
                        if (file_exists($oldAvatarPath)) {
                            unlink($oldAvatarPath);
                        }
                    }

                    $user->setAvatar($newFilename);
                    $this->addFlash('success', 'Avatar uploaded successfully!');
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                }
            }

            $user->setUpdatedAt(new \DateTimeImmutable());

            try {
                $entityManager->flush();
                $this->addFlash('success', 'Your profile has been updated successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Failed to save profile: ' . $e->getMessage());
            }

            return $this->redirectToRoute('coach_profile');
        }

        return $this->render('user/profile_edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}
