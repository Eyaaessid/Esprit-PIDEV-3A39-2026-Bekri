<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomeController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function index(): Response
    {
        // If already logged in, redirect to dashboard
        if ($this->getUser()) {
            return $this->redirectToRoute('user_dashboard'); // ✅ correct route name
        }

        return $this->render('home/index.html.twig');
    }
}