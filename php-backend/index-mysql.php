<?php
/**
 * PHP Backend Router - MySQL Version
 * Replaces the Firestore-based backend with MySQL
 */

require_once __DIR__ . '/mysql-client.php';

// CORS headers for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-User-Id');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'];

// Remove trailing slash for consistency
$path = rtrim($path, '/') ?: '/';

/**
 * Helper to send JSON response.
 */
function jsonResponse(int $code, array $data): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Helper to parse JSON body.
 */
function getJsonBody(): ?array {
    $input = file_get_contents('php://input');
    if (!$input) return null;
    $decoded = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }
    return $decoded;
}

// Initialize models
$ticketModel = new TicketModel();
$userModel = new UserModel();
$assetModel = new AssetModel();
$knowledgeModel = new KnowledgeModel();
$notificationModel = new NotificationModel();
$categoryModel = new CategoryModel();
$subcategoryModel = new SubcategoryModel();
$providerModel = new ProviderModel();
$groupModel = new GroupModel();

/**
 * API Routes
 */

// Dropdown APIs
if ($path === '/api/categories' && $method === 'GET') { jsonResponse(200, $categoryModel->getAll()); }
if ($path === '/api/subcategories' && $method === 'GET') { jsonResponse(200, $subcategoryModel->getAll()); }
if ($path === '/api/providers' && $method === 'GET') { jsonResponse(200, $providerModel->getAll()); }
if ($path === '/api/groups' && $method === 'GET') { jsonResponse(200, $groupModel->getAll()); }
if (preg_match('#^/api/groups/(\d+)/members$#', $path, $matches) && $method === 'GET') {
    jsonResponse(200, $groupModel->getMembers((int)$matches[1]));
}

// Creation APIs
if ($path === '/api/categories' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($categoryModel->create($data['name'])) jsonResponse(201, ['success' => true]);
}
if ($path === '/api/subcategories' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($subcategoryModel->create($data['name'])) jsonResponse(201, ['success' => true]);
}
if ($path === '/api/providers' && $method === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    if ($providerModel->create($data['name'])) jsonResponse(201, ['success' => true]);
}

// 1. Health Check
if ($path === '/api/health' && $method === 'GET') {
    try {
        $db = MySQLClient::getConnection();
        $db->query('SELECT 1');
        jsonResponse(200, ['status' => 'ok', 'database' => 'mysql', 'timestamp' => date('c')]);
    } catch (Exception $e) {
        jsonResponse(500, ['status' => 'error', 'message' => $e->getMessage()]);
    }
}

// 2. DB Test
if ($path === '/api/db-test' && $method === 'GET') {
    try {
        $count = count($ticketModel->getAll());
        jsonResponse(200, [
            'status' => 'connected',
            'database' => 'connectit_db',
            'count' => $count,
            'timestamp' => date('c')
        ]);
    } catch (Exception $e) {
        jsonResponse(500, [
            'status' => 'error',
            'error' => $e->getMessage()
        ]);
    }
}

// 3. Get All Tickets
if ($path === '/api/tickets/all' && $method === 'GET') {
    try {
        $tickets = $ticketModel->getAll();
        jsonResponse(200, array_map(function($t) {
            $t['id'] = (string) $t['id'];
            return $t;
        }, $tickets));
    } catch (Exception $e) {
        error_log('Failed to fetch tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch tickets']);
    }
}

// 4. Get Open Tickets
if ($path === '/api/tickets/open' && $method === 'GET') {
    try {
        $tickets = $ticketModel->getOpen();
        jsonResponse(200, array_map(function($t) {
            $t['id'] = (string) $t['id'];
            return $t;
        }, $tickets));
    } catch (Exception $e) {
        error_log('Failed to fetch open tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch open tickets']);
    }
}

// 5. Get Tickets Assigned to User
if (preg_match('#^/api/tickets/assigned/(.+)$#', $path, $matches) && $method === 'GET') {
    $userId = $matches[1];
    try {
        $tickets = $ticketModel->getAssigned($userId);
        jsonResponse(200, array_map(function($t) {
            $t['id'] = (string) $t['id'];
            return $t;
        }, $tickets));
    } catch (Exception $e) {
        error_log('Failed to fetch assigned tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch assigned tickets']);
    }
}

// 6. Get Unassigned Tickets
if ($path === '/api/tickets/unassigned' && $method === 'GET') {
    try {
        $tickets = $ticketModel->getUnassigned();
        jsonResponse(200, array_map(function($t) {
            $t['id'] = (string) $t['id'];
            return $t;
        }, $tickets));
    } catch (Exception $e) {
        error_log('Failed to fetch unassigned tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch unassigned tickets']);
    }
}

// 7. Get Resolved Tickets
if ($path === '/api/tickets/resolved' && $method === 'GET') {
    try {
        $tickets = $ticketModel->getResolved();
        jsonResponse(200, array_map(function($t) {
            $t['id'] = (string) $t['id'];
            return $t;
        }, $tickets));
    } catch (Exception $e) {
        error_log('Failed to fetch resolved tickets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch resolved tickets']);
    }
}

// 8. Get Single Ticket with Comments
if (preg_match('#^/api/tickets/([^/]+)$#', $path, $matches) && $method === 'GET') {
    $id = $matches[1];
    try {
        $ticket = $ticketModel->getById((int) $id);
        if (!$ticket) {
            jsonResponse(404, ['error' => 'Ticket not found']);
        }
        
        $ticket['id'] = (string) $ticket['id'];
        $ticket['comments'] = array_map(function($c) {
            $c['id'] = (string) $c['id'];
            return $c;
        }, $ticketModel->getComments((int) $id));
        $ticket['history'] = array_map(function($h) {
            $h['id'] = (string) $h['id'];
            return $h;
        }, $ticketModel->getHistory((int) $id));
        
        jsonResponse(200, $ticket);
    } catch (Exception $e) {
        error_log('Failed to fetch ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch ticket']);
    }
}

// 9. Create Ticket
if ($path === '/api/tickets/create' && $method === 'POST') {
    $body = getJsonBody();
    if ($body === null) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    try {
        // Auto-assignment based on category
        $assignmentGroup = $body['assignmentGroup'] ?? '';
        if (!$assignmentGroup) {
            switch ($body['category'] ?? '') {
                case 'Network': $assignmentGroup = 'Network Team'; break;
                case 'Hardware': $assignmentGroup = 'Hardware Support'; break;
                case 'Software': $assignmentGroup = 'App Support'; break;
                case 'Database': $assignmentGroup = 'DBA Team'; break;
                default: $assignmentGroup = 'Service Desk';
            }
        }

        // Generate ticket number
        $ticketNumber = 'INC' . strval(rand(1000000, 9999999));

        $ticketData = [
            'ticket_number' => $ticketNumber,
            'caller' => $body['caller'] ?? 'System',
            'category' => $body['category'] ?? 'Inquiry / Help',
            'title' => $body['title'] ?? 'Untitled',
            'description' => $body['description'] ?? null,
            'status' => 'New',
            'priority' => $body['priority'] ?? '4 - Low',
            'impact' => $body['impact'] ?? '3 - Low',
            'urgency' => $body['urgency'] ?? '3 - Low',
            'channel' => $body['channel'] ?? 'Self-service',
            'assignment_group' => $assignmentGroup,
            'assigned_to' => $body['assignedTo'] ?? null,
            'assigned_to_name' => $body['assignedToName'] ?? null,
            'created_by' => $body['createdBy'] ?? $body['caller'] ?? 'System',
            'created_by_name' => $body['createdByName'] ?? $body['caller'] ?? 'System',
            'service' => $body['service'] ?? null,
            'service_offering' => $body['serviceOffering'] ?? null,
            'subcategory' => $body['subcategory'] ?? null
        ];

        $result = $ticketModel->create($ticketData);
        
        // Add creation history
        $ticketModel->addHistory(
            (int) $result['id'],
            'Ticket Created via API',
            $body['caller'] ?? 'System',
            $body['createdBy'] ?? null,
            json_encode($ticketData)
        );

        // Priority notification for high priority
        $priority = $body['priority'] ?? '';
        if ($priority === '1 - Critical' || $priority === '2 - High') {
            $ticketModel->addHistory(
                (int) $result['id'],
                'Manager Notified (High Priority)',
                'System Automation',
                null,
                'High priority ticket created'
            );
        }

        $result['id'] = (string) $result['id'];
        jsonResponse(200, $result);
    } catch (Exception $e) {
        error_log('Error creating ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create ticket: ' . $e->getMessage()]);
    }
}

// 10. Update Ticket
if (preg_match('#^/api/tickets/([^/]+)$#', $path, $matches) && $method === 'PUT') {
    $id = $matches[1];
    $body = getJsonBody();
    if ($body === null) {
        jsonResponse(400, ['error' => 'Invalid JSON body']);
    }

    try {
        // Get current ticket to calculate points
        $currentTicket = $ticketModel->getById((int) $id);
        if (!$currentTicket) {
            jsonResponse(404, ['error' => 'Ticket not found']);
        }

        $points = 0;
        if (($body['status'] === 'Resolved' || $body['status'] === 'Closed') && !$currentTicket['resolved_at']) {
            if ($currentTicket['resolution_deadline']) {
                $deadline = strtotime($currentTicket['resolution_deadline']) * 1000;
                $resolvedAt = time() * 1000;
                $createdAt = strtotime($currentTicket['created_at']) * 1000;

                if ($resolvedAt < $deadline) {
                    $totalSla = $deadline - $createdAt;
                    $timeSaved = $deadline - $resolvedAt;
                    $points = round(($timeSaved / $totalSla) * 100);
                    if ($points < 10) $points = 10;
                } else {
                    $points = 5;
                }
            }
        }

        $updateData = array_merge($body, [
            'points' => ($currentTicket['points'] ?? 0) + $points,
        ]);

        if ($body['status'] === 'Resolved' || $body['status'] === 'Closed') {
            $updateData['resolved_at'] = date('Y-m-d H:i:s');
        }

        $ticketModel->update((int) $id, $updateData);

        // Add history for status change
        if (isset($body['status']) && $body['status'] !== $currentTicket['status']) {
            $ticketModel->addHistory(
                (int) $id,
                "Status changed to {$body['status']}",
                $body['updatedBy'] ?? 'System',
                null,
                json_encode(['oldStatus' => $currentTicket['status'], 'newStatus' => $body['status']])
            );
        }

        $updatedTicket = $ticketModel->getById((int) $id);
        $updatedTicket['id'] = (string) $updatedTicket['id'];
        $updatedTicket['pointsAwarded'] = $points;
        
        jsonResponse(200, $updatedTicket);
    } catch (Exception $e) {
        error_log('Error updating ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to update ticket']);
    }
}

// 11. Delete Ticket
if (preg_match('#^/api/tickets/([^/]+)$#', $path, $matches) && $method === 'DELETE') {
    $id = $matches[1];
    try {
        $ticketModel->delete((int) $id);
        jsonResponse(200, ['message' => 'Ticket deleted successfully']);
    } catch (Exception $e) {
        error_log('Error deleting ticket: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to delete ticket']);
    }
}

// 12. Add Comment to Ticket
if (preg_match('#^/api/tickets/([^/]+)/comments$#', $path, $matches) && $method === 'POST') {
    $id = $matches[1];
    $body = getJsonBody();
    
    try {
        $ticketModel->addComment(
            (int) $id,
            $body['user_id'] ?? null,
            $body['user_name'] ?? 'Unknown',
            $body['message'] ?? '',
            $body['is_internal'] ?? false
        );
        
        jsonResponse(200, ['message' => 'Comment added successfully']);
    } catch (Exception $e) {
        error_log('Error adding comment: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to add comment']);
    }
}

/* ============================================================
   USER API ROUTES
   ============================================================ */

// Get all users
if ($path === '/api/users' && $method === 'GET') {
    try {
        $users = $userModel->getAll();
        jsonResponse(200, array_map(function($u) {
            $u['id'] = (string) $u['id'];
            unset($u['password_hash']);
            return $u;
        }, $users));
    } catch (Exception $e) {
        error_log('Failed to fetch users: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch users']);
    }
}

// Get user by UID
if (preg_match('#^/api/users/(.+)$#', $path, $matches) && $method === 'GET') {
    $uid = $matches[1];
    try {
        $user = $userModel->getByUid($uid);
        if (!$user) {
            jsonResponse(404, ['error' => 'User not found']);
        }
        $user['id'] = (string) $user['id'];
        unset($user['password_hash']);
        jsonResponse(200, $user);
    } catch (Exception $e) {
        error_log('Failed to fetch user: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch user']);
    }
}

// Create user
if ($path === '/api/users' && $method === 'POST') {
    $body = getJsonBody();
    try {
        $result = $userModel->create([
            'uid' => $body['uid'],
            'email' => $body['email'],
            'name' => $body['name'],
            'role' => $body['role'] ?? 'user',
            'phone' => $body['phone'] ?? null,
            'password_hash' => $body['password_hash'] ?? null
        ]);
        $result['id'] = (string) $result['id'];
        unset($result['password_hash']);
        jsonResponse(200, $result);
    } catch (Exception $e) {
        error_log('Failed to create user: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create user']);
    }
}

// Update user
if (preg_match('#^/api/users/(.+)$#', $path, $matches) && $method === 'PUT') {
    $uid = $matches[1];
    $body = getJsonBody();
    try {
        $user = $userModel->getByUid($uid);
        if (!$user) {
            jsonResponse(404, ['error' => 'User not found']);
        }
        
        $userModel->update((int) $user['id'], [
            'name' => $body['name'] ?? $user['name'],
            'email' => $body['email'] ?? $user['email'],
            'role' => $body['role'] ?? $user['role'],
            'phone' => $body['phone'] ?? $user['phone'],
            'is_active' => $body['is_active'] ?? $user['is_active']
        ]);
        
        $updated = $userModel->getById((int) $user['id']);
        $updated['id'] = (string) $updated['id'];
        unset($updated['password_hash']);
        jsonResponse(200, $updated);
    } catch (Exception $e) {
        error_log('Failed to update user: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to update user']);
    }
}

/* ============================================================
   AUTHENTICATION ROUTES
   ============================================================ */

// Simple hash function (same as frontend)
function simpleHash(string $str): string {
    $hash = 0;
    for ($i = 0; $i < strlen($str); $i++) {
        $char = ord($str[$i]);
        $hash = (($hash << 5) - $hash) + $char;
        $hash = $hash & $hash;
    }
    return 'h_' . abs($hash) . '_' . strlen($str);
}

if ($path === '/api/auth/login' && $method === 'POST') {
    $body = getJsonBody();
    try {
        $user = $userModel->getByEmail($body['email'] ?? '');
        
        if (!$user) {
            jsonResponse(401, ['error' => 'Invalid email or password']);
        }
        
        if ($user['password_hash'] && $user['password_hash'] !== simpleHash($body['password'] ?? '')) {
            jsonResponse(401, ['error' => 'Invalid email or password']);
        }
        
        $userModel->updateLastLogin($user['uid']);
        
        $user['id'] = (string) $user['id'];
        unset($user['password_hash']);
        jsonResponse(200, $user);
    } catch (Exception $e) {
        error_log('Login error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Login failed']);
    }
}

/* ============================================================
   ASSET API ROUTES
   ============================================================ */

if ($path === '/api/assets' && $method === 'GET') {
    try {
        $assets = $assetModel->getAll();
        jsonResponse(200, array_map(function($a) {
            $a['id'] = (string) $a['id'];
            return $a;
        }, $assets));
    } catch (Exception $e) {
        error_log('Failed to fetch assets: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch assets']);
    }
}

if ($path === '/api/assets' && $method === 'POST') {
    $body = getJsonBody();
    try {
        $result = $assetModel->create($body);
        $result['id'] = (string) $result['id'];
        jsonResponse(200, $result);
    } catch (Exception $e) {
        error_log('Failed to create asset: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create asset']);
    }
}

/* ============================================================
   KNOWLEDGE BASE API ROUTES
   ============================================================ */

if ($path === '/api/knowledge' && $method === 'GET') {
    try {
        $articles = $knowledgeModel->getAllPublished();
        jsonResponse(200, array_map(function($a) {
            $a['id'] = (string) $a['id'];
            return $a;
        }, $articles));
    } catch (Exception $e) {
        error_log('Failed to fetch knowledge articles: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch articles']);
    }
}

if (preg_match('#^/api/knowledge/([^/]+)$#', $path, $matches) && $method === 'GET') {
    $id = $matches[1];
    try {
        $article = $knowledgeModel->getById((int) $id);
        if (!$article) {
            jsonResponse(404, ['error' => 'Article not found']);
        }
        $knowledgeModel->incrementViews((int) $id);
        $article['id'] = (string) $article['id'];
        jsonResponse(200, $article);
    } catch (Exception $e) {
        error_log('Failed to fetch article: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch article']);
    }
}

/* ============================================================
   LEADERBOARD API ROUTE
   ============================================================ */

if ($path === '/api/leaderboard/daily' && $method === 'GET') {
    try {
        $db = MySQLClient::getConnection();
        $today = date('Y-m-d 00:00:00');
        
        $stmt = $db->prepare(
            "SELECT assigned_to, assigned_to_name, SUM(points) as total_points, COUNT(*) as resolved_count
             FROM tickets 
             WHERE status IN ('Resolved', 'Closed') 
               AND resolved_at >= ?
               AND assigned_to IS NOT NULL
             GROUP BY assigned_to, assigned_to_name
             ORDER BY total_points DESC"
        );
        $stmt->execute([$today]);
        $rows = $stmt->fetchAll();
        
        $leaderboard = array_map(function($row) {
            return [
                'id' => $row['assigned_to'],
                'name' => $row['assigned_to_name'] ?? $row['assigned_to'],
                'points' => (int) ($row['total_points'] ?? 0),
                'resolvedCount' => (int) $row['resolved_count']
            ];
        }, $rows);
        
        jsonResponse(200, $leaderboard);
    } catch (Exception $e) {
        error_log('Leaderboard error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch leaderboard']);
    }
}

/* ============================================================
   TIMESHEET API ROUTES (Keep existing - already uses MySQL)
   ============================================================ */

require_once __DIR__ . '/timesheet/AuthHelper.php';
require_once __DIR__ . '/timesheet/models/Task.php';
require_once __DIR__ . '/timesheet/models/Timesheet.php';
require_once __DIR__ . '/timesheet/models/TimeCard.php';

// GET /api/timesheet/tasks
if ($path === '/api/timesheet/tasks' && $method === 'GET') {
    try {
        $tasks = Task::getAll();
        jsonResponse(200, $tasks);
    } catch (Exception $e) {
        error_log('Timesheet tasks error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch tasks']);
    }
}

// GET /api/timesheet/week?week_start=YYYY-MM-DD
if ($path === '/api/timesheet/week' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);
        $timeCards = TimeCard::getByTimesheet($timesheet['id']);
        jsonResponse(200, [
            'timesheet' => $timesheet,
            'time_cards' => $timeCards,
        ]);
    } catch (Exception $e) {
        error_log('Timesheet week error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch timesheet']);
    }
}

// POST /api/timesheet/entry
if ($path === '/api/timesheet/entry' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();
    if (!$body) jsonResponse(400, ['error' => 'Invalid JSON body']);

    $weekStart = $body['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    $entryDate = $body['entry_date'] ?? '';
    $taskId = isset($body['task_id']) ? (int) $body['task_id'] : null;
    $hours = isset($body['hours_worked']) ? (float) $body['hours_worked'] : 0;
    $description = $body['description'] ?? '';

    if (!$entryDate || $hours <= 0) {
        jsonResponse(400, ['error' => 'entry_date and hours_worked are required']);
    }
    if ($hours > 24) {
        jsonResponse(400, ['error' => 'Hours per entry cannot exceed 24']);
    }

    try {
        $timesheet = Timesheet::getOrCreate($user['uid'], $user['name'], $weekStart);
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot edit a submitted or approved timesheet']);
        }

        $dayTotal = TimeCard::getDayTotal($timesheet['id'], $entryDate);
        if ($dayTotal + $hours > 24) {
            jsonResponse(400, ['error' => 'Total hours per day cannot exceed 24']);
        }

        if (TimeCard::exists($timesheet['id'], $entryDate, $taskId)) {
            jsonResponse(400, ['error' => 'Duplicate entry for this date and task']);
        }

        $id = TimeCard::create($timesheet['id'], $entryDate, $taskId, $hours, $description);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['id' => $id, 'message' => 'Time entry created']);
    } catch (Exception $e) {
        error_log('Timesheet entry create error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to create time entry']);
    }
}

// PUT /api/timesheet/entry/:id
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $matches) && $method === 'PUT') {
    $user = TimesheetAuth::requireAuth();
    $entryId = (int) $matches[1];
    $body = getJsonBody();
    if (!$body) jsonResponse(400, ['error' => 'Invalid JSON body']);

    try {
        $card = TimeCard::getById($entryId);
        if (!$card) jsonResponse(404, ['error' => 'Time entry not found']);

        $timesheet = Timesheet::getById($card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'You do not own this time entry']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot edit a submitted or approved timesheet']);
        }

        $taskId = isset($body['task_id']) ? (int) $body['task_id'] : (int) $card['task_id'];
        $hours = isset($body['hours_worked']) ? (float) $body['hours_worked'] : (float) $card['hours_worked'];
        $description = $body['description'] ?? $card['description'];
        $entryDate = $body['entry_date'] ?? $card['entry_date'];

        if ($hours <= 0 || $hours > 24) {
            jsonResponse(400, ['error' => 'Hours must be between 0.01 and 24']);
        }

        $dayTotal = TimeCard::getDayTotal($timesheet['id'], $entryDate);
        $adjustedTotal = $dayTotal - (float) $card['hours_worked'] + $hours;
        if ($adjustedTotal > 24) {
            jsonResponse(400, ['error' => 'Total hours per day cannot exceed 24']);
        }

        TimeCard::update($entryId, $taskId, $hours, $description);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['message' => 'Time entry updated']);
    } catch (Exception $e) {
        error_log('Timesheet entry update error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to update time entry']);
    }
}

// DELETE /api/timesheet/entry/:id
if (preg_match('#^/api/timesheet/entry/(\d+)$#', $path, $matches) && $method === 'DELETE') {
    $user = TimesheetAuth::requireAuth();
    $entryId = (int) $matches[1];

    try {
        $card = TimeCard::getById($entryId);
        if (!$card) jsonResponse(404, ['error' => 'Time entry not found']);

        $timesheet = Timesheet::getById($card['timesheet_id']);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'You do not own this time entry']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Cannot delete from a submitted or approved timesheet']);
        }

        TimeCard::delete($entryId);
        Timesheet::updateTotalHours($timesheet['id']);
        jsonResponse(200, ['message' => 'Time entry deleted']);
    } catch (Exception $e) {
        error_log('Timesheet entry delete error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to delete time entry']);
    }
}

// POST /api/timesheet/submit
if ($path === '/api/timesheet/submit' && $method === 'POST') {
    $user = TimesheetAuth::requireAuth();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;

    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            jsonResponse(403, ['error' => 'Unauthorized']);
        }
        if ($timesheet['status'] !== 'Draft') {
            jsonResponse(400, ['error' => 'Timesheet is already ' . $timesheet['status']]);
        }
        Timesheet::submit($timesheetId);
        jsonResponse(200, ['message' => 'Timesheet submitted for approval']);
    } catch (Exception $e) {
        error_log('Timesheet submit error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to submit timesheet']);
    }
}

// GET /api/timesheet/admin/list
if ($path === '/api/timesheet/admin/list' && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $status = $_GET['status'] ?? null;
    try {
        $timesheets = Timesheet::getAllForAdmin($status);
        jsonResponse(200, $timesheets);
    } catch (Exception $e) {
        error_log('Timesheet admin list error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch timesheets']);
    }
}

// GET /api/timesheet/admin/detail/:id
if (preg_match('#^/api/timesheet/admin/detail/(\d+)$#', $path, $matches) && $method === 'GET') {
    TimesheetAuth::requireAdmin();
    $timesheetId = (int) $matches[1];
    try {
        $timesheet = Timesheet::getById($timesheetId);
        if (!$timesheet) jsonResponse(404, ['error' => 'Timesheet not found']);
        $timeCards = TimeCard::getByTimesheet($timesheetId);
        jsonResponse(200, ['timesheet' => $timesheet, 'time_cards' => $timeCards]);
    } catch (Exception $e) {
        jsonResponse(500, ['error' => $e->getMessage()]);
    }
}

// POST /api/timesheet/admin/approve
if ($path === '/api/timesheet/admin/approve' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;
    $comment = $body['comment'] ?? null;

    try {
        Timesheet::approve($timesheetId, $comment);
        jsonResponse(200, ['message' => 'Timesheet approved']);
    } catch (Exception $e) {
        error_log('Timesheet approve error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to approve timesheet']);
    }
}

// POST /api/timesheet/admin/reject
if ($path === '/api/timesheet/admin/reject' && $method === 'POST') {
    TimesheetAuth::requireAdmin();
    $body = getJsonBody();
    $timesheetId = isset($body['timesheet_id']) ? (int) $body['timesheet_id'] : 0;
    $comment = $body['comment'] ?? '';

    if (!$comment) {
        jsonResponse(400, ['error' => 'Rejection comment is required']);
    }

    try {
        Timesheet::reject($timesheetId, $comment);
        jsonResponse(200, ['message' => 'Timesheet rejected']);
    } catch (Exception $e) {
        error_log('Timesheet reject error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to reject timesheet']);
    }
}

// GET /api/timesheet/reports/weekly
if ($path === '/api/timesheet/reports/weekly' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $weekStart = $_GET['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
    try {
        $hours = Timesheet::getWeeklyHoursByUser($user['uid'], $weekStart);
        jsonResponse(200, ['week_start' => $weekStart, 'daily_hours' => $hours]);
    } catch (Exception $e) {
        error_log('Timesheet weekly report error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch weekly report']);
    }
}

// GET /api/timesheet/reports/monthly
if ($path === '/api/timesheet/reports/monthly' && $method === 'GET') {
    $user = TimesheetAuth::requireAuth();
    $year = (int) ($_GET['year'] ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));
    try {
        $isAdmin = TimesheetAuth::isAdmin();
        if ($isAdmin && isset($_GET['all_users'])) {
            $data = Timesheet::getAllUsersMonthlyReport($year, $month);
        } else {
            $data = Timesheet::getMonthlyReport($user['uid'], $year, $month);
        }
        jsonResponse(200, ['year' => $year, 'month' => $month, 'data' => $data]);
    } catch (Exception $e) {
        error_log('Timesheet monthly report error: ' . $e->getMessage());
        jsonResponse(500, ['error' => 'Failed to fetch monthly report']);
    }
}

/* ============================================================
   TIMESHEET PAGE ROUTES (Bootstrap HTML UI)
   ============================================================ */

if ($path === '/timesheet' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/dashboard.php';
    exit;
}

if ($path === '/timesheet/admin' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/admin.php';
    exit;
}

if ($path === '/timesheet/reports' && $method === 'GET') {
    require_once __DIR__ . '/timesheet/views/reports.php';
    exit;
}

/* ============================================================
   Static file serving for production (non-API routes)
   ============================================================ */

$distPath = __DIR__ . '/../dist';
if (strpos($path, '/api/') !== 0) {
    $filePath = $distPath . $path;
    if (is_file($filePath)) {
        $ext = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html',
            'js' => 'application/javascript',
            'css' => 'text/css',
            'json' => 'application/json',
            'png' => 'image/png',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'gif' => 'image/gif',
            'svg' => 'image/svg+xml',
            'ico' => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2' => 'font/woff2',
            'ttf' => 'font/ttf',
            'eot' => 'application/vnd.ms-fontobject',
        ];
        $contentType = $mimeTypes[$ext] ?? 'application/octet-stream';
        header('Content-Type: ' . $contentType);
        readfile($filePath);
        exit;
    }

    // SPA fallback: serve index.html for client-side routing
    $indexPath = $distPath . '/index.html';
    if (is_file($indexPath)) {
        header('Content-Type: text/html');
        readfile($indexPath);
        exit;
    }
}

// Unknown API route
if (strpos($path, '/api/') === 0) {
    jsonResponse(404, ['error' => 'Not found']);
}

// Nothing matched and no dist/index.html
http_response_code(404);
echo 'Not found';
