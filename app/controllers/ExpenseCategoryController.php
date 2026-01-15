<?php
class ExpenseCategoryController {

    // --- LIST & MAP CATEGORIES ---
    public function index() {
        $db = Database::getInstance();

        // Fetch all categories with their linked account info
        $sql = "SELECT ec.*, a.code as account_code, a.name as account_name 
                FROM expense_categories ec 
                LEFT JOIN accounts a ON ec.account_id = a.id 
                ORDER BY ec.name ASC";
        $categories = $db->query($sql)->fetchAll();

        // Fetch Expense Accounts for the dropdown (Type: Expense or Cost of Goods Sold)
        $accounts = $db->query("SELECT * FROM accounts WHERE type IN ('Expense', 'Cost of Goods Sold') ORDER BY code ASC")->fetchAll();

        $pageTitle = "Expense Categories";
        $childView = ROOT_PATH . '/app/views/settings/categories/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE NEW CATEGORY ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $name = $_POST['name'];
            $desc = $_POST['description'];
            
            $stmt = $db->prepare("INSERT INTO expense_categories (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            
            header("Location: /settings/categories");
        }
    }

    // --- UPDATE MAPPING (Link to Account) ---
    public function updateMapping() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $catId = $_POST['category_id'];
            $accId = $_POST['account_id'];
            
            // If user selected "Select Account", set to NULL
            $accId = ($accId == "") ? NULL : $accId;

            $stmt = $db->prepare("UPDATE expense_categories SET account_id = ? WHERE id = ?");
            $stmt->execute([$accId, $catId]);
            
            header("Location: /settings/categories");
        }
    }

    // --- DELETE CATEGORY ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $db->prepare("DELETE FROM expense_categories WHERE id = ?")->execute([$id]);
            header("Location: /settings/categories");
        }
    }
}