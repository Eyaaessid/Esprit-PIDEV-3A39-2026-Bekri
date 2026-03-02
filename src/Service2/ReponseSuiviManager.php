<?php

namespace App\Service2;

use App\Entity\ReponseSuivi;

class ReponseSuiviManager
{
    public function validate(ReponseSuivi $reponse): bool
    {
        if (empty($reponse->getValeur())) {
            throw new \InvalidArgumentException('La valeur de la réponse est obligatoire');
        }

        if (strlen($reponse->getValeur()) > 255) {
            throw new \InvalidArgumentException('La valeur ne peut pas dépasser 255 caractères');
        }

        return true;
    }
}