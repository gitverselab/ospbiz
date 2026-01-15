<?php
class COAController {
    
    // --- READ: List all Accounts ---
    public function index() {
        $db = Database::getInstance();
        
        // Fetch accounts ordered by Code
        $accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();
        
        // Note: Make sure this path matches where you saved the view from the previous step!
        // If you used my previous code, it might be '/app/views/settings/coa/index.php'
        // If you kept your old structure, keep it as '/app/views/settings/coa.php'
        $childView = ROOT_PATH . '/app/views/settings/coa/index.php'; 
        
        $pageTitle = "Chart of Accounts";
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE: Save new Account ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $code = $_POST['code'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $subtype = $_POST['subtype'] ?? ''; // Handle subtype if your form sends it
            
            // 1. Check for duplicates
            $exists = $db->query("SELECT id FROM accounts WHERE code = '$code'")->fetch();
            if ($exists) {
                die("Error: Account Code '$code' already exists.");
            }

            try {
                $sql = "INSERT INTO accounts (company_id, code, name, type, subtype, status) VALUES (?, ?, ?, ?, ?, 'Active')";
                $stmt = $db->prepare($sql);
                // Hardcoded Company ID 1
                $stmt->execute([1, $code, $name, $type, $subtype]);
                
                header("Location: /settings/coa");
                exit;
            } catch (Exception $e) {
                die("Error creating account: " . $e->getMessage());
            }
        }
    }

    // --- UPDATE: Edit existing Account ---
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $id = $_POST['id'];
            $name = $_POST['name'];
            $type = $_POST['type'];
            $status = $_POST['status'];
            $subtype = $_POST['subtype'] ?? ''; // Optional
            
            // Note: We usually don't update 'code' or 'company_id' to maintain integrity, 
            // but if you really need to edit code, ensure it doesn't conflict.
            
            try {
                $sql = "UPDATE accounts SET name = ?, type = ?, subtype = ?, status = ? WHERE id = ?";
                $stmt = $db->prepare($sql);
                $stmt->execute([$name, $type, $subtype, $status, $id]);
                
                header("Location: /settings/coa");
                exit;
            } catch (Exception $e) {
                die("Error updating account: " . $e->getMessage());
            }
        }
    }

    // --- DELETE: Remove or Deactivate Account ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];

            try {
                // 1. Safety Check: Is this account used in any Journal Entries?
                // If you created the journal_lines table from the previous steps:
                $used = $db->query("SELECT id FROM journal_lines WHERE account_id = $id LIMIT 1")->fetch();

                if ($used) {
                    // It is safer to just mark as "Inactive" instead of deleting
                    // This prevents breaking your Balance Sheet history
                    $db->prepare("UPDATE accounts SET status = 'Inactive' WHERE id = ?")->execute([$id]);
                    // Optional: Show a message saying "Account deactivated instead of deleted because it has history."
                } else {
                    // Safe to delete entirely
                    $db->prepare("DELETE FROM accounts WHERE id = ?")->execute([$id]);
                }

                header("Location: /settings/coa");
                exit;
            } catch (Exception $e) {
                die("Error deleting account: " . $e->getMessage());
            }
        }
    }
}
?>