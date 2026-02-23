<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use App\Entity\ParticipationEvenement;
use App\Repository\EvenementRepository;
use App\Repository\UtilisateurRepository;
use App\Repository\ParticipationEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    #[Route('/', name: 'evenement_liste')]
    public function liste(EvenementRepository $evenementRepository): Response
    {
        $evenements = $evenementRepository->findBy(
            ['statut' => 'ouvert'],
            ['dateDebut' => 'ASC']
        );

        return $this->render('evenement/liste.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/{id}', name: 'evenement_detail', requirements: ['id' => '\d+'])]
    public function detail(
        Evenement $evenement,
        Request $request,
        ParticipationEvenementRepository $participationRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $this->getUser();
        
        // Fallback for anonymous session flow
        if (!$utilisateur) {
            $userId = $request->getSession()->get('user_id');
            if ($userId) {
                $utilisateur = $utilisateurRepository->find($userId);
            }
        }

        $isParticipant = false;
        if ($utilisateur) {
            $participation = $participationRepository->findActiveParticipation($utilisateur, $evenement);
            $isParticipant = ($participation !== null);
        }

        return $this->render('evenement/detail.html.twig', [
            'evenement' => $evenement,
            'user' => $utilisateur,
            'isParticipant' => $isParticipant,
        ]);
    }

    #[Route('/{id}/participer', name: 'evenement_participer', methods: ['POST'])]
    public function participer(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepository,
        ParticipationEvenementRepository $participationRepository,
        \Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface $passwordHasher
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $this->getUser();
        
        // Fallback or session identification
        if (!$utilisateur) {
            $userId = $request->getSession()->get('user_id');
            if ($userId) {
                $utilisateur = $utilisateurRepository->find($userId);
            }
        }

        if (!$utilisateur) {
            // Lazy-create "Visiteur" only if strictly necessary
            $utilisateur = new Utilisateur();
            $utilisateur->setNom('Visiteur');
            $utilisateur->setPrenom('Anonyme');
            $utilisateur->setEmail('visiteur' . uniqid() . '@bekri.local');
            $utilisateur->setDateNaissance(new \DateTime('-18 years'));
            
            // Random secure password
            $tempPass = bin2hex(random_bytes(16));
            $utilisateur->setPassword($passwordHasher->hashPassword($utilisateur, $tempPass));
            
            $em->persist($utilisateur);
            $em->flush(); // Flush now to get ID
            
            $request->getSession()->set('user_id', $utilisateur->getId());
        }

        // Vérifier si l'événement est complet
        if ($evenement->isComplet()) {
            $this->addFlash('error', 'Cet événement est complet.');
            return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
        }

        // Vérifier si l'utilisateur est déjà inscrit
        $participationExistante = $participationRepository->findActiveParticipation($utilisateur, $evenement);
        if ($participationExistante) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement.');
            return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
        }

        // Créer la participation
        $participation = new ParticipationEvenement();
        $participation->setUtilisateur($utilisateur);
        $participation->setEvenement($evenement);
        $participation->setStatut('confirmé');

        $em->persist($participation);
        $em->flush();

        $this->addFlash('success', 'Votre inscription a été confirmée avec succès !');
        return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
    }

    #[Route('/{id}/annuler', name: 'evenement_annuler', methods: ['POST'])]
    public function annuler(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepository,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        // 1. Prioritize Symfony Security User
        $utilisateur = $this->getUser();
        
        if (!$utilisateur) {
            // 2. Fallback to session user
            $userId = $request->getSession()->get('user_id');
            if ($userId) {
                $utilisateur = $utilisateurRepository->find($userId);
            }
        }

        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être inscrit pour annuler une participation.');
            return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
        }

        // Trouver la participation
        $participation = $participationRepository->findActiveParticipation($utilisateur, $evenement);
        
        if (!$participation) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
        }

        // Annuler la participation
        $participation->setStatut('annulé');
        $em->flush();

        $this->addFlash('success', 'Votre participation a été annulée.');
        return $this->redirectToRoute('evenement_detail', ['id' => $evenement->getId()]);
    }

    #[Route('/mes-participations', name: 'evenement_mes_participations')]
    public function mesParticipations(
        Request $request,
        UtilisateurRepository $utilisateurRepository,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        /** @var Utilisateur|null $utilisateur */
        $utilisateur = $this->getUser();
        
        if (!$utilisateur) {
            $userId = $request->getSession()->get('user_id');
            if ($userId) {
                $utilisateur = $utilisateurRepository->find($userId);
                if (!$utilisateur) {
                    $request->getSession()->remove('user_id');
                }
            }
        }
        
        if (!$utilisateur) {
            return $this->render('evenement/mes_participations.html.twig', [
                'participations' => [],
                'utilisateur' => null,
            ]);
        }

        $participations = $participationRepository->findUserHistory($utilisateur);

        return $this->render('evenement/mes_participations.html.twig', [
            'participations' => $participations,
            'utilisateur' => $utilisateur,
        ]);
    }

    // ========== ROUTES COACH (CRUD) ==========
    
    #[Route('/coach/dashboard', name: 'evenement_coach_dashboard')]
    public function coachDashboard(EvenementRepository $evenementRepository): Response
    {
        $user = $this->getUser();
        $evenements = $evenementRepository->findBy(['coach' => $user]);
        
        return $this->render('evenement/coach/dashboard.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/coach', name: 'evenement_coach_index')]
    public function coachIndex(EvenementRepository $evenementRepository): Response
    {
        $user = $this->getUser();
        $evenements = $evenementRepository->findBy(['coach' => $user], ['dateDebut' => 'DESC']);
        
        return $this->render('evenement/coach/index.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/coach/new', name: 'evenement_coach_new', methods: ['GET', 'POST'])]
    public function coachNew(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $evenement = new Evenement();
                $evenement->setTitre($request->request->get('titre'));
                $evenement->setDescription($request->request->get('description'));
                $evenement->setType($request->request->get('type'));
                
                $dateDebut = $request->request->get('dateDebut');
                $dateFin = $request->request->get('dateFin');
                
                if (!$dateDebut || !$dateFin) {
                    throw new \InvalidArgumentException('Les dates sont obligatoires.');
                }

                $evenement->setDateDebut(new \DateTime($dateDebut));
                $evenement->setDateFin(new \DateTime($dateFin));
                $evenement->setLieu($request->request->get('lieu'));
                $evenement->setCapaciteMax((int)$request->request->get('capaciteMax'));
                $evenement->setStatut('ouvert');
                
                // Assign the current user as the coach
                /** @var Utilisateur $user */
                $user = $this->getUser();
                if ($user) {
                    $evenement->setCoach($user);
                }
                
                $em->persist($evenement);
                $em->flush();
                
                $this->addFlash('success', 'Événement créé avec succès !');
                return $this->redirectToRoute('evenement_coach_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }
        
        return $this->render('evenement/coach/new.html.twig');
    }

    #[Route('/coach/{id}/edit', name: 'evenement_coach_edit', methods: ['GET', 'POST'])]
    public function coachEdit(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            try {
                $evenement->setTitre($request->request->get('titre'));
                $evenement->setDescription($request->request->get('description'));
                $evenement->setType($request->request->get('type'));
                
                $dateDebut = $request->request->get('dateDebut');
                $dateFin = $request->request->get('dateFin');
                
                if ($dateDebut) $evenement->setDateDebut(new \DateTime($dateDebut));
                if ($dateFin) $evenement->setDateFin(new \DateTime($dateFin));
                
                $evenement->setLieu($request->request->get('lieu'));
                $evenement->setCapaciteMax((int)$request->request->get('capaciteMax'));
                $evenement->setStatut($request->request->get('statut'));
                
                $em->flush();
                
                $this->addFlash('success', 'Événement modifié avec succès !');
                return $this->redirectToRoute('evenement_coach_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        }
        
        return $this->render('evenement/coach/edit.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/coach/{id}/delete', name: 'evenement_coach_delete', methods: ['POST'])]
    public function coachDelete(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $em->remove($evenement);
        $em->flush();
        
        $this->addFlash('success', 'Événement supprimé avec succès !');
        return $this->redirectToRoute('evenement_coach_index');
    }

    // ========== ROUTES ADMIN (CRUD/Supervision) ==========
    
    #[Route('/admin/supervision', name: 'evenement_admin_supervision')]
    public function adminSupervision(
        EvenementRepository $evenementRepository,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        $evenements = $evenementRepository->findAll();
        
        return $this->render('evenement/admin/supervision.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/admin/{id}/update-status', name: 'evenement_admin_update_status', methods: ['POST'])]
    public function adminUpdateStatus(Evenement $evenement, Request $request, EntityManagerInterface $em): Response
    {
        $newStatus = $request->request->get('statut');
        $evenement->setStatut($newStatus);
        $em->flush();
        
        $this->addFlash('success', 'Statut de l\'événement mis à jour !');
        return $this->redirectToRoute('evenement_admin_supervision');
    }

    #[Route('/admin/{id}/delete', name: 'evenement_admin_delete', methods: ['POST'])]
    public function adminDelete(Evenement $evenement, EntityManagerInterface $em): Response
    {
        $em->remove($evenement);
        $em->flush();
        
        $this->addFlash('success', 'Événement supprimé avec succès !');
        return $this->redirectToRoute('evenement_admin_supervision');
    }

    #[Route('/{id}/scan-summary', name: 'evenement_scan_summary', requirements: ['id' => '\d+'])]
    public function scanSummary(
        Evenement $evenement,
        Request $request,
        ParticipationEvenementRepository $participationRepository,
        UtilisateurRepository $utilisateurRepository
    ): Response {
        $utilisateur = $this->getUser();
        
        if (!$utilisateur) {
            $userId = $request->getSession()->get('user_id');
            if ($userId) {
                $utilisateur = $utilisateurRepository->find($userId);
            }
        }

        $isParticipant = false;
        if ($utilisateur) {
            $participation = $participationRepository->findActiveParticipation($utilisateur, $evenement);
            $isParticipant = ($participation !== null);
        }

        return $this->render('evenement/scan_summary.html.twig', [
            'evenement' => $evenement,
            'user' => $utilisateur,
            'isParticipant' => $isParticipant,
        ]);
    }

    #[Route('/qr-code/{id}', name: 'evenement_qr_code')]
    public function qrCode(Evenement $evenement): Response
    {
        try {
            // Generate simple QR code with event URL
            $eventUrl = $this->generateUrl(
                'evenement_detail', 
                ['id' => $evenement->getId()], 
                UrlGeneratorInterface::ABSOLUTE_URL
            );

            // Create QR code
            $qrResult = Builder::create()
                ->writer(new PngWriter())
                ->data($eventUrl)
                ->encoding(new Encoding('UTF-8'))
                ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
                ->size(400)
                ->margin(20)
                ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
                ->build();

            // Return the QR code image directly
            return new Response(
                $qrResult->getString(),
                200,
                [
                    'Content-Type' => 'image/png',
                    'Content-Disposition' => 'inline; filename="ticket-event-' . $evenement->getId() . '.png"',
                    'Cache-Control' => 'public, max-age=3600',
                ]
            );

        } catch (\Exception $e) {
            // If QR code generation fails, return a simple error image
            // Create a 400x400 white image with error text
            $width = 400;
            $height = 400;

            // Create image without GD - return SVG instead
            $svg = '<?xml version="1.0" encoding="UTF-8"?>
    <svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#f8f9fa"/>
    <text x="50%" y="45%" text-anchor="middle" font-family="Arial" font-size="16" fill="#6c757d">
        QR Code Error
    </text>
    <text x="50%" y="55%" text-anchor="middle" font-family="Arial" font-size="12" fill="#999">
        ' . htmlspecialchars($e->getMessage()) . '
    </text>
    </svg>';

            return new Response(
                $svg,
                200,
                [
                    'Content-Type' => 'image/svg+xml',
                    'Cache-Control' => 'no-cache',
                ]
            );
        }
    }

    #[Route('/stats', name: 'evenement_stats')]
    public function stats(EvenementRepository $evenementRepository): Response
    {
        $mostPopularData = $evenementRepository->findMostPopular(10);
        $categoryData = $evenementRepository->findStatsByCategory();
        $monthlyData = $evenementRepository->findStatsByMonth();
        
        return $this->render('evenement/stats.html.twig', [
            'mostPopularData' => $mostPopularData,
            'categoryData' => $categoryData,
            'monthlyData' => $monthlyData,
        ]);
    }

    #[Route('/calendar', name: 'evenement_calendar')]
    public function calendar(): Response
    {
        return $this->render('evenement/calendrier.html.twig');
    }

    #[Route('/calendar/data', name: 'evenement_calendar_data')]
    public function calendarData(EvenementRepository $evenementRepository): Response
    {
        $evenements = $evenementRepository->findAll();
        $data = [];

        foreach ($evenements as $event) {
            $url = $this->generateUrl('evenement_detail', ['id' => $event->getId()]);
            
            if ($this->isGranted('ROLE_ADMIN')) {
                $url = $this->generateUrl('evenement_admin_supervision'); // Or a specific admin detail if we create it
            } elseif ($this->isGranted('ROLE_COACH') && $event->getCoach() === $this->getUser()) {
                $url = $this->generateUrl('evenement_coach_edit', ['id' => $event->getId()]);
            }

            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitre(),
                'start' => $event->getDateDebut()->format('Y-m-d\TH:i:s'),
                'end' => $event->getDateFin() ? $event->getDateFin()->format('Y-m-d\TH:i:s') : null,
                'url' => $url,
                'backgroundColor' => $this->getEventColor($event->getType()),
            ];
        }

        return $this->json($data);
    }

    private function getEventColor(string $type): string
    {
        return match (strtolower($type)) {
            'atelier' => '#4e73df',
            'méditation' => '#1cc88a',
            'défi santé' => '#f6c23e',
            'conférence' => '#36b9cc',
            default => '#858796',
        };
    }

    #[Route('/search-ajax', name: 'evenement_search_ajax')]
    public function searchAjax(Request $request, EvenementRepository $repo): JsonResponse
    {
        $term = $request->query->get('q', '');
        $evenements = $repo->searchByTerm($term);
        
        $data = [];
        foreach ($evenements as $e) {
            $data[] = [
                'id' => $e->getId(),
                'titre' => $e->getTitre(),
                'type' => $e->getType(),
                'dateDebut' => $e->getDateDebut()->format('d/m/Y H:i'),
                'lieu' => $e->getLieu(),
                'url' => $this->generateUrl('evenement_detail', ['id' => $e->getId()]),
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/admin/{id}/export-pdf', name: 'evenement_admin_export_pdf')]
    public function exportPdf(Evenement $evenement): Response
    {
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        
        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('evenement/export_pdf.html.twig', [
            'evenement' => $evenement,
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="event-' . $evenement->getId() . '.pdf"',
        ]);
    }

    #[Route('/admin/export-all-pdf', name: 'evenement_admin_export_all_pdf')]
    public function exportAllPdf(EvenementRepository $evenementRepository): Response
    {
        $evenements = $evenementRepository->findAll();
        
        $pdfOptions = new Options();
        $pdfOptions->set('defaultFont', 'Arial');
        $pdfOptions->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($pdfOptions);
        
        $html = $this->renderView('evenement/export_all_pdf.html.twig', [
            'evenements' => $evenements,
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();
        
        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="all-events-' . date('Y-m-d') . '.pdf"',
        ]);
    }
}
