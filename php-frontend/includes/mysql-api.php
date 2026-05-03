<?php
/**
 * MySQL API Client for PHP Frontend
 */

require_once __DIR__ . '/../../php-backend/mysql-client.php';

class MySQLAPI {
    private $userModel;
    private $ticketModel;
    private $groupModel;
    private $categoryModel;
    private $subcategoryModel;
    private $providerModel;

    public function __construct() {
        $this->userModel = new UserModel();
        $this->ticketModel = new TicketModel();
        $this->groupModel = new GroupModel();
        $this->categoryModel = new CategoryModel();
        $this->subcategoryModel = new SubcategoryModel();
        $this->providerModel = new ProviderModel();
    }

    public function listDocuments(string $collection): array {
        switch ($collection) {
            case 'users': return $this->userModel->getAll();
            case 'tickets': return $this->ticketModel->getAll();
            case 'groups': return $this->groupModel->getAll();
            case 'categories': return $this->categoryModel->getAll();
            case 'subcategories': return $this->subcategoryModel->getAll();
            case 'providers': return $this->providerModel->getAll();
            case 'tasks': 
                return [
                    ['id' => '1', 'name' => 'General Support', 'category' => 'Inquiry'],
                    ['id' => '2', 'name' => 'Password Reset', 'category' => 'Access']
                ];
            default: return [];
        }
    }

    public function getDocument(string $collection, $id) {
        switch ($collection) {
            case 'tickets': return $this->ticketModel->getById((int)$id);
            case 'users': return $this->userModel->getById((int)$id);
            default: return null;
        }
    }
}
