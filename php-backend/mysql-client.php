<?php
/**
 * MySQL Database Client for PHP Backend
 * Replaces Firestore REST API Client
 */

require_once __DIR__ . '/config.php';

class MySQLClient {
    private static ?PDO $instance = null;
    private static string $driver = 'mysql';

    public static function getConnection(): PDO {
        if (self::$instance !== null) {
            return self::$instance;
        }

        $host = getenv('MYSQL_HOST') ?: 'localhost';
        $port = getenv('MYSQL_PORT') ?: '3306';
        $db   = getenv('MYSQL_DATABASE') ?: 'connectit_db';
        $user = getenv('MYSQL_USER') ?: 'root';
        $pass = getenv('MYSQL_PASSWORD') ?: '';
        $charset = 'utf8mb4';

        $dsn = "mysql:host={$host};port={$port};dbname={$db};charset={$charset}";
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_PERSISTENT         => true
        ];

        try {
            self::$instance = new PDO($dsn, $user, $pass, $options);
            self::$driver = 'mysql';
            error_log("[MySQL] Connected to {$host}:{$port}/{$db}");
        } catch (PDOException $e) {
            error_log("[MySQL] Connection failed: " . $e->getMessage());
            throw new Exception('Database connection failed: ' . $e->getMessage());
        }

        return self::$instance;
    }

    public static function getDriver(): string {
        if (self::$instance === null) {
            self::getConnection();
        }
        return self::$driver;
    }

    public static function sqlNow(): string {
        return 'NOW()';
    }

    public static function close(): void {
        self::$instance = null;
    }
}

/**
 * Ticket Model - MySQL Operations for Tickets
 */
class TicketModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getAll(string $orderBy = 'created_at', string $order = 'DESC'): array {
        $sql = "SELECT * FROM tickets ORDER BY {$orderBy} {$order}";
        $stmt = $this->db->query($sql);
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByTicketNumber(string $ticketNumber): ?array {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE ticket_number = ?");
        $stmt->execute([$ticketNumber]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getOpen(): array {
        $stmt = $this->db->query("SELECT * FROM tickets WHERE status NOT IN ('Resolved', 'Closed', 'Canceled') ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getAssigned(string $userId): array {
        $stmt = $this->db->prepare("SELECT * FROM tickets WHERE assigned_to = ? ORDER BY created_at DESC");
        $stmt->execute([$userId]);
        return $stmt->fetchAll();
    }

    public function getUnassigned(): array {
        $stmt = $this->db->query("SELECT * FROM tickets WHERE assigned_to IS NULL OR assigned_to = '' ORDER BY created_at DESC");
        return $stmt->fetchAll();
    }

    public function getResolved(): array {
        $stmt = $this->db->query("SELECT * FROM tickets WHERE status IN ('Resolved', 'Closed') ORDER BY resolved_at DESC");
        return $stmt->fetchAll();
    }

    public function create(array $data): array {
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO tickets (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $id = $this->db->lastInsertId();
        return $this->getById((int)$id);
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        $values[] = $id;
        $sql = "UPDATE tickets SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM tickets WHERE id = ?");
        return $stmt->execute([$id]);
    }

    public function addHistory(int $ticketId, string $action, string $user, ?string $userId = null, ?string $details = null): void {
        $stmt = $this->db->prepare("INSERT INTO ticket_history (ticket_id, action, user, user_id, details) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ticketId, $action, $user, $userId, $details]);
    }

    public function getHistory(int $ticketId): array {
        $stmt = $this->db->prepare("SELECT * FROM ticket_history WHERE ticket_id = ? ORDER BY timestamp DESC");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function getComments(int $ticketId): array {
        $stmt = $this->db->prepare("SELECT * FROM comments WHERE ticket_id = ? ORDER BY created_at ASC");
        $stmt->execute([$ticketId]);
        return $stmt->fetchAll();
    }

    public function addComment(int $ticketId, string $userId, string $userName, string $message, bool $isInternal = false): void {
        $stmt = $this->db->prepare("INSERT INTO comments (ticket_id, user_id, user_name, message, is_internal) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$ticketId, $userId, $userName, $message, $isInternal ? 1 : 0]);
    }
}

/**
 * User Model - MySQL Operations for Users
 */
class UserModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM users ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByUid(string $uid): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE uid = ?");
        $stmt->execute([$uid]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function getByEmail(string $email): ?array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE email = ? AND is_active = TRUE");
        $stmt->execute([$email]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): array {
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO users (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $id = $this->db->lastInsertId();
        return $this->getById((int)$id);
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key !== 'id' && $key !== 'created_at') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        $values[] = $id;
        $sql = "UPDATE users SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function updateLastLogin(string $uid): void {
        $stmt = $this->db->prepare("UPDATE users SET last_login = NOW() WHERE uid = ?");
        $stmt->execute([$uid]);
    }

    public function getByRole(string $role): array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE role = ? AND is_active = TRUE ORDER BY name");
        $stmt->execute([$role]);
        return $stmt->fetchAll();
    }
}

/**
 * Category Model
 */
class CategoryModel {
    private PDO $db;
    public function __construct() { $this->db = MySQLClient::getConnection(); }
    public function getAll(): array { return $this->db->query("SELECT * FROM categories ORDER BY name")->fetchAll(); }
    public function create(string $name): bool { return $this->db->prepare("INSERT INTO categories (name) VALUES (?)")->execute([$name]); }
    public function delete(int $id): bool { return $this->db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]); }
}

/**
 * Subcategory Model
 */
class SubcategoryModel {
    private PDO $db;
    public function __construct() { $this->db = MySQLClient::getConnection(); }
    public function getAll(): array { return $this->db->query("SELECT * FROM subcategories ORDER BY name")->fetchAll(); }
    public function create(string $name): bool { return $this->db->prepare("INSERT INTO subcategories (name) VALUES (?)")->execute([$name]); }
    public function delete(int $id): bool { return $this->db->prepare("DELETE FROM subcategories WHERE id = ?")->execute([$id]); }
}

/**
 * Provider Model
 */
class ProviderModel {
    private PDO $db;
    public function __construct() { $this->db = MySQLClient::getConnection(); }
    public function getAll(): array { return $this->db->query("SELECT * FROM providers ORDER BY name")->fetchAll(); }
    public function create(string $name): bool { return $this->db->prepare("INSERT INTO providers (name) VALUES (?)")->execute([$name]); }
    public function delete(int $id): bool { return $this->db->prepare("DELETE FROM providers WHERE id = ?")->execute([$id]); }
}

/**
 * Group Model
 */
class GroupModel {
    private PDO $db;
    public function __construct() { $this->db = MySQLClient::getConnection(); }
    public function getAll(): array { return $this->db->query("SELECT * FROM groups ORDER BY name")->fetchAll(); }
    public function create(string $name, string $desc = ''): bool { return $this->db->prepare("INSERT INTO groups (name, description) VALUES (?, ?)")->execute([$name, $desc]); }
    public function delete(int $id): bool { return $this->db->prepare("DELETE FROM groups WHERE id = ?")->execute([$id]); }
    public function getMembers(int $groupId): array {
        $stmt = $this->db->prepare("SELECT * FROM users WHERE group_id = ? ORDER BY name");
        $stmt->execute([$groupId]);
        return $stmt->fetchAll();
    }
}

/**
 * SLA Policy Model
 */
class SLAPolicyModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM sla_policies WHERE is_active = TRUE ORDER BY priority");
        return $stmt->fetchAll();
    }

    public function getByPriorityAndCategory(string $priority, ?string $category = null): ?array {
        $sql = "SELECT * FROM sla_policies WHERE priority = ? AND is_active = TRUE";
        $params = [$priority];
        
        if ($category) {
            $sql .= " AND (category = ? OR category IS NULL) ORDER BY category DESC LIMIT 1";
            $params[] = $category;
        } else {
            $sql .= " AND category IS NULL LIMIT 1";
        }
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $result = $stmt->fetch();
        return $result ?: null;
    }
}

/**
 * Asset Model
 */
class AssetModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM assets ORDER BY name");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM assets WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function create(array $data): array {
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO assets (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $id = $this->db->lastInsertId();
        return $this->getById((int)$id);
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        $values[] = $id;
        $sql = "UPDATE assets SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM assets WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

/**
 * Knowledge Base Model
 */
class KnowledgeModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getAllPublished(): array {
        $stmt = $this->db->query("SELECT * FROM knowledge_articles WHERE status = 'Published' ORDER BY views DESC");
        return $stmt->fetchAll();
    }

    public function getById(int $id): ?array {
        $stmt = $this->db->prepare("SELECT * FROM knowledge_articles WHERE id = ?");
        $stmt->execute([$id]);
        $result = $stmt->fetch();
        return $result ?: null;
    }

    public function search(string $query): array {
        $search = "%{$query}%";
        $stmt = $this->db->prepare("SELECT * FROM knowledge_articles WHERE status = 'Published' AND (title LIKE ? OR content LIKE ? OR summary LIKE ?) ORDER BY views DESC");
        $stmt->execute([$search, $search, $search]);
        return $stmt->fetchAll();
    }

    public function incrementViews(int $id): void {
        $stmt = $this->db->prepare("UPDATE knowledge_articles SET views = views + 1 WHERE id = ?");
        $stmt->execute([$id]);
    }

    public function create(array $data): array {
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($value !== null && $value !== '') {
                $fields[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO knowledge_articles (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
        
        $id = $this->db->lastInsertId();
        return $this->getById((int)$id);
    }

    public function update(int $id, array $data): bool {
        $fields = [];
        $values = [];

        foreach ($data as $key => $value) {
            if ($key !== 'id') {
                $fields[] = "{$key} = ?";
                $values[] = $value;
            }
        }

        $values[] = $id;
        $sql = "UPDATE knowledge_articles SET " . implode(', ', $fields) . ", updated_at = NOW() WHERE id = ?";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute($values);
    }

    public function delete(int $id): bool {
        $stmt = $this->db->prepare("DELETE FROM knowledge_articles WHERE id = ?");
        return $stmt->execute([$id]);
    }
}

/**
 * Notification Model
 */
class NotificationModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function getForUser(string $userId, bool $unreadOnly = false): array {
        $sql = "SELECT * FROM notifications WHERE user_id = ?";
        $params = [$userId];
        
        if ($unreadOnly) {
            $sql .= " AND is_read = FALSE";
        }
        
        $sql .= " ORDER BY created_at DESC LIMIT 50";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function create(array $data): void {
        $fields = [];
        $values = [];
        $placeholders = [];

        foreach ($data as $key => $value) {
            if ($value !== null) {
                $fields[] = $key;
                $placeholders[] = '?';
                $values[] = $value;
            }
        }

        $sql = "INSERT INTO notifications (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($values);
    }

    public function markAsRead(int $id, string $userId): bool {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE id = ? AND user_id = ?");
        return $stmt->execute([$id, $userId]);
    }

    public function markAllAsRead(string $userId): void {
        $stmt = $this->db->prepare("UPDATE notifications SET is_read = TRUE, read_at = NOW() WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
    }

    public function getUnreadCount(string $userId): int {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = FALSE");
        $stmt->execute([$userId]);
        return (int) $stmt->fetchColumn();
    }
}

/**
 * System Settings Model
 */
class SystemSettingsModel {
    private PDO $db;

    public function __construct() {
        $this->db = MySQLClient::getConnection();
    }

    public function get(string $key): ?string {
        $stmt = $this->db->prepare("SELECT setting_value FROM system_settings WHERE setting_key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        return $result ? $result['setting_value'] : null;
    }

    public function getInt(string $key): int {
        $value = $this->get($key);
        return $value !== null ? (int) $value : 0;
    }

    public function getBool(string $key): bool {
        $value = $this->get($key);
        return $value === 'true' || $value === '1';
    }

    public function set(string $key, string $value, ?string $updatedBy = null): void {
        $stmt = $this->db->prepare("INSERT INTO system_settings (setting_key, setting_value, updated_by) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = ?, updated_by = ?, updated_at = NOW()");
        $stmt->execute([$key, $value, $updatedBy, $value, $updatedBy]);
    }

    public function getAll(): array {
        $stmt = $this->db->query("SELECT * FROM system_settings ORDER BY setting_key");
        return $stmt->fetchAll();
    }
}
