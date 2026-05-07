<?php

namespace App\Models;

use Core\Model;

class User extends Model {
    protected string $table = 'users';

    public function findByEmail($email) {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE email = ?", [$email]);
    }

    public function findByUid($uid) {
        return $this->db->fetch("SELECT * FROM {$this->table} WHERE uid = ?", [$uid]);
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

    public function update($uid, $data) {
        $fields = [];
        $values = [];
        foreach ($data as $key => $value) {
            $fields[] = "$key = ?";
            $values[] = $value;
        }
        $values[] = $uid;
        $fieldString = implode(', ', $fields);

        return $this->db->execute(
            "UPDATE {$this->table} SET {$fieldString} WHERE uid = ?",
            $values
        );
    }

    public function getAll() {
        return $this->db->fetchAll("SELECT * FROM {$this->table} ORDER BY name ASC");
    }
}
