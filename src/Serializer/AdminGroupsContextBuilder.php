<?php

namespace App\Serializer;

use ApiPlatform\State\SerializerContextBuilderInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

class AdminGroupsContextBuilder implements SerializerContextBuilderInterface
{
    public function __construct(
        private readonly SerializerContextBuilderInterface $decorated,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    public function createFromRequest(\Symfony\Component\HttpFoundation\Request $request, bool $normalization, ?array $extractedAttributes = null): array
    {
        $context = $this->decorated->createFromRequest($request, $normalization, $extractedAttributes);

        if ($normalization && $this->authorizationChecker->isGranted('ROLE_ADMIN')) {
            $groups = $context['groups'] ?? [];
            if (!in_array('admin:read', $groups, true)) {
                $groups[] = 'admin:read';
                $context['groups'] = $groups;
            }
        }

        return $context;
    }
}
