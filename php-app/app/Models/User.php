<?php

namespace App\Models;

use Core\Model;

class User extends Model {
    protected string $table      = 'users';
    protected string $primaryKey = 'id';

    public function findByEmail(string $email): ?array {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE email = ? LIMIT 1",
            [$email]
        );
        return $row ?: null;
    }

    public function findByUid(string $uid): ?array {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE uid = ? LIMIT 1",
            [$uid]
        );
        return $row ?: null;
    }

    public function getAll(array $filters = []): array {
        $sql    = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['role'])) {
            $sql     .= " AND role = ?";
            $params[] = $filters['role'];
        }
        if (isset($filters['is_active'])) {
            $sql     .= " AND is_active = ?";
            $params[] = (int) $filters['is_active'];
        }
        if (!empty($filters['search'])) {
            $sql     .= " AND (name LIKE ? OR email LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
        }

        $sql .= " ORDER BY name ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getAgents(): array {
        return $this->db->fetchAll(
            "SELECT uid, name, email, department FROM `{$this->table}`
             WHERE role IN ('agent','admin','super_admin','ultra_super_admin')
               AND is_active = 1
             ORDER BY name ASC",
            []
        );
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function update(string $uid, array $data): void {
        $setParts = [];
        $values   = [];
        foreach ($data as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $values[]   = $value;
        }
        $values[] = $uid;
        $setStr   = implode(', ', $setParts);

        $this->db->execute(
            "UPDATE `{$this->table}` SET {$setStr} WHERE uid = ?",
            $values
        );
    }

    public function deleteByUid(string $uid): void {
        $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE uid = ?",
            [$uid]
        );
    }

    public function emailExists(string $email, string $excludeUid = ''): bool {
        $sql    = "SELECT COUNT(*) AS cnt FROM `{$this->table}` WHERE email = ?";
        $params = [$email];
        if ($excludeUid) {
            $sql     .= " AND uid != ?";
            $params[] = $excludeUid;
        }
        $row = $this->db->fetch($sql, $params);
        return (int) ($row['cnt'] ?? 0) > 0;
    }

    public function getLeaderboard(int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT uid, name, role, department, points, tickets_resolved, avg_rating
             FROM `{$this->table}`
             WHERE is_active = 1 AND role IN ('agent','admin','super_admin','ultra_super_admin')
             ORDER BY points DESC, tickets_resolved DESC
             LIMIT ?",
            [$limit]
        );
    }
}
