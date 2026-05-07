<?php

use Core\Application;

$app = Application::$app;

// ─── Auth ────────────────────────────────────────────────────────────────────
$app->router->get('/login',    [App\Controllers\AuthController::class, 'login']);
$app->router->post('/login',   [App\Controllers\AuthController::class, 'login']);
$app->router->get('/register', [App\Controllers\AuthController::class, 'register']);
$app->router->post('/register',[App\Controllers\AuthController::class, 'register']);
$app->router->get('/logout',   [App\Controllers\AuthController::class, 'logout']);

// ─── Dashboard ───────────────────────────────────────────────────────────────
$app->router->get('/', [App\Controllers\DashboardController::class, 'index']);

// ─── Tickets ─────────────────────────────────────────────────────────────────
$app->router->get('/tickets',          [App\Controllers\TicketController::class, 'index']);
$app->router->get('/tickets/create',   [App\Controllers\TicketController::class, 'create']);
$app->router->post('/tickets/create',  [App\Controllers\TicketController::class, 'create']);
$app->router->get('/tickets/detail',   [App\Controllers\TicketController::class, 'detail']);
$app->router->get('/tickets/edit',     [App\Controllers\TicketController::class, 'edit']);
$app->router->post('/tickets/edit',    [App\Controllers\TicketController::class, 'edit']);
$app->router->post('/tickets/delete',  [App\Controllers\TicketController::class, 'delete']);
$app->router->post('/tickets/assign',  [App\Controllers\TicketController::class, 'assign']);
$app->router->post('/tickets/comment', [App\Controllers\TicketController::class, 'storeComment']);
$app->router->post('/tickets/status',  [App\Controllers\TicketController::class, 'updateStatus']);

// ─── Users ───────────────────────────────────────────────────────────────────
$app->router->get('/users',          [App\Controllers\UserController::class, 'index']);
$app->router->get('/users/create',   [App\Controllers\UserController::class, 'create']);
$app->router->post('/users/create',  [App\Controllers\UserController::class, 'create']);
$app->router->get('/users/edit',     [App\Controllers\UserController::class, 'edit']);
$app->router->post('/users/edit',    [App\Controllers\UserController::class, 'edit']);
$app->router->post('/users/delete',  [App\Controllers\UserController::class, 'delete']);
$app->router->post('/users/toggle',  [App\Controllers\UserController::class, 'toggleActive']);

// ─── Timesheets ───────────────────────────────────────────────────────────────
$app->router->get('/timesheets',         [App\Controllers\TimesheetController::class, 'index']);
$app->router->post('/timesheets/create', [App\Controllers\TimesheetController::class, 'create']);
$app->router->post('/timesheets/entry',  [App\Controllers\TimesheetController::class, 'addEntry']);
$app->router->post('/timesheets/submit', [App\Controllers\TimesheetController::class, 'submit']);

// ─── Reports ─────────────────────────────────────────────────────────────────
$app->router->get('/reports', [App\Controllers\ReportController::class, 'index']);

// ─── REST API ─────────────────────────────────────────────────────────────────
$app->router->get('/api/tickets',          [App\Controllers\ApiController::class, 'tickets']);
$app->router->get('/api/tickets/stats',    [App\Controllers\ApiController::class, 'ticketStats']);
$app->router->get('/api/users',            [App\Controllers\ApiController::class, 'users']);
$app->router->get('/api/notifications',    [App\Controllers\ApiController::class, 'notifications']);
$app->router->post('/api/notifications/read', [App\Controllers\ApiController::class, 'markNotificationRead']);
$app->router->get('/api/sla/status',       [App\Controllers\ApiController::class, 'slaStatus']);
$app->router->get('/api/leaderboard',      [App\Controllers\ApiController::class, 'leaderboard']);
$app->router->post('/api/ai/chat',         [App\Controllers\ApiController::class, 'aiChat']);
