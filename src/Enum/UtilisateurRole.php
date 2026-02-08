<?php

namespace App\Enum;

enum UtilisateurRole: string
{
    case ADMIN = 'ADMIN';
    case USER = 'USER';
    case COACH = 'COACH';

    // Optional: nice display name for forms / UI
    public function label(): string
    {
        return match ($this) {
            self::ADMIN => 'Administrateur',
            self::USER => 'Utilisateur',
            self::COACH => 'Coach',
        };
    }
}