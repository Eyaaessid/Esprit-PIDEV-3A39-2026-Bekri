<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel\ErrorCorrectionLevelHigh;
use Endroid\QrCode\RoundBlockSizeMode\RoundBlockSizeModeMargin;
use Endroid\QrCode\Writer\PngWriter;
use Psr\Log\LoggerInterface;
use Scheb\TwoFactorBundle\Security\TwoFactor\Provider\Totp\TotpAuthenticatorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\HttpFoundation\JsonResponse;

class TwoFactorController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TotpAuthenticatorInterface $totpAuthenticator,
        private UserPasswordHasherInterface $passwordHasher,
        private LoggerInterface $logger
    ) {
    }

    /**
     * Get the profile route based on user role
     */
    private function getProfileRoute(): string
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        
        if ($user->getRole()->value === 'admin') {
            return 'admin_profile';
        } elseif ($user->getRole()->value === 'coach') {
            return 'coach_profile';
        }
        return 'user_profile';
    }

    /**
     * Show 2FA enable page with QR code
     */
    #[Route('/profile/2fa/enable', name: 'app_2fa_enable', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function enable(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled()) {
            $this->addFlash('info', 'Two-factor authentication is already enabled.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Generate secret if not already set
        if (!$user->getTotpSecret()) {
            $secret = $this->totpAuthenticator->generateSecret();
            $user->setTotpSecret($secret);
            $this->entityManager->flush();
        }

        // Generate QR code content
        $qrCodeContent = $this->totpAuthenticator->getQRContent($user);

        // Generate QR code image using endroid/qr-code v6
        $result = Builder::create()
            ->writer(new PngWriter())
            ->writerOptions([])
            ->data($qrCodeContent)
            ->encoding(new Encoding('UTF-8'))
            ->errorCorrectionLevel(new ErrorCorrectionLevelHigh())
            ->size(300)
            ->margin(10)
            ->roundBlockSizeMode(new RoundBlockSizeModeMargin())
            ->validateResult(false)
            ->build();

        $qrCodeDataUri = $result->getDataUri();

        return $this->render('two_factor/enable.html.twig', [
            'user' => $user,
            'qrCodeDataUri' => $qrCodeDataUri,
            'secret' => $user->getTotpSecret(),
        ]);
    }

    /**
     * Verify code and enable 2FA
     */
    #[Route('/profile/2fa/verify', name: 'app_2fa_verify', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function verify(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if ($user->isTwoFactorEnabled()) {
            $this->addFlash('error', 'Two-factor authentication is already enabled.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        $code = $request->request->get('code');
        $password = $request->request->get('password');

        if (!$code || !$password) {
            $this->addFlash('error', 'Please provide both the verification code and your password.');
            return $this->redirectToRoute('app_2fa_enable');
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Invalid password.');
            return $this->redirectToRoute('app_2fa_enable');
        }

        // Verify TOTP code
        if (!$this->totpAuthenticator->checkCode($user, $code)) {
            $this->addFlash('error', 'Invalid verification code. Please try again.');
            return $this->redirectToRoute('app_2fa_enable');
        }

        // Enable 2FA
        $user->setIsTwoFactorEnabled(true);
        $user->setTwoFactorEnabledAt(new \DateTime());

        // Generate backup codes
        $backupCodes = $this->generateBackupCodes();
        $hashedCodes = $this->hashBackupCodes($backupCodes);
        $user->setBackupCodes(json_encode($hashedCodes));

        $this->entityManager->flush();

        $this->logger->info('2FA enabled', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        // Store backup codes in session to show once
        $request->getSession()->set('2fa_backup_codes', $backupCodes);

        $this->addFlash('success', 'Two-factor authentication has been enabled successfully!');

        return $this->redirectToRoute('app_2fa_backup_codes');
    }

    /**
     * Show backup codes (only once after enabling)
     */
    #[Route('/profile/2fa/backup-codes', name: 'app_2fa_backup_codes', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function backupCodes(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $backupCodes = $request->getSession()->get('2fa_backup_codes');

        if (!$backupCodes) {
            $this->addFlash('info', 'Backup codes are only shown once. If you lost them, you can regenerate them from your profile.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Remove from session after showing
        $request->getSession()->remove('2fa_backup_codes');

        return $this->render('two_factor/backup_codes.html.twig', [
            'user' => $user,
            'backupCodes' => $backupCodes,
        ]);
    }

    /**
     * Disable 2FA
     */
    #[Route('/profile/2fa/disable', name: 'app_2fa_disable', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function disable(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            $this->addFlash('error', 'Two-factor authentication is not enabled.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        $password = $request->request->get('password');

        if (!$password) {
            $this->addFlash('error', 'Please provide your password to disable two-factor authentication.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Invalid password.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Disable 2FA
        $user->resetTwoFactorAuth();
        $this->entityManager->flush();

        $this->logger->info('2FA disabled', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        $this->addFlash('success', 'Two-factor authentication has been disabled.');

        return $this->redirectToRoute($this->getProfileRoute());
    }

    /**
     * Regenerate backup codes
     */
    #[Route('/profile/2fa/regenerate-backup-codes', name: 'app_2fa_regenerate_backup_codes', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function regenerateBackupCodes(Request $request): Response
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        if (!$user->isTwoFactorEnabled()) {
            $this->addFlash('error', 'Two-factor authentication is not enabled.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        $password = $request->request->get('password');

        if (!$password) {
            $this->addFlash('error', 'Please provide your password.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Verify password
        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            $this->addFlash('error', 'Invalid password.');
            return $this->redirectToRoute($this->getProfileRoute());
        }

        // Generate new backup codes
        $backupCodes = $this->generateBackupCodes();
        $hashedCodes = $this->hashBackupCodes($backupCodes);
        $user->setBackupCodes(json_encode($hashedCodes));
        $this->entityManager->flush();

        // Store in session to show once
        $request->getSession()->set('2fa_backup_codes', $backupCodes);

        $this->logger->info('2FA backup codes regenerated', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
        ]);

        return $this->redirectToRoute('app_2fa_backup_codes');
    }

    /**
     * Verify backup code (used during login)
     */
    #[Route('/2fa/verify-backup-code', name: 'app_2fa_verify_backup_code', methods: ['POST'])]
    public function verifyBackupCode(Request $request): JsonResponse
    {
        $code = $request->request->get('code');
        $email = $request->request->get('email');

        if (!$code || !$email) {
            return new JsonResponse(['success' => false, 'message' => 'Code and email are required.'], 400);
        }

        $user = $this->entityManager->getRepository(Utilisateur::class)->findOneBy(['email' => $email]);

        if (!$user || !$user->isTwoFactorEnabled()) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid request.'], 400);
        }

        $storedCodes = json_decode($user->getBackupCodes() ?? '[]', true);
        $codeHash = hash('sha256', $code);

        // Check if code matches
        $foundIndex = null;
        foreach ($storedCodes as $index => $storedHash) {
            if (hash_equals($storedHash, $codeHash)) {
                $foundIndex = $index;
                break;
            }
        }

        if ($foundIndex === null) {
            return new JsonResponse(['success' => false, 'message' => 'Invalid backup code.'], 400);
        }

        // Remove used backup code
        unset($storedCodes[$foundIndex]);
        $storedCodes = array_values($storedCodes); // Re-index array
        $user->setBackupCodes(json_encode($storedCodes));
        $this->entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Backup code verified successfully.']);
    }

    /**
     * Generate 10 backup codes
     */
    private function generateBackupCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < 10; $i++) {
            $codes[] = bin2hex(random_bytes(4)); // 8-character codes
        }
        return $codes;
    }

    /**
     * Hash backup codes for storage
     */
    private function hashBackupCodes(array $codes): array
    {
        return array_map(fn($code) => hash('sha256', $code), $codes);
    }
}