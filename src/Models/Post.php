<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Post Model
 * 
 * Handles all database operations for posts
 */
class Post
{
    private PDO $db;
    
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Get published posts with pagination
     */
    public function getPublishedPosts(int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name, u.avatar_path as author_avatar
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.is_published = 1
            ORDER BY p.published_at DESC, p.created_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get total count of published posts
     */
    public function getPublishedCount(): int
    {
        $stmt = $this->db->query('SELECT COUNT(*) FROM posts WHERE is_published = 1');
        return (int) $stmt->fetchColumn();
    }
    
    /**
     * Get post by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name, u.avatar_path as author_avatar
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.slug = :slug
            LIMIT 1
        ');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get post by ID (for admin/editing)
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Create new post
     */
    public function create(array $data): int|false
    {
        try {
            $this->db->beginTransaction();
            
            // Insert post
            $stmt = $this->db->prepare('
                INSERT INTO posts (author_id, title, slug, content, preview, image_path, is_published)
                VALUES (:author_id, :title, :slug, :content, :preview, :image_path, :is_published)
            ');
            $stmt->execute([
                'author_id' => $data['author_id'],
                'title' => $data['title'],
                'slug' => $data['slug'],
                'content' => $data['content'],
                'preview' => $data['preview'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'is_published' => $data['is_published'] ?? 0,
            ]);
            
            $postId = (int) $this->db->lastInsertId();
            
            // Add tags if provided
            if (!empty($data['tags'])) {
                $this->assignTags($postId, $data['tags']);
            }
            
            $this->db->commit();
            return $postId;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Create post error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update existing post
     */
    public function update(int $id, array $data): bool
    {
        try {
            $this->db->beginTransaction();
            
            $stmt = $this->db->prepare('
                UPDATE posts
                SET title = :title,
                    slug = :slug,
                    content = :content,
                    preview = :preview,
                    image_path = :image_path,
                    is_published = :is_published,
                    updated_at = NOW()
                WHERE id = :id
            ');
            $stmt->execute([
                'id' => $id,
                'title' => $data['title'],
                'slug' => $data['slug'],
                'content' => $data['content'],
                'preview' => $data['preview'] ?? null,
                'image_path' => $data['image_path'] ?? null,
                'is_published' => $data['is_published'] ?? 0,
            ]);
            
            // Update tags if provided
            if (isset($data['tags'])) {
                $this->assignTags($id, $data['tags']);
            }
            
            $this->db->commit();
            return true;
        } catch (\Exception $e) {
            $this->db->rollBack();
            error_log("Update post error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete post
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM posts WHERE id = :id');
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log("Delete post error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Increment view count
     */
    public function incrementViews(int $id): void
    {
        try {
            $stmt = $this->db->prepare('UPDATE posts SET views = views + 1 WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log("Increment views error: " . $e->getMessage());
        }
    }
    
    /**
     * Get posts by tag
     */
    public function getByTag(string $tagSlug, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare('
            SELECT DISTINCT p.*, u.username as author_name
            FROM posts p
            JOIN users u ON p.author_id = u.id
            JOIN post_tags pt ON p.id = pt.post_id
            JOIN tags t ON pt.tag_id = t.id
            WHERE p.is_published = 1 AND t.slug = :slug
            ORDER BY p.published_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':slug', $tagSlug, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Search posts
     */
    public function search(string $query, int $page = 1, int $perPage = 10): array
    {
        $offset = ($page - 1) * $perPage;
        
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name,
                   MATCH(p.title, p.content) AGAINST(:query IN NATURAL LANGUAGE MODE) as relevance
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.is_published = 1
              AND MATCH(p.title, p.content) AGAINST(:query IN NATURAL LANGUAGE MODE)
            ORDER BY relevance DESC, p.published_at DESC
            LIMIT :limit OFFSET :offset
        ');
        $stmt->bindValue(':query', $query, PDO::PARAM_STR);
        $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get best posts by rating
     */
    public function getBestByRating(int $limit = 5): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.is_published = 1 AND p.rating_count >= 3
            ORDER BY p.rating_avg DESC, p.rating_count DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get best posts by views
     */
    public function getBestByViews(int $limit = 5): array
    {
        $stmt = $this->db->prepare('
            SELECT p.*, u.username as author_name
            FROM posts p
            JOIN users u ON p.author_id = u.id
            WHERE p.is_published = 1
            ORDER BY p.views DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Assign tags to post
     */
    private function assignTags(int $postId, array $tagIds): void
    {
        // Remove existing tags
        $deleteStmt = $this->db->prepare('DELETE FROM post_tags WHERE post_id = :post_id');
        $deleteStmt->execute(['post_id' => $postId]);
        
        // Add new tags
        if (!empty($tagIds)) {
            $insertStmt = $this->db->prepare('INSERT INTO post_tags (post_id, tag_id) VALUES (:post_id, :tag_id)');
            foreach ($tagIds as $tagId) {
                $insertStmt->execute([
                    'post_id' => $postId,
                    'tag_id' => (int) $tagId,
                ]);
            }
        }
    }
}
