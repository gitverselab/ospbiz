<?php
class ContactController {
    
    // --- SUPPLIERS ---
    public function suppliers() {
        $db = Database::getInstance();
        $contacts = $db->query("SELECT * FROM suppliers ORDER BY name ASC")->fetchAll();
        
        $pageTitle = "Manage Suppliers";
        $type = "supplier"; // For the view to know what to display
        $childView = ROOT_PATH . '/app/views/settings/contacts.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function createSupplier() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save('suppliers', $_POST);
            header("Location: /settings/suppliers");
        }
    }

    // --- CUSTOMERS ---
    public function customers() {
        $db = Database::getInstance();
        $contacts = $db->query("SELECT * FROM customers ORDER BY name ASC")->fetchAll();
        
        $pageTitle = "Manage Customers";
        $type = "customer";
        $childView = ROOT_PATH . '/app/views/settings/contacts.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function createCustomer() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->save('customers', $_POST);
            header("Location: /settings/customers");
        }
    }

    // --- SHARED SAVE LOGIC ---
    private function save($table, $data) {
        $db = Database::getInstance();
        $sql = "INSERT INTO $table (company_id, name, email, phone, address, tax_id) VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        // Hardcoded Company ID 1
        $stmt->execute([1, $data['name'], $data['email'], $data['phone'], $data['address'], $data['tax_id']]);
    }
}