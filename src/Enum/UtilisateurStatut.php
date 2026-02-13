<?php

namespace App\Enum;

enum UtilisateurStatut: string
{
    case VIDE     = '';
    case ACTIF = 'ACTIF';
    case BLOQUE = 'bloque';
    case INACTIF = 'inactif';
    case SUPPRIME = 'supprime';

    public function label(): string
    {
        return match ($this) {
            self::ACTIF => 'Actif',
            self::BLOQUE => 'Bloqué',
            self::INACTIF => 'Inactif',
            self::SUPPRIME => 'Supprimé',
        };
    }
}