<?php
// Ensure we have access to models
require_once ROOT_PATH . '/app/models/JournalModel.php';

class JournalController {

    public function index() {
        // Show the Dashboard or List
        $childView = ROOT_PATH . '/app/views/dashboard.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        // 1. If POST request, process the form
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            // Logic to save data would go here
            // For now, let's just show the form again with a success message placeholder
        }

        // 2. Load Data for the View (Mock Accounts for now)
        $accounts = [
            ['id' => 1, 'code' => '1000', 'name' => 'Cash'],
            ['id' => 2, 'code' => '1200', 'name' => 'Accounts Receivable'],
            ['id' => 3, 'code' => '2000', 'name' => 'Accounts Payable'],
            ['id' => 4, 'code' => '4000', 'name' => 'Sales Revenue'],
            ['id' => 5, 'code' => '5000', 'name' => 'Rent Expense'],
        ];

        // 3. Render the View inside the Layout
        $childView = ROOT_PATH . '/app/views/journal/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function approve() {
        echo "Approval Logic Placeholder";
    }
}