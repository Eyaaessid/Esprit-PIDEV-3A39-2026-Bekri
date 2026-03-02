<?php

namespace App\Tests\Service2;

use App\Entity\Utilisateur;
use App\Service2\UtilisateurManager;
use PHPUnit\Framework\TestCase;

class UtilisateurManagerTest extends TestCase
{
    public function testValidUtilisateur(): void
    {
        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setPrenom('Ahmed');
        $utilisateur->setEmail('ahmed.benali@gmail.com');
        $utilisateur->setDateNaissance(new \DateTime('-20 years'));

        $manager = new UtilisateurManager();
        $this->assertTrue($manager->validate($utilisateur));
    }

    public function testUtilisateurNomVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom est obligatoire');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('');
        $utilisateur->setEmail('test@gmail.com');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    public function testUtilisateurNomTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le nom doit contenir au moins 2 caractères');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('A');
        $utilisateur->setEmail('test@gmail.com');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    public function testUtilisateurEmailInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'email n\'est pas valide');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Ben Ali');
        $utilisateur->setEmail('email_invalide');

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }

    public function testUtilisateurAgeMoinsDe13Ans(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('L\'utilisateur doit avoir au moins 13 ans');

        $utilisateur = new Utilisateur();
        $utilisateur->setNom('Jeune');
        $utilisateur->setEmail('jeune@gmail.com');
        $utilisateur->setDateNaissance(new \DateTime('-10 years'));

        $manager = new UtilisateurManager();
        $manager->validate($utilisateur);
    }
}