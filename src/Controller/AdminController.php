<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\Length;

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

    #[Route('/users', name: 'users_list')]
    public function usersList(Request $request, EntityManagerInterface $entityManager): Response
    {
        $search = $request->query->get('search', '');
        $roleFilter = $request->query->get('role', '');
        $page = max(1, $request->query->getInt('page', 1));
        $limit = 10;

        $queryBuilder = $entityManager->getRepository(Utilisateur::class)->createQueryBuilder('u');

        // Search by name or email
        if ($search) {
            $queryBuilder->andWhere('u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        // Filter by role
        if ($roleFilter && in_array($roleFilter, ['user', 'coach', 'admin'])) {
            $queryBuilder->andWhere('u.role = :role')
                ->setParameter('role', $roleFilter);
        }

        // Order by creation date (newest first)
        $queryBuilder->orderBy('u.createdAt', 'DESC');

        // Pagination
        $totalUsers = count($queryBuilder->getQuery()->getResult());
        $totalPages = ceil($totalUsers / $limit);
        
        $users = $queryBuilder
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        // Count users by role
        $userCount = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'user']);
        $coachCount = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'coach']);
        $adminCount = $entityManager->getRepository(Utilisateur::class)->count(['role' => 'admin']);

        return $this->render('admin/users_list.html.twig', [
            'users' => $users,
            'search' => $search,
            'roleFilter' => $roleFilter,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'totalUsers' => $totalUsers,
            'userCount' => $userCount,
            'coachCount' => $coachCount,
            'adminCount' => $adminCount,
        ]);
    }

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

    #[Route('/users/{id}/delete', name: 'user_delete', methods: ['POST'])]
    public function userDelete(int $id, Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $entityManager->getRepository(Utilisateur::class)->find($id);

        if (!$user) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Prevent admin from deleting themselves
        if ($user->getId() === $this->getUser()->getId()) {
            $this->addFlash('error', 'You cannot delete your own account.');
            return $this->redirectToRoute('admin_users_list');
        }

        // Check CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete-user-' . $user->getId(), $token)) {
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

    #[Route('/tables', name: 'tables')]
    public function tables(): Response
    {
        return $this->render('admin/table.html.twig');
    }

    #[Route('/typography', name: 'typography')]
    public function typography(): Response
    {
        return $this->render('admin/typography.html.twig');
    }

    #[Route('/widgets', name: 'widgets')]
    public function widgets(): Response
    {
        return $this->render('admin/widget.html.twig');
    }

    #[Route('/forms', name: 'forms')]
    public function forms(): Response
    {
        return $this->render('admin/form.html.twig');
    }

    #[Route('/elements/buttons', name: 'elements_buttons')]
    public function elementsButtons(): Response
    {
        return $this->render('admin/element.html.twig');
    }

    #[Route('/elements/other', name: 'elements_other')]
    public function elementsOther(): Response
    {
        return $this->render('admin/element.html.twig');
    }

    #[Route('/charts', name: 'charts')]
    public function charts(): Response
    {
        return $this->render('admin/chart.html.twig');
    }

    #[Route('/blank', name: 'blank')]
    public function blank(): Response
    {
        return $this->render('admin/blank.html.twig');
    }

    #[Route('/404', name: '404')]
    public function notFound(): Response
    {
        return $this->render('admin/404.html.twig');
    }

    #[Route('/profile', name: 'profile')]
    public function profile(): Response
    {
        return $this->render('admin/profile.html.twig', [
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
        
        $form = $this->createFormBuilder($user)
            ->add('nom', TextType::class, [
                'label' => 'Last Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('prenom', TextType::class, [
                'label' => 'First Name',
                'attr' => ['class' => 'form-control']
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr' => ['class' => 'form-control']
            ])
            ->add('avatarFile', FileType::class, [
                'label' => 'Profile Picture',
                'mapped' => false,
                'required' => false,
                'attr' => ['class' => 'form-control', 'accept' => 'image/*'],
                'constraints' => [
                    new File([
                        'maxSize' => '2M',
                        'mimeTypes' => ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
                        'mimeTypesMessage' => 'Please upload a valid image',
                    ])
                ],
            ])
            ->add('plainPassword', RepeatedType::class, [
                'type' => PasswordType::class,
                'mapped' => false,
                'required' => false,
                'first_options' => [
                    'label' => 'New Password',
                    'attr' => ['class' => 'form-control', 'placeholder' => 'Leave blank to keep current']
                ],
                'second_options' => [
                    'label' => 'Confirm Password',
                    'attr' => ['class' => 'form-control']
                ],
                'constraints' => [
                    new Length(['min' => 6, 'minMessage' => 'Password must be at least {{ limit }} characters']),
                ],
            ])
            ->getForm();

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newEmail = $form->get('email')->getData();
            if ($newEmail !== $originalEmail) {
                $existingUser = $entityManager->getRepository(Utilisateur::class)
                    ->findOneBy(['email' => $newEmail]);
                
                if ($existingUser && $existingUser->getId() !== $user->getId()) {
                    $this->addFlash('error', 'This email is already in use by another account.');
                    return $this->render('admin/profile_edit.html.twig', [
                        'user' => $user,
                        'form' => $form,
                    ]);
                }
            }

            $plainPassword = $form->get('plainPassword')->getData();
            if ($plainPassword) {
                $user->setPassword($passwordHasher->hashPassword($user, $plainPassword));
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
                } catch (FileException $e) {
                    $this->addFlash('error', 'Failed to upload avatar: ' . $e->getMessage());
                }
            }

            $user->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Profile updated successfully!');
            return $this->redirectToRoute('admin_profile');
        }

        return $this->render('admin/profile_edit.html.twig', [
            'user' => $user,
            'form' => $form,
        ]);
    }
}