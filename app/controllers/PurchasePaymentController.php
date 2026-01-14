<?php
class PurchasePaymentController {

    public function index() {
        $db = Database::getInstance();
        $sql = "SELECT pp.*, s.name as supplier_name, fa.name as account_name 
                FROM purchase_payments pp
                LEFT JOIN suppliers s ON pp.supplier_id = s.id
                LEFT JOIN financial_accounts fa ON pp.financial_account_id = fa.id
                ORDER BY pp.date DESC";
        $payments = $db->query($sql)->fetchAll();

        $pageTitle = "Purchase Payments";
        $childView = ROOT_PATH . '/app/views/expenses/payments/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // Step 1: Choose Supplier to see unpaid POs
    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1")->fetchAll();
        $accounts = $db->query("SELECT * FROM financial_accounts")->fetchAll(); // Banks/Cash

        // If Supplier is selected, fetch their open POs
        $openPOs = [];
        if (isset($_GET['supplier_id'])) {
            $supId = $_GET['supplier_id'];
            $sql = "SELECT * FROM purchase_orders 
                    WHERE supplier_id = ? AND status IN ('open', 'partial')
                    ORDER BY date ASC";
            $stmt = $db->prepare($sql);
            $stmt->execute([$supId]);
            $openPOs = $stmt->fetchAll();
        }

        $pageTitle = "New Purchase Payment";
        $childView = ROOT_PATH . '/app/views/expenses/payments/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Create Payment Header (The Binder)
                $sql = "INSERT INTO purchase_payments (company_id, supplier_id, financial_account_id, payment_method, reference_no, date, total_paid) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    1, 
                    $_POST['supplier_id'], 
                    $_POST['financial_account_id'], 
                    $_POST['payment_method'], 
                    $_POST['reference_no'], 
                    $_POST['date'], 
                    $_POST['total_paid']
                ]);
                $paymentId = $db->lastInsertId();

                // 2. Reduce Bank/Cash Balance
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$_POST['total_paid'], $_POST['financial_account_id']]);

                // 3. Loop through allocated POs
                $allocations = json_decode($_POST['allocations_json'], true);
                
                $allocSql = "INSERT INTO purchase_payment_allocations (purchase_payment_id, purchase_order_id, amount_applied) VALUES (?, ?, ?)";
                $allocStmt = $db->prepare($allocSql);
                
                $updatePOSql = "UPDATE purchase_orders SET amount_paid = amount_paid + ?, status = CASE WHEN amount_paid >= total_amount THEN 'paid' WHEN amount_paid > 0 THEN 'partial' ELSE status END WHERE id = ?";
                $updatePOStmt = $db->prepare($updatePOSql);

                foreach ($allocations as $alloc) {
                    if ($alloc['amount'] > 0) {
                        // Link Payment to PO
                        $allocStmt->execute([$paymentId, $alloc['po_id'], $alloc['amount']]);
                        
                        // Update PO Status
                        $updatePOStmt->execute([$alloc['amount'], $alloc['po_id']]);
                    }
                }

                $db->commit();
                header("Location: /expenses/payments");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}