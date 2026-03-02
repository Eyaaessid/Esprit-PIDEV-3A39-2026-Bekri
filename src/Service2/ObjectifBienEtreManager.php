<?php

namespace App\Service2;

use App\Entity\ObjectifBienEtre;

class ObjectifBienEtreManager
{
    public function validate(ObjectifBienEtre $objectif): bool
    {
        if (empty($objectif->getTitre())) {
            throw new \InvalidArgumentException('Le titre est obligatoire');
        }

        if (strlen($objectif->getTitre()) < 3) {
            throw new \InvalidArgumentException('Le titre doit contenir au moins 3 caractères');
        }

        if ($objectif->getDateFin() !== null && $objectif->getDateDebut() !== null) {
            if ($objectif->getDateFin() <= $objectif->getDateDebut()) {
                throw new \InvalidArgumentException('La date de fin doit être postérieure à la date de début');
            }
        }

        if ($objectif->getValeurCible() !== null && $objectif->getValeurCible() <= 0) {
            throw new \InvalidArgumentException('La valeur cible doit être supérieure à zéro');
        }

        return true;
    }
}