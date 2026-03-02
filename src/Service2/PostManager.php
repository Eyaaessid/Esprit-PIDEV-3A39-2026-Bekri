<?php

namespace App\Service2;

use App\Entity\Post;

class PostManager
{
    private const RISK_LEVELS_VALIDES = ['low', 'medium', 'high'];

    public function validate(Post $post): bool
    {
        if (empty($post->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (strlen($post->getTitre()) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }

        if (empty($post->getContenu())) {
            throw new \InvalidArgumentException('Le contenu est obligatoire');
        }

        if (strlen($post->getContenu()) < 10) {
            throw new \InvalidArgumentException('Le contenu doit contenir au moins 10 caractères');
        }

        if (!in_array($post->getRiskLevel(), self::RISK_LEVELS_VALIDES)) {
            throw new \InvalidArgumentException('Le niveau de risque est invalide');
        }

        return true;
    }
}