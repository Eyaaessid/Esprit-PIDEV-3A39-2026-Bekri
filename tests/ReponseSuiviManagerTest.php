<?php

namespace App\Tests\Service2;

use App\Entity\ReponseSuivi;
use App\Service2\ReponseSuiviManager;
use PHPUnit\Framework\TestCase;

class ReponseSuiviManagerTest extends TestCase
{
    public function testValidReponse(): void
    {
        $reponse = new ReponseSuivi();
        $reponse->setValeur('great');

        $manager = new ReponseSuiviManager();
        $this->assertTrue($manager->validate($reponse));
    }

    public function testReponseValeurVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La valeur de la réponse est obligatoire');

        $reponse = new ReponseSuivi();
        $reponse->setValeur('');

        $manager = new ReponseSuiviManager();
        $manager->validate($reponse);
    }

    public function testReponseValeurTropLongue(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('La valeur ne peut pas dépasser 255 caractères');

        $reponse = new ReponseSuivi();
        $reponse->setValeur(str_repeat('x', 256));

        $manager = new ReponseSuiviManager();
        $manager->validate($reponse);
    }
}