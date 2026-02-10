<?php

namespace App\Enum;

enum UtilisateurRole: string
{
    case admin = 'admin';
    case USER = 'USER';
    case COACH = 'coach';

    // Optional: nice display name for forms / UI
    public function label(): string
    {
        return match ($this) {
            self::admin => 'Administrateur',
            self::USER => 'Utilisateur',
            self::COACH => 'Coach',
        };
    }
}