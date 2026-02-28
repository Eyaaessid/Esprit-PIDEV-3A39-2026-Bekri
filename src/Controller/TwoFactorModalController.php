<?php

declare(strict_types=1);

namespace App\Controller;

use Scheb\TwoFactorBundle\Security\Authentication\Exception\InvalidTwoFactorCodeException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\SecurityRequestAttributes;

/**
 * Serves the 2FA form as a lightweight HTML fragment for the AJAX modal interceptor.
 *
 * The Scheb 2FA bundle handles the actual /2fa route and /2fa_check POST internally.
 * This controller provides an extra endpoint that returns only the form HTML
 * (no <html> wrapper) so the JavaScript modal can inject it.
 *
 * Access is protected by: IS_AUTHENTICATED_2FA_IN_PROGRESS (same as /2fa).
 */
class TwoFactorModalController extends AbstractController
{
    #[Route('/2fa/modal-fragment', name: '2fa_modal_fragment', methods: ['GET'])]
    public function fragment(Request $request): Response
    {
        // Only callable from our AJAX interceptor
        if (!$request->headers->has('X-Fetch-Intercept') && !$request->isXmlHttpRequest()) {
            return $this->redirectToRoute('2fa_login');
        }

        /** @var AuthenticationException|null $authError */
        $authError = $request->getSession()->get(SecurityRequestAttributes::AUTHENTICATION_ERROR);

        // TOTP check path & parameter name (matches security.yaml)
        $checkPath               = $request->getUriForPath('/2fa_check');
        $authCodeParameterName   = '_auth_code';
        $csrfProtectionEnabled   = false; // Set to true and configure if you use CSRF on 2FA
        $csrfParameterName       = '_csrf_token';
        $csrfTokenId             = 'two_factor';
        $logoutPath              = $this->generateUrl('app_logout');
        $targetPath              = $request->query->get('_target_path', '/');

        return $this->render('security/2fa_modal_fragment.html.twig', [
            'authenticationError'      => $authError ? $authError->getMessageKey() : null,
            'authenticationErrorData'  => $authError ? $authError->getMessageData() : [],
            'checkPathUrl'             => $checkPath,
            'checkPathRoute'           => null,
            'authCodeParameterName'    => $authCodeParameterName,
            'isCsrfProtectionEnabled'  => $csrfProtectionEnabled,
            'csrfParameterName'        => $csrfParameterName,
            'csrfTokenId'              => $csrfTokenId,
            'logoutPath'               => $logoutPath,
            'targetPath'               => $targetPath,
        ]);
    }
}
