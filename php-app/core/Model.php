<?php

namespace Core;

abstract class Model {
    protected Database $db;
    protected string $table = '';
    protected string $primaryKey = 'id';

    public function __construct() {
        $this->db = Database::getInstance();
    }

    /** Find a single record by primary key. */
    public function findById(int $id): ?array {
        $result = $this->db->fetch(
            "SELECT * FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
        return $result ?: null;
    }

    /** Return all rows, optionally ordered. */
    public function all(string $orderBy = ''): array {
        $sql = "SELECT * FROM `{$this->table}`";
        if ($orderBy) {
            $sql .= " ORDER BY {$orderBy}";
        }
        return $this->db->fetchAll($sql, []);
    }

    /**
     * Generic INSERT from an associative array.
     * Returns the last insert ID.
     */
    public function insert(array $data): int {
        $keys         = array_keys($data);
        $fields       = implode(', ', array_map(fn($k) => "`{$k}`", $keys));
        $placeholders = implode(', ', array_fill(0, count($keys), '?'));
        $values       = array_values($data);

        return (int) $this->db->execute(
            "INSERT INTO `{$this->table}` ({$fields}) VALUES ({$placeholders})",
            $values
        );
    }

    /**
     * Generic UPDATE by primary key.
     */
    public function updateById(int $id, array $data): void {
        $setParts = [];
        $values   = [];
        foreach ($data as $key => $value) {
            $setParts[] = "`{$key}` = ?";
            $values[]   = $value;
        }
        $values[] = $id;
        $setStr   = implode(', ', $setParts);

        $this->db->execute(
            "UPDATE `{$this->table}` SET {$setStr} WHERE `{$this->primaryKey}` = ?",
            $values
        );
    }

    /** Delete a record by primary key. */
    public function deleteById(int $id): void {
        $this->db->execute(
            "DELETE FROM `{$this->table}` WHERE `{$this->primaryKey}` = ?",
            [$id]
        );
    }

    /** Count all rows (optionally with a WHERE clause). */
    public function count(string $where = '', array $params = []): int {
        $sql = "SELECT COUNT(*) AS cnt FROM `{$this->table}`";
        if ($where) {
            $sql .= " WHERE {$where}";
        }
        $row = $this->db->fetch($sql, $params);
        return (int) ($row['cnt'] ?? 0);
    }
}
