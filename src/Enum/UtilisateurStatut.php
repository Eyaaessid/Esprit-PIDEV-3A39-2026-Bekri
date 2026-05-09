<?php

namespace App\Enum;

enum UtilisateurStatut: string
{
    case ACTIF = 'ACTIF';
    case BLOQUE = 'BLOQUE';
    case INACTIF = 'INACTIF';
    case SUPPRIME = 'SUPPRIME';

    public function getLabel(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::BLOQUE => 'Bloqué',
            self::INACTIF => 'Inactif',
            self::SUPPRIME => 'Supprimé',
        };
    }
}