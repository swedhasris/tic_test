<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use Core\CSRF;
use App\Middlewares\AuthMiddleware;
use App\Models\Timesheet;
use App\Models\TimeCard;

class TimesheetController extends Controller {

    private Timesheet $timesheetModel;
    private TimeCard  $timeCardModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->timesheetModel = new Timesheet();
        $this->timeCardModel  = new TimeCard();
    }

    public function index(Request $request, Response $response) {
        $user = $this->currentUser();

        // Admins can see all; others see their own
        $filters = [];
        if (!in_array($user['role'], ['admin', 'super_admin', 'ultra_super_admin'])) {
            $filters['user_id'] = $user['uid'];
        }

        $timesheets = $this->timesheetModel->getAll($filters);

        // Get or create current week timesheet for the user
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime('sunday this week'));

        $currentTimesheet = $this->timesheetModel->findByUserAndWeek($user['uid'], $weekStart);
        if (!$currentTimesheet) {
            $tsId = $this->timesheetModel->create([
                'user_id'    => $user['uid'],
                'week_start' => $weekStart,
                'week_end'   => $weekEnd,
                'status'     => 'Draft',
            ]);
            $currentTimesheet = $this->timesheetModel->findById($tsId);
        }

        $entries = $this->timeCardModel->getForTimesheet($currentTimesheet['id']);

        return $this->render('timesheet/index', [
            'title'            => 'Timesheets',
            'timesheets'       => $timesheets,
            'currentTimesheet' => $currentTimesheet,
            'entries'          => $entries,
        ]);
    }

    public function create(Request $request, Response $response) {
        $this->validateCsrf();

        $user      = $this->currentUser();
        $data      = $request->getBody();
        $weekStart = $data['week_start'] ?? date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime($weekStart . ' +6 days'));

        $existing = $this->timesheetModel->findByUserAndWeek($user['uid'], $weekStart);
        if ($existing) {
            Application::$app->session->setFlash('error', 'A timesheet for that week already exists.');
            return $response->redirect('/php-app/timesheets');
        }

        $this->timesheetModel->create([
            'user_id'    => $user['uid'],
            'week_start' => $weekStart,
            'week_end'   => $weekEnd,
            'status'     => 'Draft',
        ]);

        CSRF::regenerate();
        Application::$app->session->setFlash('success', 'Timesheet created.');
        return $response->redirect('/php-app/timesheets');
    }

    public function addEntry(Request $request, Response $response) {
        $this->validateCsrf();

        $user = $this->currentUser();
        $data = $request->getBody();

        $timesheetId = isset($data['timesheet_id']) ? (int) $data['timesheet_id'] : 0;
        if (!$timesheetId) {
            return $response->redirect('/php-app/timesheets');
        }

        $timesheet = $this->timesheetModel->findById($timesheetId);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            Application::$app->session->setFlash('error', 'Access denied.');
            return $response->redirect('/php-app/timesheets');
        }

        if ($timesheet['status'] !== 'Draft') {
            Application::$app->session->setFlash('error', 'Cannot modify a submitted timesheet.');
            return $response->redirect('/php-app/timesheets');
        }

        $hours = (float) ($data['hours_worked'] ?? 0);
        if ($hours <= 0 || $hours > 24) {
            Application::$app->session->setFlash('error', 'Hours must be between 0 and 24.');
            return $response->redirect('/php-app/timesheets');
        }

        $this->timeCardModel->create([
            'timesheet_id'      => $timesheetId,
            'user_id'           => $user['uid'],
            'entry_date'        => $data['entry_date'] ?? date('Y-m-d'),
            'task'              => $data['task'] ?? '',
            'short_description' => $data['short_description'] ?? '',
            'description'       => $data['description'] ?? '',
            'hours_worked'      => $hours,
            'start_time'        => $data['start_time'] ?? '',
            'end_time'          => $data['end_time'] ?? '',
            'work_type'         => $data['work_type'] ?? '',
            'billable'          => $data['billable'] ?? 'No',
            'status'            => 'Draft',
        ]);

        $this->timesheetModel->recalculateTotal($timesheetId);

        CSRF::regenerate();
        Application::$app->session->setFlash('success', 'Time entry added.');
        return $response->redirect('/php-app/timesheets');
    }

    public function submit(Request $request, Response $response) {
        $this->validateCsrf();

        $user = $this->currentUser();
        $data = $request->getBody();
        $id   = isset($data['timesheet_id']) ? (int) $data['timesheet_id'] : 0;

        if (!$id) {
            return $response->redirect('/php-app/timesheets');
        }

        $timesheet = $this->timesheetModel->findById($id);
        if (!$timesheet || $timesheet['user_id'] !== $user['uid']) {
            Application::$app->session->setFlash('error', 'Access denied.');
            return $response->redirect('/php-app/timesheets');
        }

        $this->timesheetModel->update($id, [
            'status'       => 'Submitted',
            'submitted_at' => date('Y-m-d H:i:s'),
        ]);

        CSRF::regenerate();
        Application::$app->session->setFlash('success', 'Timesheet submitted for approval.');
        return $response->redirect('/php-app/timesheets');
    }
}
