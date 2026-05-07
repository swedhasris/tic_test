<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use App\Middlewares\AuthMiddleware;
use App\Models\Ticket;
use App\Models\User;

class DashboardController extends Controller {

    private Ticket $ticketModel;
    private User   $userModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->ticketModel = new Ticket();
        $this->userModel   = new User();
    }

    public function index(Request $request, Response $response) {
        $user = $this->currentUser();

        // Stats
        $openCount        = $this->ticketModel->getOpenCount();
        $assignedToMe     = $this->ticketModel->getAssignedToMeCount($user['uid']);
        $resolvedToday    = $this->ticketModel->getResolvedTodayCount();
        $slaBreaches      = $this->ticketModel->getSlaBreachCount();

        // Recent tickets
        $recentTickets    = $this->ticketModel->getAll(['limit' => 8]);

        // Status breakdown
        $statusCounts     = $this->ticketModel->getStatusCounts();

        // Priority breakdown
        $priorityCounts   = $this->ticketModel->getPriorityCounts();

        // Leaderboard
        $leaderboard      = $this->userModel->getLeaderboard(5);

        // Activity chart data (last 7 days)
        $activityData     = $this->ticketModel->getRecentActivity(7);

        return $this->render('dashboard/index', [
            'title'          => 'Dashboard',
            'openCount'      => $openCount,
            'assignedToMe'   => $assignedToMe,
            'resolvedToday'  => $resolvedToday,
            'slaBreaches'    => $slaBreaches,
            'recentTickets'  => $recentTickets,
            'statusCounts'   => $statusCounts,
            'priorityCounts' => $priorityCounts,
            'leaderboard'    => $leaderboard,
            'activityData'   => $activityData,
        ]);
    }
}
