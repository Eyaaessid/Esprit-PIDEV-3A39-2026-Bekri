<?php

namespace App\Enum;

enum UtilisateurRole: string
{
    case USER = 'user';
    case COACH = 'coach';
    case ADMIN = 'admin';

    public function getLabel(): string
    {
        return match ($this) {
            self::USER => 'Utilisateur',
            self::COACH => 'Coach',
            self::ADMIN => 'Administrateur',
        };
    }

    public function getRoles(): array
    {
        return match ($this) {
            self::USER => ['ROLE_USER'],
            self::COACH => ['ROLE_USER', 'ROLE_COACH'],
            self::ADMIN => ['ROLE_USER', 'ROLE_ADMIN'],
        };
    }

    public static function fromString(string $role): self
    {
        return match (strtolower($role)) {
            'user', 'utilisateur' => self::USER,
            'coach' => self::COACH,
            'admin', 'administrateur' => self::ADMIN,
            default => self::USER,
        };
    }
}