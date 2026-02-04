<?php

namespace App\Enum;

enum UtilisateurRole: string
{
    case ADMIN = 'admin';
    case USER = 'user';
    case COACH = 'coach';

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