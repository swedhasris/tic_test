<?php

namespace App\Models;

use Core\Model;

class Comment extends Model {
    protected string $table      = 'comments';
    protected string $primaryKey = 'id';

    public function getForTicket(int $ticketId, bool $includeInternal = true): array {
        $sql    = "SELECT * FROM `{$this->table}` WHERE ticket_id = ?";
        $params = [$ticketId];
        if (!$includeInternal) {
            $sql .= " AND is_internal = 0";
        }
        $sql .= " ORDER BY created_at ASC";
        return $this->db->fetchAll($sql, $params);
    }

    public function create(array $data): int {
        return $this->insert($data);
    }

    public function delete(int $id): void {
        $this->deleteById($id);
    }
}
