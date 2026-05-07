<?php

namespace App\Models;

use Core\Model;

class TimeCard extends Model {
    protected string $table      = 'time_cards';
    protected string $primaryKey = 'id';

    public function getForTimesheet(int $timesheetId): array {
        return $this->db->fetchAll(
            "SELECT tc.*, t.ticket_number
             FROM `{$this->table}` tc
             LEFT JOIN tickets t ON t.id = tc.ticket_id
             WHERE tc.timesheet_id = ?
             ORDER BY tc.entry_date ASC, tc.created_at ASC",
            [$timesheetId]
        );
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
}
