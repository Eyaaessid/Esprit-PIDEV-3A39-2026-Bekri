<?php

namespace App\Tests\Service2;

use App\Entity\SuiviQuotidien;
use App\Service2\SuiviQuotidienManager;
use PHPUnit\Framework\TestCase;

class SuiviQuotidienManagerTest extends TestCase
{
    public function testValidSuivi(): void
    {
        $suivi = new SuiviQuotidien();
        $suivi->setDate(new \DateTime('yesterday'));
        $suivi->setCommentaire('Bonne journée');

        $manager = new SuiviQuotidienManager();
        $this->assertTrue($manager->validate($suivi));
    }

    public function testSuiviDateNull(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date est obligatoire');

        $suivi = new SuiviQuotidien();

        $manager = new SuiviQuotidienManager();
        $manager->validate($suivi);
    }

    public function testSuiviDateFuture(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La date ne peut pas être dans le futur');

        $suivi = new SuiviQuotidien();
        $suivi->setDate(new \DateTime('+1 day'));

        $manager = new SuiviQuotidienManager();
        $manager->validate($suivi);
    }

    public function testSuiviCommentaireTropLong(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le commentaire ne peut pas dépasser 1000 caractères');

        $suivi = new SuiviQuotidien();
        $suivi->setDate(new \DateTime('yesterday'));
        $suivi->setCommentaire(str_repeat('a', 1001));

        $manager = new SuiviQuotidienManager();
        $manager->validate($suivi);
    }
}