<?php
require_once '../app/models/JournalModel.php';
require_once '../app/core/WorkflowEngine.php';

class JournalController {
    public function create() {
        // Auth Check assumed here
        $user = $_SESSION['user'];
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // CSRF Check needed here
            $data = [
                'date' => $_POST['date'],
                'ref' => $_POST['reference_no'],
                'desc' => $_POST['description']
            ];
            $lines = json_decode($_POST['lines_json'], true); // Lines sent as JSON string
            
            $model = new JournalModel();
            try {
                $id = $model->create($data, $lines, $user);
                header("Location: /journal/view?id=$id");
            } catch (Exception $e) {
                echo "Error: " . $e->getMessage();
            }
        } else {
            // Load accounts for dropdown
            $db = Database::getInstance();
            $accounts = $db->query("SELECT * FROM accounts WHERE company_id = {$user['company_id']}")->fetchAll();
            require '../app/views/journal/create.php';
        }
    }

    public function approve() {
        $id = $_POST['id'];
        $user = $_SESSION['user'];
        try {
            WorkflowEngine::transition('journal', $id, 'approve', $user['id'], $user['role']);
            header("Location: /journal/view?id=$id");
        } catch (Exception $e) {
            die($e->getMessage());
        }
    }
}