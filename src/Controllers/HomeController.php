<?php

declare(strict_types=1);

namespace App\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Views\Twig;
use App\Models\Post;
use App\Models\Tag;

/**
 * Home Controller
 * 
 * Handles main page and public post views
 */
class HomeController
{
    private Twig $view;
    private Post $postModel;
    private Tag $tagModel;
    
    public function __construct(
        ?Twig $view = null,
        ?Post $postModel = null,
        ?Tag $tagModel = null
    ) {
        $this->view = $view ?? Twig::fromRaw('');
        $this->postModel = $postModel ?? new Post();
        $this->tagModel = $tagModel ?? new Tag();
    }
    
    /**
     * Main page - show latest posts
     */
    public function index(Request $request, Response $response, array $args): Response
    {
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = 10;
        
        $posts = $this->postModel->getPublishedPosts($page, $perPage);
        $totalCount = $this->postModel->getPublishedCount();
        $totalPages = (int) ceil($totalCount / $perPage);
        
        // Get widgets data
        $bestByRating = $this->postModel->getBestByRating(5);
        $bestByViews = $this->postModel->getBestByViews(5);
        $topTags = $this->tagModel->getTopTags(10);
        
        return $this->view->render($response, 'pages/home/index.twig', [
            'posts' => $posts,
            'pagination' => [
                'current' => $page,
                'total' => $totalPages,
                'has_prev' => $page > 1,
                'has_next' => $page < $totalPages,
                'prev_url' => $page > 1 ? '/page/' . ($page - 1) : null,
                'next_url' => $page < $totalPages ? '/page/' . ($page + 1) : null,
            ],
            'widgets' => [
                'best_by_rating' => $bestByRating,
                'best_by_views' => $bestByViews,
                'top_tags' => $topTags,
            ],
        ]);
    }
    
    /**
     * Show single post
     */
    public function show(Request $request, Response $response, array $args): Response
    {
        $slug = $args['slug'];
        $post = $this->postModel->getBySlug($slug);
        
        if (!$post) {
            // Return 404
            return $this->view->render($response->withStatus(404), 'errors/404.twig');
        }
        
        // Increment view count
        $this->postModel->incrementViews((int) $post['id']);
        
        // Get post tags
        $tags = $this->tagModel->getByPostId((int) $post['id']);
        
        // Get comments (would be implemented in Comment model)
        $comments = []; // TODO: Implement comments
        
        return $this->view->render($response, 'pages/posts/show.twig', [
            'post' => $post,
            'tags' => $tags,
            'comments' => $comments,
            'csrf_token' => \App\Middleware\CsrfMiddleware::getToken(),
        ]);
    }
    
    /**
     * Show posts by tag
     */
    public function byTag(Request $request, Response $response, array $args): Response
    {
        $tagSlug = $args['slug'];
        $page = max(1, (int) ($args['page'] ?? 1));
        $perPage = 10;
        
        $tag = $this->tagModel->getBySlug($tagSlug);
        
        if (!$tag) {
            return $this->view->render($response->withStatus(404), 'errors/404.twig');
        }
        
        $posts = $this->postModel->getByTag($tagSlug, $page, $perPage);
        
        return $this->view->render($response, 'pages/home/by_tag.twig', [
            'tag' => $tag,
            'posts' => $posts,
        ]);
    }
    
    /**
     * Search posts
     */
    public function search(Request $request, Response $response): Response
    {
        $query = trim($request->getQueryParam('q', ''));
        $page = max(1, (int) ($request->getQueryParam('page', '1')));
        $perPage = 10;
        
        $results = [];
        if (!empty($query)) {
            $results = $this->postModel->search($query, $page, $perPage);
        }
        
        return $this->view->render($response, 'pages/home/search.twig', [
            'query' => $query,
            'results' => $results,
            'csrf_token' => \App\Middleware\CsrfMiddleware::getToken(),
        ]);
    }
}
