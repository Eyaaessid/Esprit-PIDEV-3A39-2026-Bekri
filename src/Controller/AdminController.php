<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use App\Form\UserFormType;
use App\Form\AdminUserEditFormType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;

#[Route('/admin', name: 'admin_')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        return $this->render('admin/index.html.twig', [
            'controller_name' => 'AdminController',
        ]);
    }

    // ==================== USERS LIST ====================
    #[Route('/users', name: 'users_list')]
    public function usersList(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role', '');
        $statusFilter = $request->query->get('status', '');
        $sortBy = $request->query->get('sort', 'createdAt');
        $sortOrder = $request->query->get('order', 'DESC');
        
        // Validate sort parameters
        $allowedSorts = ['nom', 'prenom', 'email', 'createdAt'];
        $sortBy = in_array($sortBy, $allowedSorts) ? $sortBy : 'createdAt';
        $sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $queryBuilder = $entityManager->getRepository(Utilisateur::class)->createQueryBuilder('u');

        if ($search) {
            $queryBuilder->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($roleFilter && in_array($roleFilter, ['user', 'coach', 'admin'])) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $roleFilter);
        }

        if ($statusFilter && in_array($statusFilter, ['actif', 'bloque', 'inactif'])) {
            $queryBuilder->andWhere('u.statut = :status')
                ->setParameter('status', $statusFilter);
        }

        $totalUsers = (clone $queryBuilder)->select('COUNT(u.id)')->getQuery()->getSingleScalarResult();

        $users = $queryBuilder
            ->orderBy('u.' . $sortBy, $sortOrder)
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Statistics for cards
        $userCount  = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'user']);
        $coachCount = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'coach']);
        $adminCount = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'admin']);

        return $this->render('admin/users_list.html.twig', [
            'users'       => $users,
            'search'      => $search,
            'roleFilter'  => $roleFilter,
            'statusFilter' => $statusFilter,
            'sortBy' => $sortBy,
            'sortOrder' => $sortOrder,
            'currentPage' => $page,
            'totalPages'  => ceil($totalUsers / $limit),
            'totalUsers'  => $totalUsers,
            'userCount'   => $userCount,
            'coachCount'  => $coachCount,
            'adminCount'  => $adminCount,
        ]);
    }

    // ==================== USER DETAIL ====================
    #[Route('/users/{id}', name: 'user_detail', requirements: ['id' => '\d+'])]
    public function userDetail(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(Utilisateur::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('admin/user_detail.html.twig', [
            'user' => $user,
        ]);
    }

    // ==================== DELETE USER ====================
    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var Utilisateur|null $user */
        $user = $entityManager->getRepository(Utilisateur::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_users_list');
        }

        /** @var Utilisateur $currentUser */
        $currentUser = $this->getUser();

        // Prevent deleting yourself
        if ($user->getId() === $currentUser->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_users_list');
        }

        // CSRF protection
        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Delete avatar if exists
        if ($user->getAvatar()) {
            $avatarPath = $this->getParameter('avatars_directory') . '/' . $user->getAvatar();
            if (file_exists($avatarPath)) {
                unlink($avatarPath);
            }
        }

        $userName = $user->getPrenom() . ' ' . $user->getNom();
        $entityManager->remove($user);
        $entityManager->flush();

        $this->addFlash('success', "User '$userName' has been deleted successfully.");

        return $this->redirectToRoute('admin_users_list');
    }

    // ==================== PROFILE ====================
    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->render('admin/profile.html.twig', [
            'user' => $user,
        ]);
    }

    // ==================== PROFILE EDIT ====================
    #[Route('/profile/edit', name: 'profile_edit')]
    public function profileEdit(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $form = $this->createFormBuilder($user)
            ->add('nom', TextType::class, ['label' => 'Last Name'])
            ->add('prenom', TextType::class, ['label' => 'First Name'])
            ->add('email', EmailType::class, ['label' => 'Email'])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'constraints' => [
                    new File(['maxSize' => '2M', 'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif']])
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => ['label' => 'New Password'],
                'second_options' => ['label' => 'Confirm Password'],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));
            }

            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarsDirectory = $this->getParameter('avatars_directory');
                    $avatarFile->move($avatarsDirectory, $newFilename);

                    if ($user->getAvatar()) {
                        $oldPath = $avatarsDirectory . '/' . $user->getAvatar();
                        if (file_exists($oldPath)) {
                            unlink($oldPath);
                        }
                    }

                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar.');
                }
            }

            $user->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile_edit.html.twig', [
            'user' => $user,
            'form' => $form->createView(),
        ]);
    }

    // ==================== ADD USER ====================
    #[Route('/users/add', name: 'user_add')]
    public function userAdd(
        Request $request,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SluggerInterface $slugger
    ): Response {
        $user = new Utilisateur();
        $form = $this->createForm(UserFormType::class, $user, ['is_edit' => false]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existingUser = $entityManager->getRepository(Utilisateur::class)
                ->findOneBy(['email' => $user->getEmail()]);

            if ($existingUser) {
                $this->addFlash('error', 'This email is already in use.');
                return $this->render('admin/user_add.html.twig', [
                    'form' => $form,
                ]);
            }

            $plainPassword = $form->get('plainPassword')->getData();
            $user->setMotDePasse($passwordHasher->hashPassword($user, $plainPassword));

            $avatarFile = $form->get('avatarFile')->getData();
            if ($avatarFile) {
                $originalFilename = pathinfo($avatarFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename . '-' . uniqid() . '.' . $avatarFile->guessExtension();

                try {
                    $avatarsDirectory = $this->getParameter('avatars_directory');
                    $avatarFile->move($avatarsDirectory, $newFilename);
                    $user->setAvatar($newFilename);
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar.');
                }
            }

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', "User '{$user->getPrenom()} {$user->getNom()}' has been created successfully.");
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('admin/user_add.html.twig', [
            'form' => $form,
        ]);
    }

    // ==================== EDIT USER ====================
    #[Route('/users/{id}/edit', name: 'user_edit', requirements: ['id' => '\d+'])]
    public function userEdit(
        int $id,
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
        $user = $entityManager->getRepository(Utilisateur::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_users_list');
        }

        $form = $this->createForm(AdminUserEditFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', "User '{$user->getPrenom()} {$user->getNom()}' has been updated successfully.");
            return $this->redirectToRoute('admin_users_list');
        }

        return $this->render('admin/user_edit.html.twig', [
            'form' => $form,
            'user' => $user,
        ]);
    }
}