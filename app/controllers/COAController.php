<?php
class COAController {
    
    public function index() {
        $db = Database::getInstance();
        
        // Fetch accounts ordered by Code
        $accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();
        
        $childView = ROOT_PATH . '/app/views/settings/coa.php';
        $pageTitle = "Chart of Accounts"; // Updates the header title
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            try {
                $sql = "INSERT INTO accounts (company_id, code, name, type, subtype) VALUES (?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                // Hardcoded Company ID 1 for now
                $stmt->execute([1, $_POST['code'], $_POST['name'], $_POST['type'], $_POST['subtype']]);
                
                // Redirect back to list
                header("Location: /settings/coa");
                exit;
            } catch (Exception $e) {
                // In production, log this error
                die("Error creating account: " . $e->getMessage());
            }
        }
    }
}