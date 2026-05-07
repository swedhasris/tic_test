<?php

require_once __DIR__ . '/core/Application.php';
require_once __DIR__ . '/core/Database.php';

use Core\Application;
use Core\Database;

$app = new Application(__DIR__);

echo "Connect IT - Database Setup\n";
echo "============================\n";

try {
    $db = Database::getInstance();
    $pdo = $db->getConnection();
    
    echo "[1/2] Creating tables...\n";
    $sql = file_get_contents(__DIR__ . '/../mysql-schema.sql');
    $pdo->exec($sql);
    echo "Tables created successfully.\n";
    
    echo "[2/2] Creating admin user...\n";
    $adminEmail = 'admin@connectit.com';
    $adminPass = 'admin123';
    $hashedPass = password_hash($adminPass, PASSWORD_DEFAULT);
    $uid = 'u_' . bin2hex(random_bytes(8));
    
    $check = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $check->execute([$adminEmail]);
    if (!$check->fetch()) {
        $stmt = $pdo->prepare("INSERT INTO users (uid, email, password_hash, name, role, is_active) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$uid, $adminEmail, $hashedPass, 'System Administrator', 'admin', 1]);
        echo "Admin user created: $adminEmail / $adminPass\n";
    } else {
        echo "Admin user already exists.\n";
    }
    
    echo "\nSetup completed successfully!\n";
    
} catch (Exception $e) {
    echo "\nError during setup: " . $e->getMessage() . "\n";
}
