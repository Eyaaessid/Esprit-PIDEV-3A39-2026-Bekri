<?php

namespace App\Service2;

use App\Entity\SuiviQuotidien;

class SuiviQuotidienManager
{
    public function validate(SuiviQuotidien $suivi): bool
    {
        if ($suivi->getDate() === null) {
            throw new \InvalidArgumentException('La date est obligatoire');
        }

        if ($suivi->getDate() > new \DateTime()) {
            throw new \InvalidArgumentException('La date ne peut pas être dans le futur');
        }

        if ($suivi->getCommentaire() !== null && strlen($suivi->getCommentaire()) > 1000) {
            throw new \InvalidArgumentException('Le commentaire ne peut pas dépasser 1000 caractères');
        }

        return true;
    }
}