<?php

namespace App\Models;

use Core\Model;

class Ticket extends Model {
    protected string $table      = 'tickets';
    protected string $primaryKey = 'id';

    public function findById(int $id): ?array {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE id = ? LIMIT 1",
            [$id]
        );
        return $row ?: null;
    }

    public function findByNumber(string $number): ?array {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE ticket_number = ? LIMIT 1",
            [$number]
        );
        return $row ?: null;
    }

    public function getAll(array $filters = []): array {
        $sql    = "SELECT * FROM `{$this->table}` WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql     .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (!empty($filters['priority'])) {
            $sql     .= " AND priority = ?";
            $params[] = $filters['priority'];
        }
        if (!empty($filters['category'])) {
            $sql     .= " AND category = ?";
            $params[] = $filters['category'];
        }
        if (!empty($filters['assigned_to'])) {
            $sql     .= " AND assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (!empty($filters['created_by'])) {
            $sql     .= " AND created_by = ?";
            $params[] = $filters['created_by'];
        }
        if (!empty($filters['search'])) {
            $sql     .= " AND (title LIKE ? OR ticket_number LIKE ? OR caller LIKE ?)";
            $like     = '%' . $filters['search'] . '%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }
        if (isset($filters['filter'])) {
            if ($filters['filter'] === 'open') {
                $sql .= " AND status NOT IN ('Resolved','Closed','Canceled')";
            } elseif ($filters['filter'] === 'assigned_to_me' && !empty($filters['uid'])) {
                $sql     .= " AND assigned_to = ?";
                $params[] = $filters['uid'];
            }
        }

        $sql .= " ORDER BY created_at DESC";

        if (!empty($filters['limit'])) {
            $sql     .= " LIMIT ?";
            $params[] = (int) $filters['limit'];
        }

        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function update(int $id, array $data): void {
        $this->updateById($id, $data);
    }

    public function delete(int $id): void {
        $this->deleteById($id);
    }

    // ── Stats ────────────────────────────────────────────────────────────────

    public function getOpenCount(): int {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM `{$this->table}`
             WHERE status NOT IN ('Resolved','Closed','Canceled')",
            []
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getAssignedToMeCount(string $uid): int {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM `{$this->table}`
             WHERE assigned_to = ? AND status NOT IN ('Resolved','Closed','Canceled')",
            [$uid]
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getResolvedTodayCount(): int {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM `{$this->table}`
             WHERE DATE(resolved_at) = CURDATE()",
            []
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getSlaBreachCount(): int {
        $row = $this->db->fetch(
            "SELECT COUNT(*) AS cnt FROM `{$this->table}`
             WHERE resolution_sla_status = 'Breached'
               AND status NOT IN ('Resolved','Closed','Canceled')",
            []
        );
        return (int) ($row['cnt'] ?? 0);
    }

    public function getStatusCounts(): array {
        return $this->db->fetchAll(
            "SELECT status, COUNT(*) AS cnt FROM `{$this->table}` GROUP BY status",
            []
        );
    }

    public function getPriorityCounts(): array {
        return $this->db->fetchAll(
            "SELECT priority, COUNT(*) AS cnt FROM `{$this->table}` GROUP BY priority",
            []
        );
    }

    public function getRecentActivity(int $days = 7): array {
        return $this->db->fetchAll(
            "SELECT DATE(created_at) AS day, COUNT(*) AS created,
                    SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved
             FROM `{$this->table}`
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
             GROUP BY DATE(created_at)
             ORDER BY day ASC",
            [$days]
        );
    }

    // ── Comments ─────────────────────────────────────────────────────────────

    public function getComments(int $ticketId, bool $includeInternal = true): array {
        $sql    = "SELECT * FROM comments WHERE ticket_id = ?";
        $params = [$ticketId];
        if (!$includeInternal) {
            $sql .= " AND is_internal = 0";
        }
        $sql .= " ORDER BY created_at ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function addComment(array $data): int {
        $keys         = array_keys($data);
        $fields       = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        return (int) $this->db->execute(
            "INSERT INTO comments ({$fields}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    // ── History ──────────────────────────────────────────────────────────────

    public function getHistory(int $ticketId): array {
        return $this->db->fetchAll(
            "SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY created_at ASC",
            [$ticketId]
        );
    }

    public function addHistory(array $data): void {
        $keys         = array_keys($data);
        $fields       = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $this->db->execute(
            "INSERT INTO ticket_history ({$fields}) VALUES ({$placeholders})",
            array_values($data)
        );
    }

    // ── Attachments ──────────────────────────────────────────────────────────

    public function getAttachments(int $ticketId): array {
        return $this->db->fetchAll(
            "SELECT * FROM attachments WHERE entity_type = 'ticket' AND entity_id = ? ORDER BY created_at DESC",
            [$ticketId]
        );
    }

    // ── Ticket number generation ──────────────────────────────────────────────

    public function generateTicketNumber(): string {
        $prefix = $this->db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'ticket_number_prefix'",
            []
        )['setting_value'] ?? 'INC';

        $next = (int) ($this->db->fetch(
            "SELECT setting_value FROM system_settings WHERE setting_key = 'ticket_number_next'",
            []
        )['setting_value'] ?? 1000000);

        // Increment the counter
        $this->db->execute(
            "UPDATE system_settings SET setting_value = ? WHERE setting_key = 'ticket_number_next'",
            [$next + 1]
        );

        return $prefix . str_pad($next, 7, '0', STR_PAD_LEFT);
    }
}
