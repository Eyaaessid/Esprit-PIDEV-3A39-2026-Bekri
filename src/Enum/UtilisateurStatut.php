<?php

namespace App\Enum;

enum UtilisateurStatut: string
{
    case ACTIF = 'ACTIF';
    case BLOQUE = 'bloque';
    case INACTIF = 'inactif';
    case SUPPRIME = 'supprime';

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