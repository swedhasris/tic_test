<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use Core\CSRF;
use Core\Validator;
use App\Middlewares\AuthMiddleware;
use App\Models\Ticket;
use App\Models\User;
use App\Models\SLAPolicy;

class TicketController extends Controller {

    private Ticket    $ticketModel;
    private User      $userModel;
    private SLAPolicy $slaModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->ticketModel = new Ticket();
        $this->userModel   = new User();
        $this->slaModel    = new SLAPolicy();
    }

    public function index(Request $request, Response $response) {
        $user    = $this->currentUser();
        $filters = $request->getBody();

        // Non-admins/agents only see their own tickets
        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            $filters['created_by'] = $user['uid'];
        }

        $tickets = $this->ticketModel->getAll($filters);

        return $this->render('tickets/index', [
            'title'   => 'Incidents',
            'tickets' => $tickets,
            'filters' => $filters,
        ]);
    }

    public function detail(Request $request, Response $response) {
        $params = $request->getBody();
        $id     = isset($params['id']) ? (int) $params['id'] : 0;

        if (!$id) {
            return $response->redirect('/php-app/tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            Application::$app->session->setFlash('error', 'Ticket not found.');
            return $response->redirect('/php-app/tickets');
        }

        $user = $this->currentUser();

        // Non-agents can only view their own tickets
        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            if ($ticket['created_by'] !== $user['uid'] && $ticket['caller_user_id'] !== $user['uid']) {
                Application::$app->session->setFlash('error', 'Access denied.');
                return $response->redirect('/php-app/tickets');
            }
        }

        $comments    = $this->ticketModel->getComments($id, in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin']));
        $history     = $this->ticketModel->getHistory($id);
        $attachments = $this->ticketModel->getAttachments($id);
        $agents      = $this->userModel->getAgents();

        return $this->render('tickets/detail', [
            'title'       => 'Ticket - ' . $ticket['ticket_number'],
            'ticket'      => $ticket,
            'comments'    => $comments,
            'history'     => $history,
            'attachments' => $attachments,
            'agents'      => $agents,
        ]);
    }

    public function create(Request $request, Response $response) {
        $user = $this->currentUser();

        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data = $request->getBody();
            $v    = new Validator($data);
            $v->required('title')->required('caller');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('tickets/create', [
                    'title' => 'Create Ticket',
                    'data'  => $data,
                    'agents' => $this->userModel->getAgents(),
                ]);
            }

            $priority = $data['priority'] ?? '4 - Low';
            $category = $data['category'] ?? '';
            $deadlines = $this->slaModel->calculateDeadlines($priority, $category);

            $ticketNumber = $this->ticketModel->generateTicketNumber();

            $ticketData = [
                'ticket_number'       => $ticketNumber,
                'caller'              => $data['caller'],
                'caller_user_id'      => $user['uid'],
                'title'               => $data['title'],
                'description'         => $data['description'] ?? '',
                'category'            => $category,
                'subcategory'         => $data['subcategory'] ?? '',
                'service'             => $data['service'] ?? '',
                'channel'             => $data['channel'] ?? 'Self-service',
                'status'              => 'New',
                'impact'              => $data['impact'] ?? '3 - Low',
                'urgency'             => $data['urgency'] ?? '3 - Low',
                'priority'            => $priority,
                'assignment_group'    => $data['assignment_group'] ?? '',
                'assigned_to'         => $data['assigned_to'] ?? null,
                'assigned_to_name'    => '',
                'created_by'          => $user['uid'],
                'created_by_name'     => $user['name'],
                'response_deadline'   => $deadlines['response_deadline'],
                'resolution_deadline' => $deadlines['resolution_deadline'],
            ];

            // Resolve assigned_to name
            if (!empty($ticketData['assigned_to'])) {
                $agent = $this->userModel->findByUid($ticketData['assigned_to']);
                if ($agent) {
                    $ticketData['assigned_to_name'] = $agent['name'];
                }
            }

            $id = $this->ticketModel->create($ticketData);

            // Log history
            $this->ticketModel->addHistory([
                'ticket_id' => $id,
                'action'    => 'Ticket Created',
                'user_id'   => $user['uid'],
                'user_name' => $user['name'],
            ]);

            // Handle file upload
            $this->handleAttachment($id, 'ticket');

            CSRF::regenerate();
            Application::$app->session->setFlash('success', "Ticket {$ticketNumber} created successfully.");
            return $response->redirect("/php-app/tickets/detail?id={$id}");
        }

        return $this->render('tickets/create', [
            'title'  => 'Create New Incident',
            'agents' => $this->userModel->getAgents(),
            'data'   => [],
        ]);
    }

    public function edit(Request $request, Response $response) {
        $user   = $this->currentUser();
        $params = $request->getBody();
        $id     = isset($params['id']) ? (int) $params['id'] : 0;

        if (!$id) {
            return $response->redirect('/php-app/tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            Application::$app->session->setFlash('error', 'Ticket not found.');
            return $response->redirect('/php-app/tickets');
        }

        // Only agents/admins can edit
        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            Application::$app->session->setFlash('error', 'Access denied.');
            return $response->redirect("/php-app/tickets/detail?id={$id}");
        }

        if ($request->getMethod() === 'post') {
            $this->validateCsrf();

            $data = $request->getBody();
            $v    = new Validator($data);
            $v->required('title')->required('caller');

            if ($v->fails()) {
                Application::$app->session->setFlash('error', $v->errorString());
                return $this->render('tickets/edit', [
                    'title'  => 'Edit Ticket',
                    'ticket' => array_merge($ticket, $data),
                    'agents' => $this->userModel->getAgents(),
                ]);
            }

            $updateData = [
                'title'            => $data['title'],
                'caller'           => $data['caller'],
                'description'      => $data['description'] ?? '',
                'category'         => $data['category'] ?? '',
                'subcategory'      => $data['subcategory'] ?? '',
                'status'           => $data['status'] ?? $ticket['status'],
                'impact'           => $data['impact'] ?? $ticket['impact'],
                'urgency'          => $data['urgency'] ?? $ticket['urgency'],
                'priority'         => $data['priority'] ?? $ticket['priority'],
                'assignment_group' => $data['assignment_group'] ?? '',
                'assigned_to'      => $data['assigned_to'] ?? null,
                'assigned_to_name' => '',
                'updated_at'       => date('Y-m-d H:i:s'),
            ];

            // Set resolved_at when resolving
            if (in_array($updateData['status'], ['Resolved', 'Closed']) && !$ticket['resolved_at']) {
                $updateData['resolved_at'] = date('Y-m-d H:i:s');
                $updateData['resolution_sla_status'] = 'Completed';
            }

            // Resolve assigned_to name
            if (!empty($updateData['assigned_to'])) {
                $agent = $this->userModel->findByUid($updateData['assigned_to']);
                if ($agent) {
                    $updateData['assigned_to_name'] = $agent['name'];
                }
            }

            // Log field changes
            $trackFields = ['status', 'priority', 'assigned_to', 'category'];
            foreach ($trackFields as $field) {
                if (isset($updateData[$field]) && $ticket[$field] !== $updateData[$field]) {
                    $this->ticketModel->addHistory([
                        'ticket_id'  => $id,
                        'action'     => 'Field Updated',
                        'field_name' => $field,
                        'old_value'  => $ticket[$field],
                        'new_value'  => $updateData[$field],
                        'user_id'    => $user['uid'],
                        'user_name'  => $user['name'],
                    ]);
                }
            }

            $this->ticketModel->update($id, $updateData);

            CSRF::regenerate();
            Application::$app->session->setFlash('success', 'Ticket updated successfully.');
            return $response->redirect("/php-app/tickets/detail?id={$id}");
        }

        return $this->render('tickets/edit', [
            'title'  => 'Edit Ticket - ' . $ticket['ticket_number'],
            'ticket' => $ticket,
            'agents' => $this->userModel->getAgents(),
        ]);
    }

    public function delete(Request $request, Response $response) {
        $this->validateCsrf();

        $user = $this->currentUser();
        if (!in_array($user['role'], ['admin', 'super_admin', 'ultra_super_admin'])) {
            Application::$app->session->setFlash('error', 'Access denied.');
            return $response->redirect('/php-app/tickets');
        }

        $data = $request->getBody();
        $id   = isset($data['id']) ? (int) $data['id'] : 0;

        if ($id) {
            $this->ticketModel->delete($id);
            Application::$app->session->setFlash('success', 'Ticket deleted.');
        }

        return $response->redirect('/php-app/tickets');
    }

    public function assign(Request $request, Response $response) {
        $this->validateCsrf();

        $user = $this->currentUser();
        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            return $this->json(['error' => 'Access denied'], 403);
        }

        $data     = $request->getBody();
        $id       = isset($data['ticket_id']) ? (int) $data['ticket_id'] : 0;
        $agentUid = $data['assigned_to'] ?? '';

        if (!$id) {
            return $this->json(['error' => 'Invalid ticket'], 400);
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            return $this->json(['error' => 'Ticket not found'], 404);
        }

        $agentName = '';
        if ($agentUid) {
            $agent = $this->userModel->findByUid($agentUid);
            if ($agent) {
                $agentName = $agent['name'];
            }
        }

        $this->ticketModel->update($id, [
            'assigned_to'      => $agentUid ?: null,
            'assigned_to_name' => $agentName,
        ]);

        $this->ticketModel->addHistory([
            'ticket_id'  => $id,
            'action'     => 'Assigned',
            'field_name' => 'assigned_to',
            'old_value'  => $ticket['assigned_to_name'],
            'new_value'  => $agentName,
            'user_id'    => $user['uid'],
            'user_name'  => $user['name'],
        ]);

        return $response->redirect("/php-app/tickets/detail?id={$id}");
    }

    public function storeComment(Request $request, Response $response) {
        $this->validateCsrf();

        $data     = $request->getBody();
        $ticketId = isset($data['ticket_id']) ? (int) $data['ticket_id'] : 0;
        $user     = $this->currentUser();

        if (!$ticketId) {
            return $response->redirect('/php-app/tickets');
        }

        $ticket = $this->ticketModel->findById($ticketId);
        if (!$ticket) {
            return $response->redirect('/php-app/tickets');
        }

        $message = trim($data['message'] ?? '');
        if ($message === '') {
            Application::$app->session->setFlash('error', 'Comment cannot be empty.');
            return $response->redirect("/php-app/tickets/detail?id={$ticketId}");
        }

        $isInternal = isset($data['is_internal']) && $data['is_internal'] == '1' ? 1 : 0;

        // Only agents/admins can post internal notes
        if ($isInternal && !in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            $isInternal = 0;
        }

        $this->ticketModel->addComment([
            'ticket_id'   => $ticketId,
            'user_id'     => $user['uid'],
            'user_name'   => $user['name'],
            'user_role'   => $user['role'],
            'message'     => $message,
            'is_internal' => $isInternal,
        ]);

        $this->ticketModel->addHistory([
            'ticket_id' => $ticketId,
            'action'    => $isInternal ? 'Internal Note Added' : 'Comment Added',
            'user_id'   => $user['uid'],
            'user_name' => $user['name'],
        ]);

        CSRF::regenerate();
        return $response->redirect("/php-app/tickets/detail?id={$ticketId}");
    }

    public function updateStatus(Request $request, Response $response) {
        $this->validateCsrf();

        $user = $this->currentUser();
        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            Application::$app->session->setFlash('error', 'Access denied.');
            return $response->redirect('/php-app/tickets');
        }

        $data     = $request->getBody();
        $id       = isset($data['ticket_id']) ? (int) $data['ticket_id'] : 0;
        $newStatus = $data['status'] ?? '';

        $allowed = ['New', 'In Progress', 'On Hold', 'Resolved', 'Closed', 'Canceled', 'Pending Approval'];
        if (!$id || !in_array($newStatus, $allowed)) {
            return $response->redirect('/php-app/tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            return $response->redirect('/php-app/tickets');
        }

        $updateData = ['status' => $newStatus];
        if (in_array($newStatus, ['Resolved', 'Closed']) && !$ticket['resolved_at']) {
            $updateData['resolved_at']            = date('Y-m-d H:i:s');
            $updateData['resolution_sla_status']  = 'Completed';
        }

        $this->ticketModel->update($id, $updateData);
        $this->ticketModel->addHistory([
            'ticket_id'  => $id,
            'action'     => 'Status Changed',
            'field_name' => 'status',
            'old_value'  => $ticket['status'],
            'new_value'  => $newStatus,
            'user_id'    => $user['uid'],
            'user_name'  => $user['name'],
        ]);

        CSRF::regenerate();
        return $response->redirect("/php-app/tickets/detail?id={$id}");
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function handleAttachment(int $entityId, string $entityType): void {
        if (empty($_FILES['attachment']['name'])) {
            return;
        }

        $file     = $_FILES['attachment'];
        $allowed  = ['jpg', 'jpeg', 'png', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'txt', 'zip'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $maxSize  = 10 * 1024 * 1024; // 10 MB

        if (!in_array($ext, $allowed) || $file['size'] > $maxSize || $file['error'] !== UPLOAD_ERR_OK) {
            return;
        }

        $uploadDir = dirname(__DIR__, 2) . '/public/uploads/attachments/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        $dest    = $uploadDir . $newName;

        if (move_uploaded_file($file['tmp_name'], $dest)) {
            $user = $this->currentUser();
            $db   = \Core\Database::getInstance();
            $db->execute(
                "INSERT INTO attachments (entity_type, entity_id, file_name, original_name, file_path, file_size, mime_type, uploaded_by, uploaded_by_name)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)",
                [
                    $entityType, $entityId, $newName, $file['name'],
                    'uploads/attachments/' . $newName, $file['size'],
                    $file['type'] ?? '', $user['uid'], $user['name'],
                ]
            );
        }
    }
}
