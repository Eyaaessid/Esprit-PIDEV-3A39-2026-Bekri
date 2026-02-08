<?php

namespace App\Enum;

enum ParticipationStatut: string
{
    case INSCRIT = 'INSCRIT';
    case ANNULE = 'ANNULE';
    case PRESENT = 'PRESENT';
    case ABSENT = 'ABSENT';
}