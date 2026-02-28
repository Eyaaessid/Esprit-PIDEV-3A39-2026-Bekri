<?php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Detects when the 2FA bundle is redirecting an AJAX (fetch) request to /2fa,
 * and instead returns a JSON response so the JavaScript interceptor knows to
 * show the 2FA modal without navigating away from the current page.
 *
 * Also adds an X-2FA-Required header to the /2fa page response so that
 * the fetch() interceptor can detect it regardless of redirect following.
 */
#[AsEventListener(event: KernelEvents::RESPONSE, priority: 10)]
class TwoFactorAjaxListener
{
    public function __invoke(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request  = $event->getRequest();
        $response = $event->getResponse();

        // Only act on the /2fa page (the form page, not the check path)
        $pathInfo = $request->getPathInfo();
        if ($pathInfo !== '/2fa') {
            return;
        }

        // Mark every /2fa response with a custom header so our JS can detect it
        $response->headers->set('X-2FA-Required', 'true');

        // If this is a fetch/XHR request, return JSON instead of a full page
        // so the JavaScript modal handler can open the modal without a page change.
        $isAjax = $request->headers->get('X-Requested-With') === 'XMLHttpRequest'
               || $request->headers->get('X-Fetch-Intercept') === '1';

        if (!$isAjax) {
            return;
        }

        // Return a JSON envelope that the JS interceptor expects
        $event->setResponse(new JsonResponse([
            '2fa_required' => true,
            'form_url'     => '/2fa',
            'check_url'    => '/2fa_check',
        ]));
    }
}
