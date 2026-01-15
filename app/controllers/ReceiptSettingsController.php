<?php
class ReceiptSettingsController {
    
    public function index() {
        $db = Database::getInstance();
        
        // Fetch active booklets
        $booklets = $db->query("SELECT * FROM invoice_booklets ORDER BY series_start DESC")->fetchAll();
        
        // Fetch Settings (Just Company Info now)
        $settings = $db->query("SELECT * FROM receipt_settings LIMIT 1")->fetch();
        
        $pageTitle = "Receipt Settings";
        $childView = ROOT_PATH . '/app/views/settings/receipt/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function storeBooklet() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $start = intval($_POST['series_start']);
            
            $stmt = $db->prepare("INSERT INTO invoice_booklets (company_id, booklet_number, series_start, series_end, current_counter, status) VALUES (1, ?, ?, ?, ?, 'active')");
            $stmt->execute([
                $_POST['booklet_number'], 
                $start, 
                $_POST['series_end'],
                $start // Current counter starts at start
            ]);
            
            header("Location: /settings/receipt");
        }
    }
}