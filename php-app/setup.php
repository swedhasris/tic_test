<?php

/**
 * Connect IT — Database Setup Script
 * Run from the php-app/ directory: php setup.php
 */

require_once __DIR__ . '/core/Application.php';
require_once __DIR__ . '/core/Database.php';

use Core\Application;
use Core\Database;

$app = new Application(__DIR__);

echo "Connect IT — Database Setup\n";
echo "============================\n\n";

try {
    $db  = Database::getInstance();
    $pdo = $db->getConnection();

    // ── Schema ────────────────────────────────────────────────────────────────
    echo "[1/3] Creating tables...\n";
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("Schema file not found: {$schemaFile}");
    }
    $pdo->exec(file_get_contents($schemaFile));
    echo "      ✓ Tables created.\n\n";

    // ── Seed data ─────────────────────────────────────────────────────────────
    echo "[2/3] Seeding sample data...\n";
    $seedFile = __DIR__ . '/database/seed.sql';
    if (file_exists($seedFile)) {
        $pdo->exec(file_get_contents($seedFile));
        echo "      ✓ Sample data inserted.\n\n";
    } else {
        echo "      ⚠ Seed file not found, skipping.\n\n";
    }

    // ── Admin user ────────────────────────────────────────────────────────────
    echo "[3/3] Ensuring admin user exists...\n";
    $adminEmail = 'admin@connectit.com';
    $adminPass  = 'Admin@123!';

    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$adminEmail]);

    if (!$check->fetch()) {
        $uid  = 'u_' . bin2hex(random_bytes(8));
        $hash = password_hash($adminPass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare(
            "INSERT INTO users (uid, email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)"
        );
        $stmt->execute([$uid, $adminEmail, $hash, 'System Administrator', 'ultra_super_admin', 1]);
        echo "      ✓ Admin user created.\n";
        echo "        Email:    {$adminEmail}\n";
        echo "        Password: {$adminPass}\n\n";
    } else {
        echo "      ✓ Admin user already exists.\n\n";
    }

    echo "Setup completed successfully!\n";
    echo "Access the application at: " . (getenv('APP_URL') ?: 'http://localhost/php-app/public/') . "\n";

} catch (Exception $e) {
    echo "\n✗ Error during setup: " . $e->getMessage() . "\n";
    exit(1);
}
