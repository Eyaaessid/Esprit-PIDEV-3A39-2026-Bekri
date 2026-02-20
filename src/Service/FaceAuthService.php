<?php

namespace App\Service;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Service for handling facial recognition authentication.
 * Manages face descriptor storage, validation, and security.
 */
class FaceAuthService
{
    // Threshold for face matching (0.0 = identical, 1.0 = completely different)
    // Lower = stricter matching
    private const MATCH_THRESHOLD = 0.6;
    
    // Maximum failed attempts before temporary lockout
    private const MAX_FAILED_ATTEMPTS = 5;
    
    // Lockout duration in minutes
    private const LOCKOUT_DURATION_MINUTES = 15;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    /**
     * Store face descriptor for a user.
     * 
     * @param Utilisateur $user The user to store face data for
     * @param array $descriptor Face descriptor array from face-api.js
     * @return bool Success status
     */
    public function storeFaceDescriptor(Utilisateur $user, array $descriptor): bool
    {
        try {
            // Validate descriptor format
            if (!$this->isValidDescriptor($descriptor)) {
                $this->logger->error('Invalid face descriptor format', [
                    'user_id' => $user->getId(),
                    'descriptor_length' => count($descriptor)
                ]);
                return false;
            }

            // Encrypt and store descriptor as JSON
            $encryptedDescriptor = $this->encryptDescriptor($descriptor);
            $user->setFaceDescriptor($encryptedDescriptor);
            $user->setFaceAuthEnabled(true);
            $user->setFaceRegisteredAt(new \DateTime());
            $user->setUpdatedAt(new \DateTime());

            $this->entityManager->flush();

            $this->logger->info('Face descriptor stored successfully', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to store face descriptor', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Validate face descriptor for authentication.
     * 
     * @param Utilisateur $user The user attempting to authenticate
     * @param array $providedDescriptor Face descriptor from login attempt
     * @return bool True if face matches stored descriptor
     */
    public function validateFaceDescriptor(Utilisateur $user, array $providedDescriptor): bool
    {
        try {
            // Check if user has face auth enabled
            if (!$user->isFaceAuthEnabled() || !$user->getFaceDescriptor()) {
                $this->logger->warning('Face auth not enabled for user', [
                    'user_id' => $user->getId()
                ]);
                return false;
            }

            // Check for rate limiting
            if ($this->isUserLockedOut($user)) {
                $this->logger->warning('User locked out from face auth', [
                    'user_id' => $user->getId(),
                    'failed_attempts' => $user->getFaceAuthFailedAttempts()
                ]);
                return false;
            }

            // Decrypt stored descriptor
            $storedDescriptor = $this->decryptDescriptor($user->getFaceDescriptor());
            
            if (!$storedDescriptor) {
                $this->logger->error('Failed to decrypt stored face descriptor', [
                    'user_id' => $user->getId()
                ]);
                return false;
            }

            // Calculate Euclidean distance between descriptors
            $distance = $this->calculateEuclideanDistance($storedDescriptor, $providedDescriptor);

            $this->logger->info('Face descriptor comparison', [
                'user_id' => $user->getId(),
                'distance' => $distance,
                'threshold' => self::MATCH_THRESHOLD
            ]);

            // Check if match is within threshold
            if ($distance < self::MATCH_THRESHOLD) {
                // Successful match - reset failed attempts
                $user->resetFaceAuthFailedAttempts();
                $this->entityManager->flush();
                
                $this->logger->info('Face authentication successful', [
                    'user_id' => $user->getId(),
                    'distance' => $distance
                ]);
                
                return true;
            } else {
                // Failed match - increment failed attempts
                $user->incrementFaceAuthFailedAttempts();
                $this->entityManager->flush();
                
                $this->logger->warning('Face authentication failed', [
                    'user_id' => $user->getId(),
                    'distance' => $distance,
                    'failed_attempts' => $user->getFaceAuthFailedAttempts()
                ]);
                
                return false;
            }
        } catch (\Exception $e) {
            $this->logger->error('Error validating face descriptor', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Disable face authentication for a user.
     */
    public function disableFaceAuth(Utilisateur $user): bool
    {
        try {
            $user->resetFaceAuth();
            $user->setUpdatedAt(new \DateTime());
            $this->entityManager->flush();

            $this->logger->info('Face authentication disabled', [
                'user_id' => $user->getId()
            ]);

            return true;
        } catch (\Exception $e) {
            $this->logger->error('Failed to disable face auth', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Check if user is temporarily locked out due to failed attempts.
     */
    private function isUserLockedOut(Utilisateur $user): bool
    {
        if ($user->getFaceAuthFailedAttempts() < self::MAX_FAILED_ATTEMPTS) {
            return false;
        }

        $lastAttempt = $user->getLastFaceAuthAttemptAt();
        if (!$lastAttempt) {
            return false;
        }

        $now = new \DateTime();
        $lockoutEnd = (clone $lastAttempt)->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes');

        // If lockout period has passed, reset attempts
        if ($now > $lockoutEnd) {
            $user->resetFaceAuthFailedAttempts();
            $this->entityManager->flush();
            return false;
        }

        return true;
    }

    /**
     * Validate descriptor format (should be array of 128 floats).
     */
    private function isValidDescriptor(array $descriptor): bool
    {
        // face-api.js produces 128-dimensional descriptors
        if (count($descriptor) !== 128) {
            return false;
        }

        // All values should be numeric
        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Encrypt face descriptor for secure storage.
     */
    private function encryptDescriptor(array $descriptor): string
    {
        // For now, just JSON encode
        // In production, you should use actual encryption (OpenSSL, Sodium, etc.)
        return json_encode($descriptor);
    }

    /**
     * Decrypt stored face descriptor.
     */
    private function decryptDescriptor(string $encryptedDescriptor): ?array
    {
        $descriptor = json_decode($encryptedDescriptor, true);
        
        if (!is_array($descriptor) || !$this->isValidDescriptor($descriptor)) {
            return null;
        }

        return $descriptor;
    }

    /**
     * Calculate Euclidean distance between two face descriptors.
     * Lower distance = more similar faces.
     */
    private function calculateEuclideanDistance(array $descriptor1, array $descriptor2): float
    {
        if (count($descriptor1) !== count($descriptor2)) {
            throw new \InvalidArgumentException('Descriptors must have same length');
        }

        $sum = 0;
        for ($i = 0; $i < count($descriptor1); $i++) {
            $diff = $descriptor1[$i] - $descriptor2[$i];
            $sum += $diff * $diff;
        }

        return sqrt($sum);
    }

    /**
     * Get remaining lockout time in minutes.
     */
    public function getRemainingLockoutTime(Utilisateur $user): int
    {
        if (!$this->isUserLockedOut($user)) {
            return 0;
        }

        $lastAttempt = $user->getLastFaceAuthAttemptAt();
        $now = new \DateTime();
        $lockoutEnd = (clone $lastAttempt)->modify('+' . self::LOCKOUT_DURATION_MINUTES . ' minutes');
        
        $diff = $lockoutEnd->getTimestamp() - $now->getTimestamp();
        return max(0, (int)ceil($diff / 60));
    }
}