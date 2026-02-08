<?php

namespace App\Controller\Admin;

use App\Repository\CommentaireRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    public function __construct(
        private PostRepository $postRepository,
        private CommentaireRepository $commentaireRepository
    ) {
    }

    // Dashboard / Home with Posts and Comments
    #[Route('', name: '', methods: ['GET'])]
    public function dashboard(): Response
    {
        // Get all posts (not deleted)
        $posts = $this->postRepository->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(50) // Limit to recent 50 posts
            ->getQuery()
            ->getResult();

        // Get all comments (not deleted)
        $comments = $this->commentaireRepository->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(50) // Limit to recent 50 comments
            ->getQuery()
            ->getResult();

        return $this->render('admin/table.html.twig', [
            'posts' => $posts,
            'comments' => $comments,
        ]);
    }

    // Tables page
    #[Route('/tables', name: 'tables', methods: ['GET'])]
    public function tables(): Response
    {
        // Get all posts (not deleted)
        $posts = $this->postRepository->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->setMaxResults(50) // Limit to recent 50 posts
            ->getQuery()
            ->getResult();

        // Get all comments (not deleted)
        $comments = $this->commentaireRepository->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
            ->setMaxResults(50) // Limit to recent 50 comments
            ->getQuery()
            ->getResult();

        return $this->render('admin/table.html.twig', [
            'posts' => $posts,
            'comments' => $comments,
        ]);
    }

    // Typography page
    #[Route('/typography', name: 'typography', methods: ['GET'])]
    public function typography(): Response
    {
        return $this->render('admin/typography.html.twig');
    }

    // Widgets page
    #[Route('/widgets', name: 'widgets', methods: ['GET'])]
    public function widgets(): Response
    {
        return $this->render('admin/widget.html.twig');
    }

    // Forms page
    #[Route('/forms', name: 'forms', methods: ['GET'])]
    public function forms(): Response
    {
        return $this->render('admin/form.html.twig');
    }

    // Elements → Buttons
    #[Route('/elements/buttons', name: 'elements_buttons', methods: ['GET'])]
    public function buttons(): Response
    {
        return $this->render('admin/button.html.twig');
    }

    // Elements → Other Elements
    #[Route('/elements/other', name: 'elements_other', methods: ['GET'])]
    public function otherElements(): Response
    {
        return $this->render('admin/element.html.twig');
    }

    // Charts page
    #[Route('/charts', name: 'charts', methods: ['GET'])]
    public function charts(): Response
    {
        return $this->render('admin/chart.html.twig');
    }

    // Blank page
    #[Route('/blank', name: 'blank', methods: ['GET'])]
    public function blank(): Response
    {
        return $this->render('admin/blank.html.twig');
    }

    // 404 page (useful for testing)
    #[Route('/404', name: '404', methods: ['GET'])]
    public function notFound(): Response
    {
        return $this->render('admin/404.html.twig');
    }

    // Sign In page (public-like, but inside admin folder for consistency)
    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        return $this->render('admin/signin.html.twig');
    }

    // Sign Up page
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('admin/signup.html.twig');
    }

    // Profile page
    #[Route('/profile', name: 'profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->render('admin/profile.html.twig');
    }

    // Settings page
    #[Route('/settings', name: 'settings', methods: ['GET'])]
    public function settings(): Response
    {
        return $this->render('admin/settings.html.twig');
    }

    // Settings - General
    #[Route('/settings/general', name: 'settings_general', methods: ['GET'])]
    public function settingsGeneral(): Response
    {
        return $this->render('admin/settings/general.html.twig');
    }

    // Settings - Security
    #[Route('/settings/security', name: 'settings_security', methods: ['GET'])]
    public function settingsSecurity(): Response
    {
        return $this->render('admin/settings/security.html.twig');
    }

    // Users page
    #[Route('/users', name: 'users', methods: ['GET'])]
    public function users(): Response
    {
        return $this->render('admin/users.html.twig');
    }

    // Logout
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): Response
    {
        // This will be handled by security system
        return $this->redirectToRoute('admin_login');
    }
}