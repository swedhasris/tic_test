<?php

namespace App\Controllers;

use Core\Controller;
use Core\Request;
use Core\Response;
use Core\Application;
use App\Models\Ticket;
use App\Middlewares\AuthMiddleware;

class TicketController extends Controller {
    protected Ticket $ticketModel;

    public function __construct() {
        $this->registerMiddleware(new AuthMiddleware());
        $this->ticketModel = new Ticket();
    }

    public function index(Request $request, Response $response) {
        $filters = $request->getBody();
        $tickets = $this->ticketModel->getAll($filters);

        return $this->render('tickets/index', [
            'title' => 'Tickets',
            'tickets' => $tickets
        ]);
    }

    public function detail(Request $request, Response $response) {
        $id = $request->getBody()['id'] ?? null;
        if (!$id) {
            return $response->redirect('/php-app/tickets');
        }

        $ticket = $this->ticketModel->findById($id);
        if (!$ticket) {
            return $response->redirect('/php-app/tickets');
        }

        return $this->render('tickets/detail', [
            'title' => 'Ticket Detail - ' . $ticket['ticket_number'],
            'ticket' => $ticket
        ]);
    }

    public function create(Request $request, Response $response) {
        if ($request->getMethod() === 'post') {
            $data = $request->getBody();
            // Generate ticket number
            $data['ticket_number'] = 'INC' . mt_rand(1000000, 9999999);
            $data['created_by'] = Application::$app->session->get('user')['uid'];
            $data['created_by_name'] = Application::$app->session->get('user')['name'];
            
            $id = $this->ticketModel->create($data);
            return $response->redirect("/php-app/tickets/detail?id=$id");
        }

        return $this->render('tickets/create', [
            'title' => 'Create New Ticket'
        ]);
    }

    public function storeComment(Request $request, Response $response) {
        if ($request->getMethod() === 'post') {
            $data = $request->getBody();
            $ticketId = $data['ticket_id'];
            $user = Application::$app->session->get('user');
            
            $commentData = [
                'ticket_id' => $ticketId,
                'user_id' => $user['uid'],
                'user_name' => $user['name'],
                'user_role' => $user['role'],
                'message' => $data['message'],
                'is_internal' => isset($data['is_internal']) ? 1 : 0
            ];
            
            $db = \Core\Database::getInstance();
            $db->execute(
                "INSERT INTO comments (ticket_id, user_id, user_name, user_role, message, is_internal) VALUES (?, ?, ?, ?, ?, ?)",
                array_values($commentData)
            );
            
            return $response->redirect("/php-app/tickets/detail?id=$ticketId");
        }
    }
}
