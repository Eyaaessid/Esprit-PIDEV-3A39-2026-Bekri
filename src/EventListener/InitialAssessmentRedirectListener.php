<?php

declare(strict_types=1);

namespace App\EventListener;

use App\Entity\Utilisateur;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

/**
 * Redirects authenticated ROLE_USER without a psychological profile
 * from /user and /user/* to the initial assessment until they complete it.
 */
#[AsEventListener(event: KernelEvents::REQUEST, priority: 5)]
class InitialAssessmentRedirectListener
{
    public function __construct(
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {
    }

    public function __invoke(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();
        if (str_starts_with($path, '/initial-assessment')) {
            return;
        }
        if (!str_starts_with($path, '/user')) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        if ($token === null) {
            return;
        }

        $user = $token->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        $roles = $user->getRoles();
        if (in_array('ROLE_ADMIN', $roles, true) || in_array('ROLE_COACH', $roles, true)) {
            return;
        }
        if (!in_array('ROLE_USER', $roles, true)) {
            return;
        }

        if ($user->getProfilPsychologique() !== null) {
            return;
        }

        $event->setResponse(new RedirectResponse($this->urlGenerator->generate('app_initial_assessment')));
    }
}
