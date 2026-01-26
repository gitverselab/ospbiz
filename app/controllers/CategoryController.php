<?php
class CategoryController {

    // --- LIST & MAP CATEGORIES ---
    public function index() {
        $db = Database::getInstance();

        // Updated SQL to use 'categories' table
        $sql = "SELECT c.*, a.code as account_code, a.name as account_name 
                FROM categories c 
                LEFT JOIN accounts a ON c.account_id = a.id 
                ORDER BY c.name ASC";
        $categories = $db->query($sql)->fetchAll();

        // Fetch Accounts for the dropdown (Expense, COGS, Asset)
        $accounts = $db->query("SELECT * FROM accounts WHERE type IN ('expense', 'asset', 'liability', 'equity') ORDER BY code ASC")->fetchAll();

        $pageTitle = "Transaction Categories";
        // Make sure to rename your view folder from 'expense_categories' to 'categories' or update path below
        $childView = ROOT_PATH . '/app/views/settings/categories/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE NEW CATEGORY ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $name = $_POST['name'];
            $desc = $_POST['description'];
            
            // Updated table name
            $stmt = $db->prepare("INSERT INTO categories (name, description) VALUES (?, ?)");
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
            
            $accId = ($accId == "") ? NULL : $accId;

            // Updated table name
            $stmt = $db->prepare("UPDATE categories SET account_id = ? WHERE id = ?");
            $stmt->execute([$accId, $catId]);
            
            header("Location: /settings/categories");
        }
    }

    // --- DELETE CATEGORY ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            // Updated table name
            $db->prepare("DELETE FROM categories WHERE id = ?")->execute([$id]);
            header("Location: /settings/categories");
        }
    }
}