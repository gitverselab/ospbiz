<?php
class ReceiptSettingsController {
    public function index() {
        $db = Database::getInstance();
        $settings = $db->query("SELECT * FROM receipt_settings LIMIT 1")->fetch();
        
        $pageTitle = "Receipt/Invoice Settings";
        $childView = ROOT_PATH . '/app/views/settings/receipt/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $sql = "UPDATE receipt_settings SET 
                    company_name=?, company_address=?, company_tin=?, business_style=?, 
                    bir_permit_no=?, date_issued=?, valid_until=?, serial_begin=?, serial_end=?, is_vat_reg=? 
                    WHERE id=1"; // Always update row 1
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['company_name'], $_POST['company_address'], $_POST['company_tin'], $_POST['business_style'],
                $_POST['bir_permit_no'], $_POST['date_issued'], $_POST['valid_until'], 
                $_POST['serial_begin'], $_POST['serial_end'], $_POST['is_vat_reg']
            ]);
            
            header("Location: /settings/receipt");
        }
    }
}