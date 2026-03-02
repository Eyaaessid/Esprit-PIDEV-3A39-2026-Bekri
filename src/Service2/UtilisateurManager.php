<?php

namespace App\Service2;

use App\Entity\Utilisateur;

class UtilisateurManager
{
    public function validate(Utilisateur $utilisateur): bool
    {
        if (empty($utilisateur->getNom())) {
            throw new \InvalidArgumentException('Le nom est obligatoire');
        }

        if (strlen($utilisateur->getNom()) < 2) {
            throw new \InvalidArgumentException('Le nom doit contenir au moins 2 caractères');
        }

        if (empty($utilisateur->getEmail())) {
            throw new \InvalidArgumentException('L\'email est obligatoire');
        }

        if (!filter_var($utilisateur->getEmail(), FILTER_VALIDATE_EMAIL)) {
            throw new \InvalidArgumentException('L\'email n\'est pas valide');
        }

        if ($utilisateur->getDateNaissance() !== null) {
            $age = (new \DateTime())->diff($utilisateur->getDateNaissance())->y;
            if ($age < 13) {
                throw new \InvalidArgumentException('L\'utilisateur doit avoir au moins 13 ans');
            }
        }

        return true;
    }
}