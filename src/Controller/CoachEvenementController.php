<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Utilisateur;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Dompdf\Dompdf;
use Dompdf\Options;

#[Route('/coach/evenements')]
#[IsGranted('ROLE_COACH')]
class CoachEvenementController extends AbstractController
{
    #[Route('/', name: 'coach_evenement_index')]
    public function index(EvenementRepository $evenementRepository): Response
    {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        $evenements = $evenementRepository->findBy(
            ['coach' => $coach],
            ['dateDebut' => 'DESC']
        );
        
        return $this->render('evenement/coach/index.html.twig', [
            'evenements' => $evenements,
            'coach' => $coach,
        ]);
    }

    #[Route('/dashboard', name: 'coach_evenement_dashboard')]
    public function dashboard(
        EvenementRepository $evenementRepository,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        $evenements = $evenementRepository->findBy(
            ['coach' => $coach],
            ['dateDebut' => 'DESC']
        );
        
        // Calculate comprehensive statistics
        $totalEvents = count($evenements);
        $totalParticipants = 0;
        $upcomingEvents = 0;
        $completedEvents = 0;
        $now = new \DateTime();
        
        foreach ($evenements as $event) {
            $totalParticipants += $event->getNombreParticipants();
            if ($event->getDateDebut() > $now) {
                $upcomingEvents++;
            } else {
                $completedEvents++;
            }
        }
        
        return $this->render('evenement/coach/dashboard.html.twig', [
            'evenements' => $evenements,
            'coach' => $coach,
            'stats' => [
                'total' => $totalEvents,
                'participants' => $totalParticipants,
                'upcoming' => $upcomingEvents,
                'completed' => $completedEvents,
            ],
        ]);
    }

    #[Route('/new', name: 'coach_evenement_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($request->isMethod('POST')) {
            try {
                /** @var Utilisateur $coach */
                $coach = $this->getUser();
                
                $evenement = new Evenement();
                $evenement->setTitre($request->request->get('titre'));
                $evenement->setDescription($request->request->get('description'));
                $evenement->setType($request->request->get('type'));
                $evenement->setLieu($request->request->get('lieu'));
                $evenement->setCapaciteMax((int) $request->request->get('capaciteMax'));
                $evenement->setStatut('ouvert');
                $evenement->setCoach($coach);
                
                $dateDebut = new \DateTime($request->request->get('dateDebut'));
                $dateFin = new \DateTime($request->request->get('dateFin'));
                
                $evenement->setDateDebut($dateDebut);
                $evenement->setDateFin($dateFin);
                
                // Handle image upload if provided
                $imageFile = $request->files->get('image');
                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                        $newFilename
                    );
                    $evenement->setImage('/uploads/events/' . $newFilename);
                }
                
                $em->persist($evenement);
                $em->flush();
                
                $this->addFlash('success', 'Événement créé avec succès !');
                return $this->redirectToRoute('coach_evenement_index');
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la création : ' . $e->getMessage());
            }
        }
        
        return $this->render('evenement/coach/new.html.twig');
    }

    #[Route('/{id}', name: 'coach_evenement_detail', requirements: ['id' => '\d+'])]
    public function detail(
        Evenement $evenement,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        
        // Verify coach owns this event
        if ($evenement->getCoach() !== $coach) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet événement.');
        }
        
        $participations = $participationRepository->findBy(
            ['evenement' => $evenement, 'statut' => 'confirmé'],
            ['dateInscription' => 'DESC']
        );
        
        return $this->render('evenement/coach/detail.html.twig', [
            'evenement' => $evenement,
            'participations' => $participations,
            'coach' => $coach,
        ]);
    }

    #[Route('/{id}/edit', name: 'coach_evenement_edit', methods: ['GET', 'POST'])]
    public function edit(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        
        // Verify coach owns this event
        if ($evenement->getCoach() !== $coach) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet événement.');
        }
        
        if ($request->isMethod('POST')) {
            try {
                $evenement->setTitre($request->request->get('titre'));
                $evenement->setDescription($request->request->get('description'));
                $evenement->setType($request->request->get('type'));
                $evenement->setLieu($request->request->get('lieu'));
                $evenement->setCapaciteMax((int) $request->request->get('capaciteMax'));
                $evenement->setStatut($request->request->get('statut'));
                
                $dateDebut = new \DateTime($request->request->get('dateDebut'));
                $dateFin = new \DateTime($request->request->get('dateFin'));
                
                $evenement->setDateDebut($dateDebut);
                $evenement->setDateFin($dateFin);
                
                // Handle image upload if provided
                $imageFile = $request->files->get('image');
                if ($imageFile) {
                    $newFilename = uniqid() . '.' . $imageFile->guessExtension();
                    $imageFile->move(
                        $this->getParameter('kernel.project_dir') . '/public/uploads/events',
                        $newFilename
                    );
                    $evenement->setImage('/uploads/events/' . $newFilename);
                }
                
                $em->flush();
                
                $this->addFlash('success', 'Événement modifié avec succès !');
                return $this->redirectToRoute('coach_evenement_detail', ['id' => $evenement->getId()]);
                
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur lors de la modification : ' . $e->getMessage());
            }
        }
        
        return $this->render('evenement/coach/edit.html.twig', [
            'evenement' => $evenement,
        ]);
    }

    #[Route('/{id}/delete', name: 'coach_evenement_delete', methods: ['POST'])]
    public function delete(
        Evenement $evenement,
        EntityManagerInterface $em
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        
        // Verify coach owns this event
        if ($evenement->getCoach() !== $coach) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet événement.');
        }
        
        // Check if event has participants
        if ($evenement->getNombreParticipants() > 0) {
            $this->addFlash('error', 'Impossible de supprimer un événement avec des participants inscrits.');
            return $this->redirectToRoute('coach_evenement_detail', ['id' => $evenement->getId()]);
        }
        
        $em->remove($evenement);
        $em->flush();
        
        $this->addFlash('success', 'Événement supprimé avec succès.');
        return $this->redirectToRoute('coach_evenement_index');
    }

    #[Route('/{id}/participants', name: 'coach_evenement_participants')]
    public function participants(
        Evenement $evenement,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        
        // Verify coach owns this event
        if ($evenement->getCoach() !== $coach) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet événement.');
        }
        
        $participations = $participationRepository->findBy(
            ['evenement' => $evenement],
            ['dateInscription' => 'DESC']
        );
        
        return $this->render('evenement/coach/participants.html.twig', [
            'evenement' => $evenement,
            'participations' => $participations,
            'coach' => $coach,
        ]);
    }

    #[Route('/{id}/export-participants', name: 'coach_evenement_export_participants')]
    public function exportParticipants(
        Evenement $evenement,
        ParticipationEvenementRepository $participationRepository
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();
        
        // Verify coach owns this event
        if ($evenement->getCoach() !== $coach) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cet événement.');
        }
        
        $participations = $participationRepository->findBy(
            ['evenement' => $evenement, 'statut' => 'confirmé'],
            ['dateInscription' => 'ASC']
        );
        
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', true);
        
        $dompdf = new Dompdf($options);
        
        $html = $this->renderView('evenement/coach/export_participants_pdf.html.twig', [
            'evenement' => $evenement,
            'participations' => $participations,
            'coach' => $coach,
        ]);
        
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        
        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="participants-' . $evenement->getId() . '.pdf"',
            ]
        );
    }
}
