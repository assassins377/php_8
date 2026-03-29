<?php

declare(strict_types=1);

namespace App\Models;

use App\Config\Database;
use PDO;

/**
 * Tag Model
 * 
 * Handles all database operations for tags
 */
class Tag
{
    private PDO $db;
    
    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::getInstance();
    }
    
    /**
     * Get tag by slug
     */
    public function getBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, name, slug, description
            FROM tags
            WHERE slug = :slug
            LIMIT 1
        ');
        $stmt->execute(['slug' => $slug]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get tag by ID
     */
    public function getById(int $id): ?array
    {
        $stmt = $this->db->prepare('
            SELECT id, name, slug, description
            FROM tags
            WHERE id = :id
            LIMIT 1
        ');
        $stmt->execute(['id' => $id]);
        return $stmt->fetch() ?: null;
    }
    
    /**
     * Get all tags
     */
    public function getAll(): array
    {
        $stmt = $this->db->query('
            SELECT t.id, t.name, t.slug, t.description, COUNT(pt.post_id) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id AND p.is_published = 1
            GROUP BY t.id
            ORDER BY t.name ASC
        ');
        return $stmt->fetchAll();
    }
    
    /**
     * Get top tags by post count
     */
    public function getTopTags(int $limit = 10): array
    {
        $stmt = $this->db->prepare('
            SELECT t.id, t.name, t.slug, COUNT(pt.post_id) as post_count
            FROM tags t
            LEFT JOIN post_tags pt ON t.id = pt.tag_id
            LEFT JOIN posts p ON pt.post_id = p.id AND p.is_published = 1
            GROUP BY t.id
            ORDER BY post_count DESC
            LIMIT :limit
        ');
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get tags for a specific post
     */
    public function getByPostId(int $postId): array
    {
        $stmt = $this->db->prepare('
            SELECT t.id, t.name, t.slug
            FROM tags t
            JOIN post_tags pt ON t.id = pt.tag_id
            WHERE pt.post_id = :post_id
            ORDER BY t.name ASC
        ');
        $stmt->execute(['post_id' => $postId]);
        return $stmt->fetchAll();
    }
    
    /**
     * Create new tag
     */
    public function create(string $name, string $slug, ?string $description = null): int|false
    {
        try {
            $stmt = $this->db->prepare('
                INSERT INTO tags (name, slug, description)
                VALUES (:name, :slug, :description)
            ');
            $stmt->execute([
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
            
            return (int) $this->db->lastInsertId();
        } catch (\Exception $e) {
            error_log("Create tag error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Update tag
     */
    public function update(int $id, string $name, string $slug, ?string $description = null): bool
    {
        try {
            $stmt = $this->db->prepare('
                UPDATE tags
                SET name = :name, slug = :slug, description = :description
                WHERE id = :id
            ');
            return $stmt->execute([
                'id' => $id,
                'name' => $name,
                'slug' => $slug,
                'description' => $description,
            ]);
        } catch (\Exception $e) {
            error_log("Update tag error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Delete tag
     */
    public function delete(int $id): bool
    {
        try {
            $stmt = $this->db->prepare('DELETE FROM tags WHERE id = :id');
            return $stmt->execute(['id' => $id]);
        } catch (\Exception $e) {
            error_log("Delete tag error: " . $e->getMessage());
            return false;
        }
    }
}
