<?php

namespace App\Service2;

use App\Entity\Evenement;

class EvenementManager
{
    public function validate(Evenement $evenement): bool
    {
        if (empty($evenement->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (strlen($evenement->getTitre()) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }

        if ($evenement->getCapaciteMax() !== null && $evenement->getCapaciteMax() <= 0) {
            throw new \InvalidArgumentException('La capacité maximale doit être supérieure à zéro');
        }

        if ($evenement->getDateDebut() !== null && $evenement->getDateFin() !== null) {
            if ($evenement->getDateFin() <= $evenement->getDateDebut()) {
                throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
            }
        }

        return true;
    }
}