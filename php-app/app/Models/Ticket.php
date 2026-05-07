<?php

namespace App\Models;

use Core\Model;

class Ticket extends Model {
    protected string $table = 'tickets';

    public function findById($id) {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE id = ?", [$id]);
    }

    public function findByNumber($number) {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE ticket_number = ?", [$number]);
    }

    public function getAll($filters = []) {
        $sql = "SELECT * FROM {$this->table} WHERE 1=1";
        $params = [];

        if (isset($filters['status'])) {
            $sql .= " AND status = ?";
            $params[] = $filters['status'];
        }
        if (isset($filters['assigned_to'])) {
            $sql .= " AND assigned_to = ?";
            $params[] = $filters['assigned_to'];
        }
        if (isset($filters['priority'])) {
            $sql .= " AND priority = ?";
            $params[] = $filters['priority'];
        }

        $sql .= " ORDER BY created_at DESC";
        return $this->db->fetchAll($sql, $params);
    }

    public function getOpenCount() {
        return $this->db->fetch("SELECT COUNT(*) as count FROM {$this->table} WHERE status NOT IN ('Resolved', 'Closed', 'Canceled')")['count'];
    }

    public function getAssignedToMeCount($uid) {
        return $this->db->fetch("SELECT COUNT(*) as count FROM {$this->table} WHERE assigned_to = ? AND status NOT IN ('Resolved', 'Closed', 'Canceled')", [$uid])['count'];
    }

    public function create($data) {
        $keys = array_keys($data);
        $fields = implode(', ', $keys);
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $values = array_values($data);

        return $this->db->execute(
            "INSERT INTO {$this->table} ({$fields}) VALUES ({$placeholders})",
            $values
        );
    }

    public function update($id, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $id;
        $fieldString = implode(', ', $fields);

        return $this->db->execute(
            "UPDATE {$this->table} SET {$fieldString} WHERE id = ?",
            $values
        );
    }
}
