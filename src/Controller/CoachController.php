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

#[Route('/coach', name: 'coach_')]
#[IsGranted('ROLE_COACH')]
class CoachController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        $mesEvenements   = $evenementRepo->findBy(['coach' => $coach]);
        $totalEvenements = count($mesEvenements);

        $totalParticipations = 0;
        $evenementsAVenir    = 0;
        $evenementsTermines  = 0;

        foreach ($mesEvenements as $evenement) {
            $participations = $participationRepo->count([
                'evenement' => $evenement,
                'statut'    => ParticipationStatut::INSCRIT,
            ]);
            $totalParticipations += $participations;

            if ($evenement->getDateDebut() > new \DateTime()) {
                $evenementsAVenir++;
            } elseif ($evenement->getStatut() === EvenementStatut::FINISHED) {
                $evenementsTermines++;
            }
        }

        $evenementsRecents = $evenementRepo->findBy(
            ['coach' => $coach],
            ['createdAt' => 'DESC'],
            5
        );

        return $this->render('evenement/coach/dashboard.html.twig', [
            'coach'               => $coach,
            'totalEvenements'     => $totalEvenements,
            'totalParticipations' => $totalParticipations,
            'evenementsAVenir'    => $evenementsAVenir,
            'evenementsTermines'  => $evenementsTermines,
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