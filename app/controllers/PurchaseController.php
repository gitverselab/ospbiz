<?php
class PurchaseController {

    public function index() {
        $db = Database::getInstance();
        $sql = "SELECT po.*, s.name as supplier_name 
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                ORDER BY po.date DESC";
        $pos = $db->query($sql)->fetchAll();

        $pageTitle = "Purchase Orders";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();
        
        $pageTitle = "Create Purchase Order";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }
}

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Create PO Header
                $sql = "INSERT INTO purchase_orders (company_id, supplier_id, po_number, date, expected_delivery_date, status, total_amount) VALUES (?, ?, ?, ?, ?, 'open', ?)";
                $stmt = $db->prepare($sql);
                
                $lines = json_decode($_POST['lines_json'], true);
                $total = array_sum(array_column($lines, 'amount'));

                $stmt->execute([1, $_POST['supplier_id'], $_POST['po_number'], $_POST['date'], $_POST['expected_delivery_date'], $total]);
                $poId = $db->lastInsertId();

                // 2. Create PO Lines
                $lineSql = "INSERT INTO purchase_order_lines (purchase_order_id, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?)";
                $lineStmt = $db->prepare($lineSql);
                
                foreach ($lines as $line) {
                    $lineStmt->execute([$poId, $line['description'], $line['quantity'], $line['unit_price'], $line['amount']]);
                }

                $db->commit();
                header("Location: /expenses/purchases");
            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}