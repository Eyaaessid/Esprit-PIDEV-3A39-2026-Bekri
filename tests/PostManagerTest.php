<?php

namespace App\Tests\Service2;

use App\Entity\Post;
use App\Service2\PostManager;
use PHPUnit\Framework\TestCase;

class PostManagerTest extends TestCase
{
    public function testValidPost(): void
    {
        $post = new Post();
        $post->setTitre('Mon premier post');
        $post->setContenu('Ceci est un contenu valide pour mon post.');

        $manager = new PostManager();
        $this->assertTrue($manager->validate($post));
    }

    public function testPostTitreVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre est obligatoire');

        $post = new Post();
        $post->setTitre('');
        $post->setContenu('Contenu valide ici.');

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostTitreTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le titre doit contenir au moins 3 caractères');

        $post = new Post();
        $post->setTitre('AB');
        $post->setContenu('Contenu valide ici.');

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostContenuVide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu est obligatoire');

        $post = new Post();
        $post->setTitre('Titre valide');
        $post->setContenu('');

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostContenuTropCourt(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le contenu doit contenir au moins 10 caractères');

        $post = new Post();
        $post->setTitre('Titre valide');
        $post->setContenu('Court');

        $manager = new PostManager();
        $manager->validate($post);
    }

    public function testPostRiskLevelInvalide(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Le niveau de risque est invalide');

        $post = new Post();
        $post->setTitre('Titre valide');
        $post->setContenu('Contenu suffisamment long ici.');
        $post->setRiskLevel('invalid');

        $manager = new PostManager();
        $manager->validate($post);
    }
}