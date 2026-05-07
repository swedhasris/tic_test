<?php

namespace App\Models;

use Core\Model;

class SLAPolicy extends Model {
    protected string $table      = 'sla_policies';
    protected string $primaryKey = 'id';

    public function getActive(): array {
        return $this->db->fetchAll(
            "SELECT * FROM `{$this->table}` WHERE is_active = 1 ORDER BY priority ASC",
            []
        );
    }

    public function getForPriority(string $priority, string $category = ''): ?array {
        // Try category-specific first
        if ($category) {
            $row = $this->db->fetch(
                "SELECT * FROM `{$this->table}` WHERE priority = ? AND category = ? AND is_active = 1 LIMIT 1",
                [$priority, $category]
            );
            if ($row) {
                return $row;
            }
        }
        // Fall back to priority-only (category IS NULL)
        $row = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE priority = ? AND (category IS NULL OR category = '') AND is_active = 1 LIMIT 1",
            [$priority]
        );
        return $row ?: null;
    }

    /**
     * Calculate response and resolution deadlines for a ticket.
     * Returns ['response_deadline' => '...', 'resolution_deadline' => '...']
     */
    public function calculateDeadlines(string $priority, string $category = '', string $createdAt = ''): array {
        $policy = $this->getForPriority($priority, $category);
        if (!$policy) {
            return ['response_deadline' => null, 'resolution_deadline' => null];
        }

        $base = $createdAt ? strtotime($createdAt) : time();
        return [
            'response_deadline'   => date('Y-m-d H:i:s', $base + $policy['response_time_hours'] * 3600),
            'resolution_deadline' => date('Y-m-d H:i:s', $base + $policy['resolution_time_hours'] * 3600),
        ];
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function update(int $id, array $data): void {
        $this->updateById($id, $data);
    }
}
