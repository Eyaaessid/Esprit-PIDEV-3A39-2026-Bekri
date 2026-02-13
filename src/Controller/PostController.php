<?php

namespace App\Controller;

use App\Entity\Post;
use App\Repository\PostRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\String\Slugger\SluggerInterface;

class PostController extends AbstractController
{
    #[Route('/posts', name: 'posts_list', methods: ['GET'])]
    public function index(PostRepository $postRepository): Response
    {
        $posts = $postRepository->findBy(
            ['deletedAt' => null],
            ['createdAt' => 'DESC']
        );

        return $this->render('posts.html.twig', [
            'posts' => $posts,
        ]);
    }

    #[Route('/create', name: 'post_create', methods: ['POST'])]
    public function create(
        Request $request,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        // === TEMP TEST MODE: Force a hardcoded user (no login needed) ===
        $forcedUserId = 1;  // ← CHANGE THIS TO THE REAL ID OF john.doe@test.com !!!
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found in DB.');
            return $this->redirectToRoute('posts_list');
        }

        $this->addFlash('warning', 'TEST MODE: Using hardcoded user ID ' . $forcedUserId . ' (no real login)');

        // Validate CSRF token
        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('create_post', $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        // Create new post
        $post = new Post();
        $post->setUtilisateur($user);  // ← forced user
        $post->setTitre($request->request->get('titre'));
        $post->setContenu($request->request->get('contenu'));
        
        $categorie = $request->request->get('categorie');
        if ($categorie) {
            $post->setCategorie($categorie);
        }

        // Handle file upload
        $mediaFile = $request->files->get('media');
        if ($mediaFile) {
            $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$mediaFile->guessExtension();

            try {
                $mediaFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
                $post->setMediaUrl('/uploads/posts/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Failed to upload image. Please try again.');
            }
        }

        $entityManager->persist($post);
        $entityManager->flush();

        $this->addFlash('success', 'Post created successfully!');
        return $this->redirectToRoute('posts_list');
    }

   #[Route('/{id}', name: 'post_details', methods: ['GET'])]
public function show(Post $post, EntityManagerInterface $entityManager): Response
{
    // Check if post is deleted
    if ($post->getDeletedAt() !== null) {
        throw $this->createNotFoundException('Post not found');
    }

    // Check if current user has liked this post
    $userHasLiked = false;
    
    // Try to get logged-in user, or use first user for testing
    $user = $this->getUser();
    if (!$user) {
        $user = $entityManager->getRepository(\App\Entity\Utilisateur::class)->findOneBy([], ['id' => 'ASC']);
    }
    
    if ($user) {
        // Check if user has liked this post
        $like = $entityManager->getRepository(\App\Entity\Like::class)->findOneBy([
            'post' => $post,
            'utilisateur' => $user
        ]);
        $userHasLiked = ($like !== null);
    }

    return $this->render('post_details.html.twig', [
        'post' => $post,
        'userHasLiked' => $userHasLiked,
    ]);
}

    #[Route('/{id}/edit', name: 'post_edit', methods: ['POST'])]
    public function edit(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        // === TEMP TEST MODE: Force the same hardcoded user ===
        $forcedUserId = 1;  // ← same ID as above
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found.');
            return $this->redirectToRoute('posts_list');
        }

        $this->addFlash('warning', 'TEST MODE EDIT: Using hardcoded user ID ' . $forcedUserId);

        // Normally you'd check ownership — for test we skip or fake it
        // if ($post->getUtilisateur() !== $user) { ... }

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('edit' . $post->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        $post->setTitre($request->request->get('titre'));
        $post->setContenu($request->request->get('contenu'));
        
        $categorie = $request->request->get('categorie');
        $post->setCategorie($categorie ?: null);

        $mediaFile = $request->files->get('media');
        if ($mediaFile) {
            if ($post->getMediaUrl()) {
                $oldFile = $this->getParameter('kernel.project_dir') . '/public' . $post->getMediaUrl();
                if (file_exists($oldFile)) {
                    unlink($oldFile);
                }
            }

            $originalFilename = pathinfo($mediaFile->getClientOriginalName(), PATHINFO_FILENAME);
            $safeFilename = $slugger->slug($originalFilename);
            $newFilename = $safeFilename.'-'.uniqid().'.'.$mediaFile->guessExtension();

            try {
                $mediaFile->move(
                    $this->getParameter('uploads_directory'),
                    $newFilename
                );
                $post->setMediaUrl('/uploads/posts/' . $newFilename);
            } catch (FileException $e) {
                $this->addFlash('error', 'Failed to upload new image.');
            }
        }

        $post->setUpdatedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Post updated successfully!');
        return $this->redirectToRoute('posts_list');
    }

    #[Route('/{id}/delete', name: 'post_delete', methods: ['POST'])]
    public function delete(
        Request $request,
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        // === TEMP TEST MODE: Force hardcoded user ===
        $forcedUserId = 1;
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            $this->addFlash('error', 'Test user ID ' . $forcedUserId . ' not found.');
            return $this->redirectToRoute('posts_list');
        }

        $this->addFlash('warning', 'TEST MODE DELETE: Using hardcoded user ID ' . $forcedUserId);

        $token = $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete' . $post->getId(), $token)) {
            $this->addFlash('error', 'Invalid security token. Please try again.');
            return $this->redirectToRoute('posts_list');
        }

        $post->setDeletedAt(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', 'Post deleted successfully!');
        return $this->redirectToRoute('posts_list');
    }
    

    #[Route('/{id}/like', name: 'post_like', methods: ['POST'])]
    public function like(
        Post $post,
        EntityManagerInterface $entityManager
    ): Response {
        // === TEMP TEST MODE: Force hardcoded user ===
        $forcedUserId = 1;
        
        try {
            $user = $entityManager->getReference('App\Entity\Utilisateur', $forcedUserId);
        } catch (\Exception $e) {
            return $this->json(['error' => 'Test user not found'], 403);
        }

        $this->addFlash('warning', 'TEST MODE LIKE: Using hardcoded user ID ' . $forcedUserId);

        $existingLike = $entityManager->getRepository(Like::class)->findOneBy([
            'post' => $post,
            'utilisateur' => $user
        ]);

        if ($existingLike) {
            $entityManager->remove($existingLike);
            $liked = false;
        } else {
            $like = new Like();
            $like->setPost($post);
            $like->setUtilisateur($user);
            $entityManager->persist($like);
            $liked = true;
        }

        $entityManager->flush();

        return $this->json([
            'liked' => $liked,
            'count' => $post->getLikes()->count()
        ]);
    }
}