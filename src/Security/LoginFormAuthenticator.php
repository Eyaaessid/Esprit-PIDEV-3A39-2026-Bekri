<?php

namespace App\Security;

use App\Entity\Utilisateur;
<<<<<<< Updated upstream
use App\Enum\UtilisateurStatut;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
=======
use App\Enum\UtilisateurStatut; 
use Doctrine\ORM\EntityManagerInterface;
>>>>>>> Stashed changes
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Psr\Log\LoggerInterface;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private LoginSuccessHandler $successHandler,
        private UserProviderInterface $userProvider,
        private EntityManagerInterface $entityManager,
        private MailerInterface $mailer
    ) {
    }

    // ── FIX: only intercept POST requests to the login route ──────────
    public function supports(Request $request): bool
    {
        return $request->attributes->get('_route') === self::LOGIN_ROUTE
            && $request->isMethod('POST');
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
            $userAnswer    = trim((string) $request->request->get('captcha_answer', ''));
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

            if ($statut === UtilisateurStatut::BLOQUE) {
                throw new CustomUserMessageAuthenticationException(
                    'Votre compte a été bloqué par un administrateur.'
                );
            }

            if ($statut === UtilisateurStatut::INACTIF) {
                $deactivatedBy = $user->getDeactivatedBy();

                if ($deactivatedBy === 'admin') {
                    throw new CustomUserMessageAuthenticationException(
                        'Votre compte est temporairement inactif. Contactez l\'administrateur ou demandez une réactivation.'
                    );
                }

                $token     = bin2hex(random_bytes(32));
                $expiresAt = new \DateTime('+24 hours');
                $user->setReactivationToken($token);
                $user->setReactivationTokenExpiresAt($expiresAt);
                $this->entityManager->flush();

                $reactivationUrl = $this->urlGenerator->generate(
                    'reactivate_account',
                    ['token' => $token],
                    UrlGeneratorInterface::ABSOLUTE_URL
                );

                $deactivationReason = match ($deactivatedBy) {
                    'user'   => 'Vous avez désactivé votre compte.',
                    'system' => 'Votre compte a été marqué inactif après 30 jours sans connexion.',
                    default  => 'Votre compte est inactif.',
                };

                $emailMessage = (new TemplatedEmail())
                    ->from(new Address('noreply@bekri.com', 'Bekri Wellbeing'))
                    ->to(new Address($user->getEmail(), $user->getPrenom() . ' ' . $user->getNom()))
                    ->subject('Réactivation de votre compte Bekri')
                    ->htmlTemplate('emails/reactivate_account.html.twig')
                    ->context([
                        'user'               => $user,
                        'reactivationUrl'    => $reactivationUrl,
                        'deactivationReason' => $deactivationReason,
                    ]);

                try {
                    $this->mailer->send($emailMessage);
                } catch (\Throwable $e) {
<<<<<<< Updated upstream
                    // Log but don't expose; user still sees "check your email" for consistency
=======
                    $this->logger->error('Failed to send reactivation email', [
                        'email'            => $user->getEmail(),
                        'error'            => $e->getMessage(),
                        'reactivationUrl'  => $reactivationUrl,
                    ]);
>>>>>>> Stashed changes
                }

                $message = $deactivatedBy === 'system'
                    ? 'Votre compte est inactif (30 jours sans connexion). Un email de réactivation vous a été envoyé.'
                    : 'Votre compte est désactivé. Un email de réactivation vous a été envoyé.';

                throw new CustomUserMessageAuthenticationException($message);
            }

            if ($statut === UtilisateurStatut::SUPPRIME) {
                throw new CustomUserMessageAuthenticationException(
                    'Ce compte n\'est plus disponible.'
                );
            }

<<<<<<< Updated upstream
=======
            if (!$user->isVerified()) {
                throw new CustomUserMessageAuthenticationException(
                    'Please verify your email before logging in'
                );
            }

>>>>>>> Stashed changes
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
        $session  = $request->getSession();
        $attempts = $session->get('login_attempts', 0) + 1;
        $session->set('login_attempts', $attempts);
        return parent::onAuthenticationFailure($request, $exception);
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}