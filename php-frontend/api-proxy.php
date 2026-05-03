<?php
/**
 * API Proxy - Bridges Frontend to MySQL Backend
 */

require_once __DIR__ . '/includes/config.php';

if (!isLoggedIn()) {
    http_response_code(401);
    exit(json_encode(['error' => 'Unauthorized']));
}

$action = $_GET['action'] ?? '';
$backendUrl = 'http://localhost:8000/api';

function callBackend($path) {
    global $backendUrl;
    $opts = [
        "http" => [
            "method" => $_SERVER['REQUEST_METHOD'],
            "header" => "Content-Type: application/json\r\n"
        ]
    ];
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $opts['http']['content'] = file_get_contents('php://input');
    }

    $context = stream_context_create($opts);
    return file_get_contents($backendUrl . $path, false, $context);
}

header('Content-Type: application/json');

switch ($action) {
    case 'categories':
        echo callBackend('/categories');
        break;
    case 'subcategories':
        echo callBackend('/subcategories');
        break;
    case 'providers':
        echo callBackend('/providers');
        break;
    case 'groups':
        echo callBackend('/groups');
        break;
    case 'group_members':
        $groupId = $_GET['groupId'] ?? '';
        echo callBackend('/groups/' . $groupId . '/members');
        break;
    default:
        http_response_code(400);
        echo json_encode(['error' => 'Invalid action']);
        break;
}
