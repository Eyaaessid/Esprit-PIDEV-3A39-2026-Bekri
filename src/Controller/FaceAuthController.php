<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use App\Service\FaceAuthService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/face-auth', name: 'api_face_auth_')]
class FaceAuthController extends AbstractController
{
    public function __construct(
        private FaceAuthService $faceAuthService,
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger,
        private UserProviderInterface $userProvider,
        private TokenStorageInterface $tokenStorage,
        private UrlGeneratorInterface $urlGenerator
    ) {}

    /**
     * Register face descriptor for authenticated user.
     * Called from profile page after capturing face.
     */
    #[Route('/register', name: 'register', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function registerFace(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['descriptor']) || !is_array($data['descriptor'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid face descriptor data'
                ], Response::HTTP_BAD_REQUEST);
            }

            /** @var Utilisateur $user */
            $user = $this->getUser();
            
            $success = $this->faceAuthService->storeFaceDescriptor($user, $data['descriptor']);
            
            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Face authentication enabled successfully',
                    'registeredAt' => $user->getFaceRegisteredAt()->format('Y-m-d H:i:s')
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to register face. Please try again.'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            
        } catch (\Exception $e) {
            /** @var \App\Entity\Utilisateur|null $user */
            $user = $this->getUser();
            $this->logger->error('Face registration error', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'An error occurred. Please try again.'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Disable face authentication for authenticated user.
     */
    #[Route('/disable', name: 'disable', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function disableFace(): JsonResponse
    {
        try {
            /** @var Utilisateur $user */
            $user = $this->getUser();
            
            $success = $this->faceAuthService->disableFaceAuth($user);
            
            if ($success) {
                return $this->json([
                    'success' => true,
                    'message' => 'Face authentication disabled successfully'
                ]);
            } else {
                return $this->json([
                    'success' => false,
                    'message' => 'Failed to disable face authentication'
                ], Response::HTTP_INTERNAL_SERVER_ERROR);
            }
            
        } catch (\Exception $e) {
            /** @var \App\Entity\Utilisateur|null $user */
            $user = $this->getUser();
            $this->logger->error('Face disable error', [
                'error' => $e->getMessage(),
                'user_id' => $user?->getId()
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'An error occurred'
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Authenticate user with face descriptor (public endpoint).
     */
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function loginWithFace(Request $request, UtilisateurRepository $userRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['email']) || !isset($data['descriptor'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Email and face descriptor required'
                ], Response::HTTP_BAD_REQUEST);
            }

            if (!is_array($data['descriptor'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'Invalid descriptor format'
                ], Response::HTTP_BAD_REQUEST);
            }

            // Find user by email
            $user = $userRepository->findOneBy(['email' => $data['email']]);
            
            if (!$user) {
                // Don't reveal if email exists
                return $this->json([
                    'success' => false,
                    'message' => 'Authentication failed'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Check if face auth is enabled for this user
            if (!$user->isFaceAuthEnabled()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Face authentication not enabled for this account'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Check account status
            if ($user->getStatut()->value !== 'actif') {
                return $this->json([
                    'success' => false,
                    'message' => 'Account is not active'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Check email verification
            if (!$user->isVerified()) {
                return $this->json([
                    'success' => false,
                    'message' => 'Please verify your email before logging in'
                ], Response::HTTP_UNAUTHORIZED);
            }

            // Validate face descriptor
            $isValid = $this->faceAuthService->validateFaceDescriptor($user, $data['descriptor']);
            
            if ($isValid) {
                // Face authentication successful
                // Reload user to ensure we have the latest data
                /** @var \App\Entity\Utilisateur $user */
                $user = $this->userProvider->loadUserByIdentifier($user->getUserIdentifier());
                
                // Update last login
                $user->setLastLoginAt(new \DateTime());
                $this->entityManager->flush();
                
                // Create authentication token
                $token = new UsernamePasswordToken(
                    $user,
                    'main', // firewall name
                    $user->getRoles()
                );
                
                // Store token in security token storage
                // TokenStorage will automatically persist to session via TokenStorageListener
                $this->tokenStorage->setToken($token);
                
                // Ensure session is saved (TokenStorageListener handles this, but we ensure it's saved)
                $session = $request->getSession();
                $session->remove('login_attempts');
                $session->save();
                
                // Get redirect URL based on user role using LoginSuccessHandler logic
                $redirectUrl = $this->getRedirectUrlForUser($user);
                
                return $this->json([
                    'success' => true,
                    'message' => 'Face authentication successful',
                    'redirectUrl' => $redirectUrl,
                    'user' => [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'name' => $user->getPrenom() . ' ' . $user->getNom(),
                        'role' => $user->getRole()->value
                    ]
                ]);
            } else {
                // Check if user is locked out
                $remainingTime = $this->faceAuthService->getRemainingLockoutTime($user);
                
                if ($remainingTime > 0) {
                    return $this->json([
                        'success' => false,
                        'message' => "Too many failed attempts. Try again in {$remainingTime} minutes.",
                        'lockoutTime' => $remainingTime
                    ], Response::HTTP_TOO_MANY_REQUESTS);
                }
                
                return $this->json([
                    'success' => false,
                    'message' => 'Face authentication failed',
                    'remainingAttempts' => max(0, 5 - $user->getFaceAuthFailedAttempts())
                ], Response::HTTP_UNAUTHORIZED);
            }
            
        } catch (\Throwable $e) {
            $this->logger->error('Face login error', [
                'error' => $e->getMessage(),
                'email' => $data['email'] ?? 'unknown'
            ]);
            
            return $this->json([
                'success' => false,
                'message' => 'An internal error occurred: ' . $e->getMessage()
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get face auth status for current user.
     */
    #[Route('/status', name: 'status', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function getStatus(): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        
            return $this->json([
            'enabled' => $user->isFaceAuthEnabled(),
            'registeredAt' => $user->getFaceRegisteredAt()?->format('Y-m-d H:i:s'),
            'failedAttempts' => $user->getFaceAuthFailedAttempts(),
            'isLockedOut' => $this->faceAuthService->getRemainingLockoutTime($user) > 0,
            'lockoutTime' => $this->faceAuthService->getRemainingLockoutTime($user)
        ]);
    }

    /**
     * Get redirect URL based on user role.
     */
    private function getRedirectUrlForUser(Utilisateur $user): string
    {
        $roles = $user->getRoles();
        
        if (in_array('ROLE_ADMIN', $roles)) {
            return $this->urlGenerator->generate('admin_dashboard');
        }
        
        if (in_array('ROLE_COACH', $roles)) {
            return $this->urlGenerator->generate('coach_dashboard');
        }
        
        return $this->urlGenerator->generate('user_dashboard');
    }
}