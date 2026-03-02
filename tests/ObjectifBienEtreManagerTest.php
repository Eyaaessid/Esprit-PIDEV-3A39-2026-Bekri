<?php

namespace App\Tests\Service;

use App\Entity\ObjectifBienEtre;
use App\Service2\ObjectifBienEtreManager;
use PHPUnit\Framework\TestCase;

class ObjectifBienEtreManagerTest extends TestCase
{
    public function testValidObjectif(): void
    {
        $objectif = new ObjectifBienEtre();
        $objectif->setTitre('Faire du sport');
        $objectif->setDateDebut(new \DateTime('2026-01-01'));
        $objectif->setDateFin(new \DateTime('2026-06-01'));
        $objectif->setValeurCible(10.0);

        $manager = new ObjectifBienEtreManager();
        $this->assertTrue($manager->validate($objectif));
    }

    public function testObjectifWithoutTitre(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $objectif = new ObjectifBienEtre();
        $objectif->setTitre('');

        $manager = new ObjectifBienEtreManager();
        $manager->validate($objectif);
    }

    public function testObjectifTitreTooShort(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $objectif = new ObjectifBienEtre();
        $objectif->setTitre('AB');

        $manager = new ObjectifBienEtreManager();
        $manager->validate($objectif);
    }

    public function testObjectifDateFinBeforeDateDebut(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date de fin doit être postérieure à la date de début');

        $objectif = new ObjectifBienEtre();
        $objectif->setTitre('Méditer chaque jour');
        $objectif->setDateDebut(new \DateTime('2026-06-01'));
        $objectif->setDateFin(new \DateTime('2026-01-01'));

        $manager = new ObjectifBienEtreManager();
        $manager->validate($objectif);
    }

    public function testObjectifValeurCibleNegative(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La valeur cible doit être supérieure à zéro');

        $objectif = new ObjectifBienEtre();
        $objectif->setTitre('Boire plus d eau');
        $objectif->setValeurCible(-5.0);

        $manager = new ObjectifBienEtreManager();
        $manager->validate($objectif);
    }
}