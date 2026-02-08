<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\ParticipationEvenement;
use App\Enum\EvenementStatut;
use App\Enum\ParticipationStatut;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationEvenementRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/evenements')]
class EvenementController extends AbstractController
{
    // ========== Page d'accueil du module ==========
    
    #[Route('/home', name: 'evenements_home', priority: 10)]
    public function home(): Response
    {
        return $this->render('evenements/home.html.twig');
    }

    // ========== Page d'accès rapide ==========
    
    #[Route('/acces-rapide', name: 'evenements_acces_rapide', priority: 10)]
    public function accesRapide(): Response
    {
        return $this->render('evenements/index_acces.html.twig');
    }

    // ========== US 5.2 - Utilisateur: Consulter les événements ==========
    
    #[Route('/', name: 'evenements_list')]
    public function list(EvenementRepository $evenementRepository): Response
    {
        $evenements = $evenementRepository->findBy(
            ['statut' => [EvenementStatut::OPEN, EvenementStatut::PLANNED, EvenementStatut::FULL]], 
            ['dateDebut' => 'ASC']
        );
        
        return $this->render('evenements/list.html.twig', [
            'evenements' => $evenements,
        ]);
    }

    #[Route('/{id}', name: 'evenements_show', requirements: ['id' => '\d+'])]
    public function show(
        Evenement $evenement,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        // Simuler un utilisateur connecté (ID 1 pour la démo)
        $currentUserId = 1;
        
        // Vérifier si l'utilisateur est déjà inscrit
        $participation = $participationRepo->findOneBy([
            'evenement' => $evenement,
            'utilisateur' => $currentUserId
        ]);
        
        $isInscrit = $participation && $participation->getStatut() === ParticipationStatut::INSCRIT;
        
        // Calculer le nombre de places restantes
        $participationsActives = $participationRepo->count([
            'evenement' => $evenement,
            'statut' => ParticipationStatut::INSCRIT
        ]);
        
        $placesRestantes = $evenement->getCapaciteMax() - $participationsActives;
        
        return $this->render('evenements/show.html.twig', [
            'evenement' => $evenement,
            'isInscrit' => $isInscrit,
            'participation' => $participation,
            'placesRestantes' => $placesRestantes,
            'participationsActives' => $participationsActives,
        ]);
    }

    // ========== US 5.2 - Utilisateur: Participer à un événement ==========
    
    #[Route('/{id}/participer', name: 'evenements_participer', methods: ['POST'])]
    public function participer(
        Evenement $evenement,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepo,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        // Simuler un utilisateur connecté (ID 1 pour la démo)
        $utilisateur = $utilisateurRepo->find(1);
        
        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté pour participer à un événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }
        
        // Vérifier si déjà inscrit
        $existingParticipation = $participationRepo->findOneBy([
            'evenement' => $evenement,
            'utilisateur' => $utilisateur
        ]);
        
        if ($existingParticipation && $existingParticipation->getStatut() === ParticipationStatut::INSCRIT) {
            $this->addFlash('warning', 'Vous êtes déjà inscrit à cet événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }
        
        // Vérifier la capacité
        $participationsActives = $participationRepo->count([
            'evenement' => $evenement,
            'statut' => ParticipationStatut::INSCRIT
        ]);
        
        if ($participationsActives >= $evenement->getCapaciteMax()) {
            $this->addFlash('error', 'Désolé, cet événement est complet.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }
        
        // Créer ou réactiver la participation
        if ($existingParticipation) {
            $existingParticipation->setStatut(ParticipationStatut::INSCRIT);
            $existingParticipation->setDateInscription(new \DateTime());
        } else {
            $participation = new ParticipationEvenement();
            $participation->setEvenement($evenement);
            $participation->setUtilisateur($utilisateur);
            $em->persist($participation);
        }
        
        // Mettre à jour le statut de l'événement si complet
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
        UtilisateurRepository $utilisateurRepo,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        // Simuler un utilisateur connecté (ID 1 pour la démo)
        $utilisateur = $utilisateurRepo->find(1);
        
        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }
        
        $participation = $participationRepo->findOneBy([
            'evenement' => $evenement,
            'utilisateur' => $utilisateur,
            'statut' => ParticipationStatut::INSCRIT
        ]);
        
        if (!$participation) {
            $this->addFlash('error', 'Vous n\'êtes pas inscrit à cet événement.');
            return $this->redirectToRoute('evenements_show', ['id' => $evenement->getId()]);
        }
        
        $participation->setStatut(ParticipationStatut::ANNULE);
        
        // Rouvrir l'événement si il était complet
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
        ParticipationEvenementRepository $participationRepo,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        // Simuler un utilisateur connecté (ID 1 pour la démo)
        $utilisateur = $utilisateurRepo->find(1);
        
        if (!$utilisateur) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('evenements_list');
        }
        
        $participations = $participationRepo->findBy(
            ['utilisateur' => $utilisateur],
            ['dateInscription' => 'DESC']
        );
        
        return $this->render('evenements/mes_participations.html.twig', [
            'participations' => $participations,
            'utilisateur' => $utilisateur,
        ]);
    }

    // ========== US 5.1 - Coach: Dashboard ==========
    
    #[Route('/coach/dashboard', name: 'evenements_coach_dashboard', priority: 10)]
    public function coachDashboard(
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        // Simuler un coach connecté (ID 1 pour la démo)
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
        }
        
        // Statistiques
        $mesEvenements = $evenementRepo->findBy(['coach' => $coach]);
        $totalEvenements = count($mesEvenements);
        
        $totalParticipations = 0;
        $evenementsAVenir = 0;
        $evenementsTermines = 0;
        
        foreach ($mesEvenements as $evenement) {
            $participations = $participationRepo->count([
                'evenement' => $evenement,
                'statut' => ParticipationStatut::INSCRIT
            ]);
            $totalParticipations += $participations;
            
            if ($evenement->getDateDebut() > new \DateTime()) {
                $evenementsAVenir++;
            } elseif ($evenement->getStatut() === EvenementStatut::FINISHED) {
                $evenementsTermines++;
            }
        }
        
        // Événements récents
        $evenementsRecents = $evenementRepo->findBy(
            ['coach' => $coach],
            ['createdAt' => 'DESC'],
            5
        );
        
        return $this->render('evenements/coach/dashboard.html.twig', [
            'totalEvenements' => $totalEvenements,
            'totalParticipations' => $totalParticipations,
            'evenementsAVenir' => $evenementsAVenir,
            'evenementsTermines' => $evenementsTermines,
            'evenementsRecents' => $evenementsRecents,
            'coach' => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Gérer ses événements ==========
    
    #[Route('/coach/mes-evenements', name: 'evenements_coach_list', priority: 10)]
    public function coachList(
        EvenementRepository $evenementRepo,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
        }
        
        $evenements = $evenementRepo->findBy(
            ['coach' => $coach],
            ['dateDebut' => 'DESC']
        );
        
        return $this->render('evenements/coach/list.html.twig', [
            'evenements' => $evenements,
            'coach' => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Créer un événement ==========
    
    #[Route('/coach/creer', name: 'evenements_coach_create', priority: 10)]
    public function coachCreate(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
        }
        
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
            $evenement->setCapaciteMax((int)$request->request->get('capaciteMax'));
            $evenement->setStatut(EvenementStatut::from($request->request->get('statut')));
            $evenement->setCoach($coach);
            
            $em->persist($evenement);
            $em->flush();
            
            $this->addFlash('success', 'Événement créé avec succès !');
            return $this->redirectToRoute('evenements_coach_list');
        }
        
        return $this->render('evenements/coach/create.html.twig', [
            'coach' => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Modifier un événement ==========
    
    #[Route('/coach/{id}/modifier', name: 'evenements_coach_edit', requirements: ['id' => '\d+'], priority: 10)]
    public function coachEdit(
        Evenement $evenement,
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach || $evenement->getCoach() !== $coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
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
            $evenement->setCapaciteMax((int)$request->request->get('capaciteMax'));
            $evenement->setStatut(EvenementStatut::from($request->request->get('statut')));
            
            $em->flush();
            
            $this->addFlash('success', 'Événement modifié avec succès !');
            return $this->redirectToRoute('evenements_coach_list');
        }
        
        return $this->render('evenements/coach/edit.html.twig', [
            'evenement' => $evenement,
            'coach' => $coach,
        ]);
    }

    // ========== US 5.1 - Coach: Supprimer un événement ==========
    
    #[Route('/coach/{id}/supprimer', name: 'evenements_coach_delete', methods: ['POST'], requirements: ['id' => '\d+'], priority: 10)]
    public function coachDelete(
        Evenement $evenement,
        EntityManagerInterface $em,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach || $evenement->getCoach() !== $coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
        }
        
        $em->remove($evenement);
        $em->flush();
        
        $this->addFlash('success', 'Événement supprimé avec succès.');
        return $this->redirectToRoute('evenements_coach_list');
    }

    // ========== US 5.1 - Coach: Voir les participants ==========
    
    #[Route('/coach/{id}/participants', name: 'evenements_coach_participants', requirements: ['id' => '\d+'], priority: 10)]
    public function coachParticipants(
        Evenement $evenement,
        ParticipationEvenementRepository $participationRepo,
        UtilisateurRepository $utilisateurRepo
    ): Response {
        $coach = $utilisateurRepo->find(1);
        
        if (!$coach || $evenement->getCoach() !== $coach) {
            $this->addFlash('error', 'Accès refusé.');
            return $this->redirectToRoute('evenements_list');
        }
        
        $participations = $participationRepo->findBy(
            ['evenement' => $evenement],
            ['dateInscription' => 'DESC']
        );
        
        return $this->render('evenements/coach/participants.html.twig', [
            'evenement' => $evenement,
            'participations' => $participations,
            'coach' => $coach,
        ]);
    }

    // ========== US 5.4 - Admin: Superviser les événements ==========
    
    #[Route('/admin/supervision', name: 'evenements_admin_supervision', priority: 10)]
    public function adminSupervision(
        EvenementRepository $evenementRepo,
        ParticipationEvenementRepository $participationRepo
    ): Response {
        $evenements = $evenementRepo->findBy([], ['createdAt' => 'DESC']);
        
        $stats = [
            'total' => count($evenements),
            'ouverts' => $evenementRepo->count(['statut' => EvenementStatut::OPEN]),
            'complets' => $evenementRepo->count(['statut' => EvenementStatut::FULL]),
            'termines' => $evenementRepo->count(['statut' => EvenementStatut::FINISHED]),
            'totalParticipations' => $participationRepo->count(['statut' => ParticipationStatut::INSCRIT]),
        ];
        
        return $this->render('evenements/admin/supervision.html.twig', [
            'evenements' => $evenements,
            'stats' => $stats,
        ]);
    }
}
