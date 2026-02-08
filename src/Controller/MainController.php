<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MainController extends AbstractController
{
    #[Route('/', name: 'home')]
    public function index(): Response
    {
        return $this->render('index.html.twig');
    }

    #[Route('/about', name: 'about')]
    public function about(): Response
    {
        return $this->render('about.html.twig');
    }

    #[Route('/departments', name: 'departments')]
    public function departments(): Response
    {
        return $this->render('departments.html.twig');
    }

    #[Route('/department-details', name: 'department_details')]
    public function departmentDetails(): Response
    {
        return $this->render('department-details.html.twig');
    }
     
    
    


   

    
    #[Route('/testimonials', name: 'testimonials')]
    public function testimonials(): Response
    {
        return $this->render('testimonials.html.twig');
    }

    #[Route('/service-details', name: 'service_details')]
    public function serviceDetails(): Response
    {
        return $this->render('service-details.html.twig');
    }

    #[Route('/doctors', name: 'doctors')]
    public function doctors(): Response
    {
        return $this->render('doctors.html.twig');
    }

    #[Route('/contact', name: 'contact')]
    public function contact(): Response
    {
        return $this->render('contact.html.twig');
    }

    #[Route('/appointment', name: 'appointment')]
    public function appointment(): Response
    {
        return $this->render('appointment.html.twig');
    }

    #[Route('/gallery', name: 'gallery')]
    public function gallery(): Response
    {
        return $this->render('gallery.html.twig');
    }

    #[Route('/faq', name: 'faq')]
    public function faq(): Response
    {
        return $this->render('faq.html.twig');
    }

    #[Route('/terms', name: 'terms')]
    public function terms(): Response
    {
        return $this->render('terms.html.twig');
    }

    #[Route('/privacy', name: 'privacy')]
    public function privacy(): Response
    {
        return $this->render('privacy.html.twig');
    }

    #[Route('/starter-page', name: 'starter_page')]
    public function starterPage(): Response
    {
        return $this->render('starter-page.html.twig');
    }

    // 404 is usually handled by Symfony automatically,
    // but if you want a custom page:
    #[Route('/404', name: '404')]
    public function notFound(): Response
    {
        return $this->render('404.html.twig', [], new Response('', Response::HTTP_NOT_FOUND));
    }
}