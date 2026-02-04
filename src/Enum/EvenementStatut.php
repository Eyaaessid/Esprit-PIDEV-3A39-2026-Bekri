<?php

namespace App\Enum;

enum EvenementStatut: string
{
    case PLANNED = 'PLANNED';
    case OPEN = 'OPEN';
    case FULL = 'FULL';
    case CANCELLED = 'CANCELLED';
    case FINISHED = 'FINISHED';
}