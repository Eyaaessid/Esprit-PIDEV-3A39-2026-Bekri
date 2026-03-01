<?php

namespace App\Controller;

use App\Entity\ReactivationRequest;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReactivationController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Public form: request reactivation (for admin-deactivated accounts).
     */
    #[Route('/request-reactivation', name: 'request_reactivation', methods: ['GET', 'POST'])]
    public function requestReactivation(Request $request): Response
    {
        $submitted = false;
        $success = false;
        $error = null;

        if ($request->isMethod('POST')) {
            $submitted = true;
            $email = trim((string) $request->request->get('email', ''));
            $reason = trim((string) $request->request->get('reason', ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Veuillez entrer une adresse email valide.';
            } elseif (strlen($reason) < 10) {
                $error = 'Veuillez indiquer la raison de votre demande (au moins 10 caractères).';
            } else {
                $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);
                if (!$user || $user->getStatut() !== \App\Enum\UtilisateurStatut::INACTIF || $user->getDeactivatedBy() !== 'admin') {
                    $error = 'Aucun compte inactif par administrateur trouvé pour cet email, ou votre compte peut être réactivé via le lien envoyé par email.';
                } else {
                    $existing = $this->entityManager->getRepository(ReactivationRequest::class)
                        ->findOneBy(['utilisateur' => $user, 'status' => ReactivationRequest::STATUS_PENDING]);
                    if ($existing) {
                        $error = 'Une demande de réactivation est déjà en cours pour ce compte.';
                    } else {
                        $rr = new ReactivationRequest();
                        $rr->setUtilisateur($user);
                        $rr->setReason($reason);
                        $this->entityManager->persist($rr);
                        $this->entityManager->flush();
                        $success = true;
                        $this->addFlash('success', 'Votre demande de réactivation a été enregistrée. L\'administrateur vous contactera sous peu.');
                        return $this->redirectToRoute('app_login');
                    }
                }
            }
        }

        return $this->render('security/request_reactivation.html.twig', [
            'submitted' => $submitted,
            'success' => $success,
            'error' => $error,
        ]);
    }

    #[Route('/reactivate-account/{token}', name: 'reactivate_account', methods: ['GET'])]
    public function reactivate(string $token): Response
    {
        $user = $this->entityManager->getRepository(Utilisateur::class)
            ->findOneBy(['reactivationToken' => $token]);

        if (!$user) {
            $this->addFlash('error', 'Ce lien de réactivation n\'est pas valide ou a déjà été utilisé.');
            return $this->redirectToRoute('app_login');
        }

        if (!$user->getReactivationTokenExpiresAt() || $user->getReactivationTokenExpiresAt() < new \DateTime()) {
            $this->logger->info('Expired reactivation token used', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
            ]);
            $user->setReactivationToken(null);
            $user->setReactivationTokenExpiresAt(null);
            $this->entityManager->flush();

            $this->addFlash('error', 'Ce lien de réactivation a expiré (validité : 24 heures). Connectez-vous pour recevoir un nouveau lien.');
            return $this->redirectToRoute('app_login');
        }

        $user->setStatut(\App\Enum\UtilisateurStatut::ACTIF);
        $user->setDeactivatedAt(null);
        $user->setDeactivatedBy(null);
        $user->setReactivationToken(null);
        $user->setReactivationTokenExpiresAt(null);
        $user->setLastLoginAt(new \DateTime());
        $user->setUpdatedAt(new \DateTimeImmutable());

        $this->entityManager->flush();

        $this->logger->info('Account reactivated via token', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $this->addFlash('success', 'Votre compte a été réactivé ! Vous pouvez maintenant vous connecter.');
        return $this->redirectToRoute('app_login');
    }
}
