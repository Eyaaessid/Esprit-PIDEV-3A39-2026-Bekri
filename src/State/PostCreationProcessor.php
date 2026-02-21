<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Post;
use App\Entity\Utilisateur;
use App\Service\EmotionalAnalysisService;
use App\Service\PostRiskAlertNotifier;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class PostCreationProcessor implements ProcessorInterface
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly Security $security,
        private readonly EmotionalAnalysisService $emotionalAnalysisService,
        private readonly PostRiskAlertNotifier $postRiskAlertNotifier,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Post
    {
        if (!$data instanceof Post) {
            throw new \InvalidArgumentException('PostCreationProcessor expects a Post entity.');
        }

        $user = $this->security->getUser();
        if (!$user instanceof Utilisateur) {
            throw new AccessDeniedHttpException('Authentication required.');
        }

        $data->setUtilisateur($user);

        // AI analysis at creation time (emotion + risk scoring).
        $analysis = $this->emotionalAnalysisService->analyzePostContent($data->getContenu());
        $data->setEmotion($analysis->emotion);
        $data->setRiskLevel($analysis->riskLevel);
        $data->setIsSensitive($analysis->isSensitive);

        $this->entityManager->persist($data);
        $this->entityManager->flush();

        if ($analysis->riskLevel === 'high') {
            $this->postRiskAlertNotifier->notifyHighRiskPost($data, $analysis->matchedSignals);
        }

        return $data;
    }
}
