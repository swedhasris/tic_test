<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Middlewares\RoleMiddleware;
use App\Models\Ticket;
use App\Models\User;

class ReportController extends Controller {

    private Ticket $ticketModel;
    private User   $userModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->registerMiddleware(new RoleMiddleware(['agent', 'admin', 'super_admin', 'ultra_super_admin']));
        $this->ticketModel = new Ticket();
        $this->userModel   = new User();
    }

    public function index(Request $request, Response $response) {
        $db = \Core\Database::getInstance();

        // Tickets by status
        $byStatus = $this->ticketModel->getStatusCounts();

        // Tickets by priority
        $byPriority = $this->ticketModel->getPriorityCounts();

        // Tickets by category
        $byCategory = $db->fetchAll(
            "SELECT category, COUNT(*) AS cnt FROM tickets WHERE category != '' GROUP BY category ORDER BY cnt DESC LIMIT 10",
            []
        );

        // Agent performance
        $agentPerf = $db->fetchAll(
            "SELECT assigned_to_name, COUNT(*) AS total,
                    SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved,
                    AVG(CASE WHEN resolved_at IS NOT NULL
                        THEN TIMESTAMPDIFF(HOUR, created_at, resolved_at) END) AS avg_resolution_hours
             FROM tickets
             WHERE assigned_to IS NOT NULL AND assigned_to_name != ''
             GROUP BY assigned_to, assigned_to_name
             ORDER BY resolved DESC
             LIMIT 10",
            []
        );

        // SLA compliance
        $slaStats = $db->fetch(
            "SELECT
                COUNT(*) AS total,
                SUM(CASE WHEN resolution_sla_status = 'Completed' THEN 1 ELSE 0 END) AS met,
                SUM(CASE WHEN resolution_sla_status = 'Breached' THEN 1 ELSE 0 END) AS breached
             FROM tickets
             WHERE status IN ('Resolved','Closed')",
            []
        );

        // Monthly trend (last 6 months)
        $monthlyTrend = $db->fetchAll(
            "SELECT DATE_FORMAT(created_at, '%Y-%m') AS month,
                    COUNT(*) AS created,
                    SUM(CASE WHEN status IN ('Resolved','Closed') THEN 1 ELSE 0 END) AS resolved
             FROM tickets
             WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
             GROUP BY DATE_FORMAT(created_at, '%Y-%m')
             ORDER BY month ASC",
            []
        );

        return $this->render('reports/index', [
            'title'        => 'Reports & Analytics',
            'byStatus'     => $byStatus,
            'byPriority'   => $byPriority,
            'byCategory'   => $byCategory,
            'agentPerf'    => $agentPerf,
            'slaStats'     => $slaStats,
            'monthlyTrend' => $monthlyTrend,
        ]);
    }
}
