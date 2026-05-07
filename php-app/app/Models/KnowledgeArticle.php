<?php

namespace App\Models;

use Core\Model;

class KnowledgeArticle extends Model {
    protected string $table      = 'knowledge_articles';
    protected string $primaryKey = 'id';

    public function getPublished(array $filters = []): array {
        $sql    = "SELECT * FROM `{$this->table}` WHERE status = 'Published'";
        $params = [];

        if (!empty($filters['category'])) {
            $sql     .= " AND category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['search'])) {
            $sql     .= " AND (title LIKE ? OR summary LIKE ? OR tags LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (!empty($filters['visibility'])) {
            $sql     .= " AND visibility = ?";
            $params[] = $filters['visibility'];
        }

        $sql .= " ORDER BY views DESC, created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getAll(array $filters = []): array {
        $sql    = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql     .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['category'])) {
            $sql     .= " AND category = ?";
            $params[] = $filters['category'];
        }

        $sql .= " ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function incrementViews(int $id): void {
        $this->db->execute(
            "UPDATE `{$this->table}` SET views = views + 1 WHERE id = ?",
            [$id]
        );
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function update(int $id, array $data): void {
        $this->updateById($id, $data);
    }

    public function generateArticleNumber(): string {
        $row = $this->db->fetch(
            "SELECT MAX(CAST(SUBSTRING(article_number, 3) AS UNSIGNED)) AS max_num FROM `{$this->table}`",
            []
        );
        $next = ((int) ($row['max_num'] ?? 0)) + 1;
        return 'KB' . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
