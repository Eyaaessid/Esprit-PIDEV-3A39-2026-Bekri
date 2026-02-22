<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Enum\UtilisateurStatut;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private LoginSuccessHandler $successHandler,
        private UserProviderInterface $userProvider,
        private LoggerInterface $logger
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        // CAPTCHA: honeypot (invisible), timestamp (min 3s), math (when login_attempts >= 2)
        $honeypot = trim((string) $request->request->get('website', ''));
        if ($honeypot !== '') {
            throw new CustomUserMessageAuthenticationException('Requête invalide. Veuillez réessayer.');
        }
        $formTs = (int) $request->request->get('form_timestamp', 0);
        if (time() - $formTs < 3) {
            throw new CustomUserMessageAuthenticationException('Veuillez patienter quelques secondes avant de vous connecter.');
        }
        $loginAttempts = $request->getSession()->get('login_attempts', 0);
        if ($loginAttempts >= 2) {
            $userAnswer = trim((string) $request->request->get('captcha_answer', ''));
            $correctAnswer = $request->getSession()->get('captcha_answer');
            if ($correctAnswer === null || $userAnswer !== (string) $correctAnswer) {
                throw new CustomUserMessageAuthenticationException('Vérification de sécurité incorrecte. Veuillez réessayer.');
            }
        }

        $email = $request->getPayload()->getString('_username');

        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $email);

        $userLoader = function (string $userIdentifier) {
            $user = $this->userProvider->loadUserByIdentifier($userIdentifier);
            if (!$user instanceof Utilisateur) {
                return $user;
            }

            $statut = $user->getStatut();

            // Hard-block statuses: cannot reach password verification at all.
            // INACTIF and unverified checks are intentionally delegated to
            // UserChecker::checkPreAuth(), which runs after the user is loaded
            // and provides HTML-enriched error messages with clickable links.
            if ($statut === UtilisateurStatut::BLOQUE) {
                throw new CustomUserMessageAuthenticationException(
                    'Votre compte a été bloqué par un administrateur. Contactez le support à <a href="mailto:support@bekri.com" class="alert-link">support@bekri.com</a> pour plus d\'informations.'
                );
            }

            if ($statut === UtilisateurStatut::SUPPRIME) {
                throw new CustomUserMessageAuthenticationException(
                    'Ce compte n\'est plus disponible.'
                );
            }

            return $user;
        };

        return new Passport(
            new UserBadge($email, $userLoader),
            new PasswordCredentials($request->getPayload()->getString('_password')),
            [
                new CsrfTokenBadge('authenticate', $request->getPayload()->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $request->getSession()->remove('login_attempts');
        return $this->successHandler->onAuthenticationSuccess($request, $token);
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): Response
    {
        $session = $request->getSession();
        $attempts = $session->get('login_attempts', 0) + 1;
        $session->set('login_attempts', $attempts);

        // For AJAX/fetch requests: return JSON with the error message instead of the
        // standard 302→/login redirect.  This solves the double-GET session problem:
        //   1. fetch(redirect:'follow') follows the 302, rendering /login via GET
        //   2. SecurityController::login() calls getLastAuthenticationError() which
        //      CONSUMES the one-time _security session error
        //   3. window.location.href causes a second GET to /login, but error is gone
        // By returning JSON directly to the AJAX caller, no session read happens and
        // the error is displayed without any session involvement.
        if ($request->isXmlHttpRequest() || $request->headers->get('X-Requested-With') === 'fetch') {
            $message = $exception->getMessageKey() ?? $exception->getMessage();
            return new JsonResponse([
                'error'    => $message,
                'redirect' => $this->urlGenerator->generate(self::LOGIN_ROUTE),
            ], Response::HTTP_UNAUTHORIZED);
        }

        // Standard (non-AJAX) flow: store in session and redirect
        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}