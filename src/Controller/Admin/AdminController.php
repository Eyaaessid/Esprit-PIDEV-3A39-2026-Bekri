<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Entity\Commentaire;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    // Tables page - Posts & Comments Management
    #[Route('/tables', name: 'tables', methods: ['GET'])]
    public function tables(EntityManagerInterface $em): Response
    {
        // Get all posts (non-deleted)
        $posts = $em->getRepository(Post::class)
            ->createQueryBuilder('p')
            ->where('p.deletedAt IS NULL')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        
        // Get all comments (non-deleted)
        $comments = $em->getRepository(Commentaire::class)
            ->createQueryBuilder('c')
            ->where('c.deletedAt IS NULL')
            ->orderBy('c.createdAt', 'DESC')
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
}