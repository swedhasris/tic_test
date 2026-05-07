<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Auth;
use App\Middlewares\AuthMiddleware;
use App\Models\Ticket;
use App\Models\User;

/**
 * REST API Controller — returns JSON for AJAX calls.
 */
class ApiController extends Controller {

    private Ticket $ticketModel;
    private User   $userModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->ticketModel = new Ticket();
        $this->userModel   = new User();
    }

    // ── Tickets ──────────────────────────────────────────────────────────────

    public function tickets(Request $request, Response $response) {
        $user    = $this->currentUser();
        $filters = $request->getBody();

        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            $filters['created_by'] = $user['uid'];
        }

        $tickets = $this->ticketModel->getAll($filters);
        return $response->json(['success' => true, 'data' => $tickets]);
    }

    public function ticketStats(Request $request, Response $response) {
        $user = $this->currentUser();

        $stats = [
            'open'          => $this->ticketModel->getOpenCount(),
            'assigned_to_me'=> $this->ticketModel->getAssignedToMeCount($user['uid']),
            'resolved_today'=> $this->ticketModel->getResolvedTodayCount(),
            'sla_breaches'  => $this->ticketModel->getSlaBreachCount(),
            'by_status'     => $this->ticketModel->getStatusCounts(),
            'by_priority'   => $this->ticketModel->getPriorityCounts(),
        ];

        return $response->json(['success' => true, 'data' => $stats]);
    }

    // ── Users ─────────────────────────────────────────────────────────────────

    public function users(Request $request, Response $response) {
        $user = $this->currentUser();

        if (!in_array($user['role'], ['agent', 'admin', 'super_admin', 'ultra_super_admin'])) {
            return $response->json(['error' => 'Access denied'], 403);
        }

        $agents = $this->userModel->getAgents();
        return $response->json(['success' => true, 'data' => $agents]);
    }

    // ── Notifications ─────────────────────────────────────────────────────────

    public function notifications(Request $request, Response $response) {
        $user = $this->currentUser();
        $db   = \Core\Database::getInstance();

        $notifications = $db->fetchAll(
            "SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 20",
            [$user['uid']]
        );

        $unreadCount = $db->fetch(
            "SELECT COUNT(*) AS cnt FROM notifications WHERE user_id = ? AND is_read = 0",
            [$user['uid']]
        )['cnt'] ?? 0;

        return $response->json([
            'success'      => true,
            'data'         => $notifications,
            'unread_count' => (int) $unreadCount,
        ]);
    }

    public function markNotificationRead(Request $request, Response $response) {
        $user = $this->currentUser();
        $data = $request->getBody();
        $id   = isset($data['id']) ? (int) $data['id'] : 0;
        $db   = \Core\Database::getInstance();

        if ($id) {
            $db->execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ? AND user_id = ?",
                [$id, $user['uid']]
            );
        } else {
            // Mark all as read
            $db->execute(
                "UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ?",
                [$user['uid']]
            );
        }

        return $response->json(['success' => true]);
    }

    // ── SLA Status ────────────────────────────────────────────────────────────

    public function slaStatus(Request $request, Response $response) {
        $db = \Core\Database::getInstance();

        $atRisk = $db->fetchAll(
            "SELECT id, ticket_number, title, priority, resolution_deadline, resolution_sla_status
             FROM tickets
             WHERE status NOT IN ('Resolved','Closed','Canceled')
               AND resolution_deadline IS NOT NULL
               AND resolution_deadline <= DATE_ADD(NOW(), INTERVAL 2 HOUR)
             ORDER BY resolution_deadline ASC
             LIMIT 10",
            []
        );

        return $response->json(['success' => true, 'data' => $atRisk]);
    }

    // ── Leaderboard ───────────────────────────────────────────────────────────

    public function leaderboard(Request $request, Response $response) {
        $data = $this->userModel->getLeaderboard(10);
        return $response->json(['success' => true, 'data' => $data]);
    }

    // ── AI Chatbot ────────────────────────────────────────────────────────────

    public function aiChat(Request $request, Response $response) {
        $data    = $request->getBody();
        $message = trim($data['message'] ?? '');

        if ($message === '') {
            return $response->json(['error' => 'Message is required'], 400);
        }

        $apiKey  = getenv('AI_API_KEY');
        $model   = getenv('AI_MODEL') ?: 'gpt-3.5-turbo';
        $endpoint = getenv('AI_ENDPOINT') ?: 'https://api.openai.com/v1/chat/completions';

        if (!$apiKey) {
            // Return a canned response when no API key is configured
            return $response->json([
                'success' => true,
                'reply'   => 'AI Chatbot is not configured. Please set AI_API_KEY in your .env file.',
            ]);
        }

        $payload = json_encode([
            'model'    => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are a helpful IT support assistant for Connect IT. Help users with their IT issues and guide them through creating support tickets.'],
                ['role' => 'user',   'content' => $message],
            ],
            'max_tokens' => 500,
        ]);

        $ch = curl_init($endpoint);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $apiKey,
            ],
            CURLOPT_TIMEOUT        => 30,
        ]);

        $result = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($result === false || $httpCode !== 200) {
            return $response->json(['error' => 'AI service unavailable'], 503);
        }

        $json  = json_decode($result, true);
        $reply = $json['choices'][0]['message']['content'] ?? 'Sorry, I could not process your request.';

        return $response->json(['success' => true, 'reply' => $reply]);
    }
}
