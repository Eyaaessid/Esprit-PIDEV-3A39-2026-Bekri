<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\ParticipationEvenement;
use App\Entity\Utilisateur;
use App\Enum\EvenementStatut;
use App\Enum\ParticipationStatut;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationEvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Dompdf\Dompdf;
use Dompdf\Options;
use Knp\Component\Pager\PaginatorInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    // ========== Page d'accueil du module ==========

    #[Route('/home', name: 'evenements_home')]
    public function home(EvenementRepository $evenementRepository, CacheInterface $cache): Response
    {
        $totalEvenements = $cache->get('evenements_home_total', function (ItemInterface $item) use ($evenementRepository) {
            $item->expiresAfter(300);

            return $evenementRepository->count([]);
        });

        return $this->render('evenement/evenements_home.html.twig', [
            'totalEvenements' => $totalEvenements,
        ]);
    }

    // ========== Page d'accès rapide ==========

    #[Route('/acces-rapide', name: 'evenements_acces_rapide', priority: 10)]
    public function accesRapide(): Response
    {
        return $this->render('evenement/index_acces.html.twig');
    }

    // ========== US 5.2 - Utilisateur: Consulter les événements ==========

    #[Route('/', name: 'evenements_list')]
    public function list(
        Request $request,
        EvenementRepository $evenementRepository,
        ParticipationEvenementRepository $participationRepo,
        PaginatorInterface $paginator
    ): Response|JsonResponse {
        $search = trim((string) $request->query->get('q', ''));
        $queryBuilder = $evenementRepository->createPublicListQueryBuilder($search !== '' ? $search : null);

        if ($search !== '') {
            $events = $queryBuilder
                ->setMaxResults(30)
                ->getQuery()
                ->getResult();

            return new JsonResponse(array_map(function (Evenement $event) {
                return [
                    'id' => $event->getId(),
                    'titre' => $event->getTitre(),
                    'type' => $event->getType()?->value,
                    'dateDebut' => $event->getDateDebut()?->format('d/m/Y H:i'),
                    'url' => $this->generateUrl('evenements_show', ['id' => $event->getId()]),
                ];
            }, $events));
        }

        $evenements = $paginator->paginate(
            $queryBuilder,
            max(1, $request->query->getInt('page', 1)),
            9
        );

        return $this->render('evenement/list.html.twig', [
            'evenements' => $evenements,
            'totalEvenements' => $evenements->getTotalItemCount(),
            'eventParticipationCounts' => $participationRepo->getActiveCountsByEventIds(
                $this->extractEventIds($this->normalizeEvents($evenements->getItems()))
            ),
        ]);
    }

    #[Route('/{id}', name: 'evenements_show', requirements: ['id' => '\d+'])]
    public function show(
        int $id,
        EvenementRepository $evenementRepository
    ): Response {
        $evenement = $evenementRepository->findOneDetailed($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        /** @var Utilisateur|null $user */
        $user = $this->getUser();
        $allParticipations = $evenement->getParticipations()->toArray();
        $activeParticipations = array_values(array_filter(
            $allParticipations,
            static fn (ParticipationEvenement $participation): bool => $participation->getStatut() === ParticipationStatut::INSCRIT
        ));

        $participation = null;
        $isInscrit     = false;

        if ($user instanceof Utilisateur) {
            foreach ($allParticipations as $existingParticipation) {
                if ($existingParticipation->getUtilisateur()?->getId() === $user->getId()) {
                    $participation = $existingParticipation;
                    $isInscrit = $existingParticipation->getStatut() === ParticipationStatut::INSCRIT;
                    break;
                }
            }
        }

        $participationsActives = count($activeParticipations);
        $placesRestantes = max(0, $evenement->getCapaciteMax() - $participationsActives);

        return $this->render('evenement/show.html.twig', [
            'evenement'             => $evenement,
            'isInscrit'             => $isInscrit,
            'participation'         => $participation,
            'placesRestantes'       => $placesRestantes,
            'participationsActives' => $participationsActives,
            'activeParticipations'  => $activeParticipations,
        ]);
    }

    // ========== Export PDF - Un seul événement ==========

    #[Route('/{id}/export-pdf', name: 'evenements_export_pdf', requirements: ['id' => '\d+'], priority: 10)]
    public function exportPdf(int $id, EvenementRepository $evenementRepository, ParticipationEvenementRepository $participationRepo): Response
    {
        $evenement = $evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        $html = $this->renderView('evenement/export_pdf.html.twig', [
            'evenement' => $evenement,
            'participantCount' => $participationRepo->countActiveByEvenement($evenement),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'evenement-' . $evenement->getId() . '-' . date('Ymd') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    // ========== Export PDF - Tous les événements ==========

    #[Route('/export-all-pdf', name: 'evenements_export_all_pdf', priority: 10)]
    public function exportAllPdf(EvenementRepository $evenementRepository, ParticipationEvenementRepository $participationRepo): Response
    {
        $evenements = $evenementRepository->createQueryBuilder('e')
            ->orderBy('e.dateDebut', 'ASC')
            ->getQuery()
            ->getResult();

        $html = $this->renderView('evenement/export_all_pdf.html.twig', [
            'evenements' => $evenements,
            'participationCounts' => $participationRepo->getActiveCountsByEventIds(
                $this->extractEventIds($evenements)
            ),
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'tous-les-evenements-' . date('Ymd') . '.pdf';

        return new Response(
            $dompdf->output(),
            200,
            [
                'Content-Type'        => 'application/pdf',
                'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            ]
        );
    }

    // ========== US 5.2 - Utilisateur: Participer à un événement ==========

    #[Route('/{id}/participer', name: 'evenements_participer', methods: ['POST'])]
    public function participer(
        Evenement $evenement,
        EntityManagerInterface $em,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté pour participer à un événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }

        $existingParticipation = $participationRepo->findOneBy([
            'evenement'   => $evenement,
            'utilisateur' => $utilisateur,
        ]);

        if ($existingParticipation && $existingParticipation->getStatut() === ParticipationStatut::INSCRIT) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }

        $participationsActives = $participationRepo->countActiveByEvenement($evenement);

        if ($participationsActives >= $evenement->getCapaciteMax()) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }

        if ($existingParticipation) {
            $existingParticipation->setStatut(ParticipationStatut::INSCRIT);
            $existingParticipation->setDateInscription(new \DateTime());
        } else {
            $participation = new ParticipationEvenement();
            $participation->setEvenement($evenement);
            $participation->setUtilisateur($utilisateur);
            $em->persist($participation);
        }

        if ($participationsActives + 1 >= $evenement->getCapaciteMax()) {
            $evenement->setStatut(EvenementStatut::FULL);
        }

        $em->flush();

        $this->addFlash('success', 'Votre inscription a été confirmée avec succès !');
        return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
    }

    // ========== US 5.3 - Utilisateur: Annuler sa participation ==========

    #[Route('/{id}/annuler', name: 'evenements_annuler', methods: ['POST'])]
    public function annuler(
        Evenement $evenement,
        EntityManagerInterface $em,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }

        $participation = $participationRepo->findOneBy([
            'evenement'   => $evenement,
            'utilisateur' => $utilisateur,
            'statut'      => ParticipationStatut::INSCRIT,
        ]);

        if (!$participation) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }

        $participation->setStatut(ParticipationStatut::ANNULE);

        if ($evenement->getStatut() === EvenementStatut::FULL) {
            $evenement->setStatut(EvenementStatut::OPEN);
        }

        $em->flush();

        $this->addFlash('success', 'Votre participation a été annulée.');
        return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
    }

    // ========== Mes participations ==========

    #[Route('/mes-participations', name: 'evenements_mes_participations', priority: 10)]
    public function mesParticipations(
        ParticipationEvenementRepository $participationRepo
    ): Response {
        /** @var Utilisateur $utilisateur */
        $utilisateur = $this->getUser();

        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('evenements_list');
        }

        $participations = $participationRepo->findByUtilisateurWithEvenements($utilisateur);

        return $this->render('evenement/mes_participations.html.twig', [
            'participations' => $participations,
            'utilisateur'    => $utilisateur,
        ]);
    }

    // ========== US 5.1 - Coach: Dashboard ==========

    #[Route('/coach/dashboard', name: 'evenements_coach_dashboard', priority: 10)]
    public function coachDashboard(
        EvenementRepository $evenementRepo,
        CacheInterface $cache
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        $stats = $cache->get(sprintf('coach_dashboard_stats_%d', $coach->getId()), function (ItemInterface $item) use ($evenementRepo, $coach) {
            $item->expiresAfter(300);

            return $evenementRepo->getCoachDashboardStats($coach);
        });
        $evenementsRecents = $evenementRepo->findRecentForCoach($coach, 5);

        return $this->render('evenement/coach/dashboard.html.twig', [
            'totalEvenements'     => $stats['totalEvenements'],
            'totalParticipations' => $stats['totalParticipations'],
            'evenementsAVenir'    => $stats['evenementsAVenir'],
            'evenementsTermines'  => $stats['evenementsTermines'],
            'evenementsRecents'   => $evenementsRecents,
            'coach'               => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Gérer ses événements ==========

    #[Route('/coach/mes-evenements', name: 'evenements_coach_list', priority: 10)]
    public function coachList(
        Request $request,
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo,
        PaginatorInterface $paginator
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        $evenements = $paginator->paginate(
            $evenementRepo->createCoachListQueryBuilder($coach),
            max(1, $request->query->getInt('page', 1)),
            10
        );

        return $this->render('evenement/coach/list.html.twig', [
            'evenements' => $evenements,
            'totalEvenements' => $evenements->getTotalItemCount(),
            'eventParticipationCounts' => $participationRepo->getActiveCountsByEventIds(
                $this->extractEventIds($this->normalizeEvents($evenements->getItems()))
            ),
            'coach'      => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Créer un événement ==========

    #[Route('/coach/creer', name: 'evenements_coach_create', priority: 10)]
    public function coachCreate(
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        if ($request->isMethod('POST')) {
            $evenement = new Evenement();
            $evenement->setTitre($request->request->get('titre'));
            $evenement->setDescription($request->request->get('description'));
            $evenement->setType(\App\Enum\EvenementType::from($request->request->get('type')));
            $evenement->setDateDebut(new \DateTime($request->request->get('dateDebut')));

            if ($request->request->get('dateFin')) {
                $evenement->setDateFin(new \DateTime($request->request->get('dateFin')));
            }

            $evenement->setLienSession($request->request->get('lienSession'));
            $evenement->setCapaciteMax((int) $request->request->get('capaciteMax'));
            $evenement->setStatut(EvenementStatut::from($request->request->get('statut')));
            $evenement->setCoach($coach);

            $em->persist($evenement);
            $em->flush();

            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('evenements_coach_list');
        }

        return $this->render('evenement/coach/create.html.twig', [
            'coach' => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Modifier un événement ==========

    #[Route('/coach/{id}/modifier', name: 'evenements_coach_edit', requirements: ['id' => '\d+'], priority: 10)]
    public function coachEdit(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        if ($evenement->getCoach() !== $coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_coach_list');
        }

        if ($request->isMethod('POST')) {
            $evenement->setTitre($request->request->get('titre'));
            $evenement->setDescription($request->request->get('description'));
            $evenement->setType(\App\Enum\EvenementType::from($request->request->get('type')));
            $evenement->setDateDebut(new \DateTime($request->request->get('dateDebut')));

            if ($request->request->get('dateFin')) {
                $evenement->setDateFin(new \DateTime($request->request->get('dateFin')));
            }

            $evenement->setLienSession($request->request->get('lienSession'));
            $evenement->setCapaciteMax((int) $request->request->get('capaciteMax'));
            $evenement->setStatut(EvenementStatut::from($request->request->get('statut')));

            $em->flush();

            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('evenements_coach_list');
        }

        return $this->render('evenement/coach/edit.html.twig', [
            'evenement' => $evenement,
            'coach'     => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Supprimer un événement ==========

    #[Route('/coach/{id}/supprimer', name: 'evenements_coach_delete', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function coachDelete(
        Evenement $evenement,
        EntityManagerInterface $em
    ): Response {
        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        if ($evenement->getCoach() !== $coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_coach_list');
        }

        $em->remove($evenement);
        $em->flush();

        $this->addFlash('success', 'Événement supprimé avec succès.');
        return $this->redirectToRoute('evenements_coach_list');
    }

    // ========== US 5.1 - Coach: Voir les participants ==========

    #[Route('/coach/{id}/participants', name: 'evenements_coach_participants', requirements: ['id' => '\d+'], priority: 10)]
    public function coachParticipants(
        int $id,
        EvenementRepository $evenementRepository,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        $evenement = $evenementRepository->findOneDetailed($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        /** @var Utilisateur $coach */
        $coach = $this->getUser();

        if ($evenement->getCoach()->getId() !== $coach->getId()) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_coach_list');
        }

        $participations = $participationRepo->findByEvenementWithUtilisateurs($evenement);

        return $this->render('evenement/coach/participants.html.twig', [
            'evenement'      => $evenement,
            'participations' => $participations,
            'coach'          => $coach,
        ]);
    }

    // ========== US 5.4 - Admin: Superviser les événements ==========

    #[Route('/admin/supervision', name: 'evenements_admin_supervision', priority: 10)]
    public function adminSupervision(
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo,
        Request $request,
        PaginatorInterface $paginator,
        CacheInterface $cache
    ): Response {
        $evenements = $paginator->paginate(
            $evenementRepo->createAdminSupervisionQueryBuilder(),
            max(1, $request->query->getInt('page', 1)),
            15
        );
        $stats = $cache->get('admin_evenements_supervision_stats', function (ItemInterface $item) use ($evenementRepo, $participationRepo) {
            $item->expiresAfter(300);

            return [
                'total'               => $evenementRepo->count([]),
                'ouverts'             => $evenementRepo->count(['statut' => EvenementStatut::OPEN]),
                'complets'            => $evenementRepo->count(['statut' => EvenementStatut::FULL]),
                'termines'            => $evenementRepo->count(['statut' => EvenementStatut::FINISHED]),
                'totalParticipations' => $participationRepo->count(['statut' => ParticipationStatut::INSCRIT]),
            ];
        });

        return $this->render('evenement/admin/supervision.html.twig', [
            'evenements' => $evenements,
            'stats'      => $stats,
            'totalEvenements' => $evenements->getTotalItemCount(),
        ]);
    }

    // ========== Calendrier ==========

    #[Route('/calendrier', name: 'evenements_calendrier', priority: 10)]
    public function calendrier(): Response
    {
        return $this->render('evenement/calendrier.html.twig');
    }

    #[Route('/calendar-data', name: 'evenement_calendar_data', priority: 10)]
    public function calendarData(EvenementRepository $evenementRepo, CacheInterface $cache): JsonResponse
    {
        $events = $cache->get('calendar_event_data', function (ItemInterface $item) use ($evenementRepo) {
            $item->expiresAfter(300);

            return array_map(function (array $event) {
                $color = match ($event['type']->value) {
                    'EVENT'   => '#4e73df',
                    'SESSION' => '#1cc88a',
                    default   => '#36b9cc',
                };

                return [
                    'id'    => $event['id'],
                    'title' => $event['titre'],
                    'start' => $event['dateDebut']->format('Y-m-d\TH:i:s'),
                    'end'   => $event['dateFin']?->format('Y-m-d\TH:i:s'),
                    'color' => $color,
                    'url'   => '/evenements/' . $event['id'],
                ];
            }, $evenementRepo->findCalendarEventsData());
        });

        return new JsonResponse($events);
    }

    // ========== Statistiques ==========

    #[Route('/statistiques', name: 'evenements_stats', priority: 10)]
    public function stats(EvenementRepository $evenementRepo, CacheInterface $cache): Response
    {
        $payload = $cache->get('evenement_stats_payload', function (ItemInterface $item) use ($evenementRepo) {
            $item->expiresAfter(600);

            $mostPopularRaw = $evenementRepo->findMostPopularData();
            $categoryRaw = $evenementRepo->findCategoryDistributionData();

            return [
                'mostPopularData' => array_map(fn ($row) => [
                    'id' => (int) $row['id'],
                    'title' => $row['titre'],
                    'participantCount' => (int) $row['participantCount'],
                ], $mostPopularRaw),
                'categoryData' => array_map(fn ($row) => [
                    'type' => $row['type'],
                    'count' => (int) $row['count'],
                ], $categoryRaw),
                'monthlyData' => $evenementRepo->findMonthlyTrends(),
            ];
        });

        return $this->render('evenement/stats.html.twig', [
            'mostPopularData' => $payload['mostPopularData'],
            'categoryData'    => $payload['categoryData'],
            'monthlyData'     => $payload['monthlyData'],
        ]);
    }

    // ========== Scan QR Code Summary ==========

    #[Route('/{id}/scan', name: 'evenements_scan_summary', requirements: ['id' => '\d+'], priority: 10)]
    public function scanSummary(
        int $id,
        EvenementRepository $evenementRepository,
        ParticipationEvenementRepository $participationRepo
    ): Response
    {
        $evenement = $evenementRepository->find($id);
        if ($evenement === null) {
            throw $this->createNotFoundException('Événement introuvable.');
        }

        /** @var Utilisateur|null $user */
        $user = $this->getUser();

        $isParticipant = false;
        if ($user instanceof Utilisateur) {
            $participation = $participationRepo->findOneBy([
                'evenement'   => $evenement,
                'utilisateur' => $user,
                'statut'      => ParticipationStatut::INSCRIT,
            ]);
            $isParticipant = $participation !== null;
        }

        $participationsActives = $participationRepo->countActiveByEvenement($evenement);

        // Generate PDF and encode it as base64
        $html = $this->renderView('evenement/export_pdf.html.twig', [
            'evenement' => $evenement,
            'participantCount' => $participationsActives,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'Arial');
        $options->set('isRemoteEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $pdfBase64 = base64_encode($dompdf->output());
        $pdfDataUri = 'data:application/pdf;base64,' . $pdfBase64;
        $pdfFilename = 'evenement-' . $evenement->getId() . '.pdf';

        // Generate QR code pointing to the scan page itself
        // (so scanning brings user to the page with the download button)
        $scanUrl = $this->generateUrl('evenements_scan_summary', ['id' => $evenement->getId()], UrlGeneratorInterface::ABSOLUTE_URL);

        $builder = new Builder(
            writer: new PngWriter(),
            writerOptions: [],
            validateResult: false,
            data: $scanUrl,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 250,
            margin: 10,
            roundBlockSizeMode: RoundBlockSizeMode::Margin,
        );

        $qrCodeDataUri = $builder->build()->getDataUri();

        return $this->render('evenement/scan_summary.html.twig', [
            'evenement'             => $evenement,
            'participationsActives' => $participationsActives,
            'isParticipant'         => $isParticipant,
            'qrCodeDataUri'         => $qrCodeDataUri,
            'pdfDataUri'            => $pdfDataUri,
            'pdfFilename'           => $pdfFilename,
        ]);
    }

    /**
     * @param iterable<Evenement> $events
     * @return Evenement[]
     */
    private function normalizeEvents(iterable $events): array
    {
        return is_array($events) ? $events : iterator_to_array($events, false);
    }

    /**
     * @param iterable<Evenement> $events
     * @return int[]
     */
    private function extractEventIds(iterable $events): array
    {
        $ids = [];
        foreach ($events as $event) {
            $id = $event->getId();
            if ($id !== null) {
                $ids[] = $id;
            }
        }

        return array_values(array_unique($ids));
    }
}
