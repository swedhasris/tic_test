<?php

namespace App\Models;

use Core\Model;

class Timesheet extends Model {
    protected string $table      = 'timesheets';
    protected string $primaryKey = 'id';

    public function findByUserAndWeek(string $userId, string $weekStart): ?array {
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE user_id = ? AND week_start = ? LIMIT 1",
            [$userId, $weekStart]
        );
        return $row ?: null;
    }

    public function getForUser(string $userId, int $limit = 10): array {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE user_id = ? ORDER BY week_start DESC LIMIT ?",
            [$userId, $limit]
        );
    }

    public function getAll(array $filters = []): array {
        $sql    = "SELECT t.*, u.name AS user_name FROM `{$this->table}` t
                   LEFT JOIN users u ON u.uid = t.user_id WHERE 1=1";
        $params = [];

        if (!empty($filters['user_id'])) {
            $sql     .= " AND t.user_id = ?";
            $params[] = $filters['user_id'];
        }
        if (!empty($filters['status'])) {
            $sql     .= " AND t.status = ?";
            $params[] = $filters['status'];
        }

        $sql .= " ORDER BY t.week_start DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function update(int $id, array $data): void {
        $this->updateById($id, $data);
    }

    public function recalculateTotal(int $timesheetId): void {
        $row = $this->db->fetch(
            "SELECT SUM(hours_worked) AS total FROM time_cards WHERE timesheet_id = ?",
            [$timesheetId]
        );
        $total = (float) ($row['total'] ?? 0);
        $this->updateById($timesheetId, ['total_hours' => $total]);
    }
}
