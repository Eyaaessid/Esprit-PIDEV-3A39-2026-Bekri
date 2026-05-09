<?php

namespace App\Controller\Admin;

use App\Entity\Post;
use App\Repository\CommentaireRepository;
use App\Repository\PostRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin', name: 'admin_')]
class AdminController extends AbstractController
{
    // Tables page - Posts & Comments Management
    #[Route('/tables', name: 'tables', methods: ['GET'])]
    public function tables(
        Request $request,
        PostRepository $postRepository,
        CommentaireRepository $commentaireRepository
    ): Response
    {
        return $this->render('admin/table.html.twig', $this->buildContentManagementPayload(
            $request,
            $postRepository,
            $commentaireRepository
        ));
    }

    // Typography page
    #[Route('/typography', name: 'typography', methods: ['GET'])]
    public function typography(): Response
    {
        return $this->render('admin/typography.html.twig');
    }

    // Widgets page
    #[Route('/widgets', name: 'widgets', methods: ['GET'])]
    public function widgets(): Response
    {
        return $this->render('admin/widget.html.twig');
    }

    // Forms page
    #[Route('/forms', name: 'forms', methods: ['GET'])]
    public function forms(): Response
    {
        return $this->render('admin/form.html.twig');
    }

    // Elements → Buttons
    #[Route('/elements/buttons', name: 'elements_buttons', methods: ['GET'])]
    public function buttons(): Response
    {
        return $this->render('admin/button.html.twig');
    }

    // Elements → Other Elements
    #[Route('/elements/other', name: 'elements_other', methods: ['GET'])]
    public function otherElements(): Response
    {
        return $this->render('admin/element.html.twig');
    }

    // Charts page
    #[Route('/charts', name: 'charts', methods: ['GET'])]
    public function charts(
        Request $request,
        PostRepository $postRepository,
        CommentaireRepository $commentaireRepository
    ): Response
    {
        return $this->render('admin/chart.html.twig', $this->buildContentManagementPayload(
            $request,
            $postRepository,
            $commentaireRepository
        ));
    }

    // Blank page
    #[Route('/blank', name: 'blank', methods: ['GET'])]
    public function blank(): Response
    {
        return $this->render('admin/blank.html.twig');
    }

    // 404 page (useful for testing)
    #[Route('/404', name: '404', methods: ['GET'])]
    public function notFound(): Response
    {
        return $this->render('admin/404.html.twig');
    }

    private function buildContentManagementPayload(
        Request $request,
        PostRepository $postRepository,
        CommentaireRepository $commentaireRepository
    ): array {
        $limit = 20;
        $postsPage = max(1, $request->query->getInt('postsPage', 1));
        $commentsPage = max(1, $request->query->getInt('commentsPage', 1));

        $postsQueryBuilder = $postRepository->createAdminListQueryBuilder();
        $totalPosts = (int) (clone $postsQueryBuilder)
            ->select('COUNT(DISTINCT p.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $posts = $postsQueryBuilder
            ->setFirstResult(($postsPage - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $commentsQueryBuilder = $commentaireRepository->createAdminListQueryBuilder();
        $totalComments = (int) (clone $commentsQueryBuilder)
            ->select('COUNT(DISTINCT c.id)')
            ->getQuery()
            ->getSingleScalarResult();
        $comments = $commentsQueryBuilder
            ->setFirstResult(($commentsPage - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $postIds = array_values(array_filter(array_map(
            static fn (Post $post): ?int => $post->getId(),
            $posts
        )));

        return [
            'posts' => $posts,
            'comments' => $comments,
            'postMetrics' => $postRepository->getInteractionMetrics($postIds),
            'totalPosts' => $totalPosts,
            'totalComments' => $totalComments,
            'recentPostCount' => $postRepository->countVisibleCreatedSince(new \DateTimeImmutable('-7 days')),
            'totalLikes' => $postRepository->countTotalLikes(),
            'currentPostsPage' => $postsPage,
            'currentCommentsPage' => $commentsPage,
            'totalPostsPages' => max(1, (int) ceil($totalPosts / $limit)),
            'totalCommentsPages' => max(1, (int) ceil($totalComments / $limit)),
        ];
    }
}
