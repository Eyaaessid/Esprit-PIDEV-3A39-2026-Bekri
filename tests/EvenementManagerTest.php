<?php

namespace App\Tests\Service2;

use App\Entity\Evenement;
use App\Service2\EvenementManager;
use PHPUnit\Framework\TestCase;

class EvenementManagerTest extends TestCase
{
    public function testValidEvenement(): void
    {
        $evenement = new Evenement();
        $evenement->setTitre('Yoga matinal');
        $evenement->setCapaciteMax(20);
        $evenement->setDateDebut(new \DateTime('2026-04-01 09:00'));
        $evenement->setDateFin(new \DateTime('2026-04-01 11:00'));

        $manager = new EvenementManager();
        $this->assertTrue($manager->validate($evenement));
    }

    public function testEvenementTitreVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $evenement = new Evenement();
        $evenement->setTitre('');

        $manager = new EvenementManager();
        $manager->validate($evenement);
    }

    public function testEvenementTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $evenement = new Evenement();
        $evenement->setTitre('AB');

        $manager = new EvenementManager();
        $manager->validate($evenement);
    }

    public function testEvenementCapaciteNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La capacité maximale doit être supérieure à zéro');

        $evenement = new Evenement();
        $evenement->setTitre('Méditation');
        $evenement->setCapaciteMax(-5);

        $manager = new EvenementManager();
        $manager->validate($evenement);
    }

    public function testEvenementDateFinAvantDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure à la date de début');

        $evenement = new Evenement();
        $evenement->setTitre('Méditation');
        $evenement->setDateDebut(new \DateTime('2026-04-01 11:00'));
        $evenement->setDateFin(new \DateTime('2026-04-01 09:00'));

        $manager = new EvenementManager();
        $manager->validate($evenement);
    }
}