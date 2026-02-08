<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/coach', name: 'coach_')]
#[IsGranted('ROLE_COACH')]
class CoachController extends AbstractController
{
    #[Route('', name: 'dashboard')]
    public function dashboard(): Response
    {
        // Coach uses the frontoffice base template (same as regular users for now)
        return $this->render('base.html.twig', [
            'user' => $this->getUser(),
        ]);
    }
}