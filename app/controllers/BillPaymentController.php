<?php
class BillPaymentController {

    public function index() {
        $db = Database::getInstance();
        $search = $_GET['search'] ?? '';
        
        $where = "1=1"; $params = [];
        if($search) { $where .= " AND (reference_no LIKE ? OR s.name LIKE ?)"; $params[]="%$search%"; $params[]="%$search%"; }

        $sql = "SELECT bp.*, s.name as supplier_name, fa.name as account_name 
                FROM bill_payments bp 
                LEFT JOIN suppliers s ON bp.supplier_id = s.id 
                LEFT JOIN financial_accounts fa ON bp.financial_account_id = fa.id 
                WHERE $where ORDER BY bp.date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        $pageTitle = "Bill Payments";
        $childView = ROOT_PATH . '/app/views/expenses/bill_payments/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $accounts = $db->query("SELECT * FROM financial_accounts ORDER BY type DESC, name")->fetchAll();
        
        $openBills = [];
        if (isset($_GET['supplier_id'])) {
            $stmt = $db->prepare("SELECT * FROM bills WHERE supplier_id = ? AND status != 'paid' ORDER BY due_date ASC");
            $stmt->execute([$_GET['supplier_id']]);
            $openBills = $stmt->fetchAll();
        }

        $pageTitle = "New Bill Payment";
        $childView = ROOT_PATH . '/app/views/expenses/bill_payments/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();
                $totalPaid = floatval($_POST['total_paid']);
                $accId = $_POST['financial_account_id'];

                // 1. Payment Header
                $stmt = $db->prepare("INSERT INTO bill_payments (company_id, supplier_id, financial_account_id, payment_method, reference_no, date, total_paid) VALUES (1, ?, ?, ?, ?, ?, ?)");
                $stmt->execute([$_POST['supplier_id'], $accId, $_POST['payment_method'], $_POST['reference_no'], $_POST['date'], $totalPaid]);
                $payId = $db->lastInsertId();

                // 2. Deduct Funds
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$totalPaid, $accId]);

                // 3. Allocate
                $allocs = json_decode($_POST['allocations_json'], true);
                $allocStmt = $db->prepare("INSERT INTO bill_payment_allocations (bill_payment_id, bill_id, amount_applied) VALUES (?, ?, ?)");
                $updateBill = $db->prepare("UPDATE bills SET amount_paid = amount_paid + ?, status = CASE WHEN (amount_paid + ?) >= total_amount THEN 'paid' ELSE 'partial' END WHERE id = ?");

                foreach ($allocs as $a) {
                    $amt = floatval($a['amount']);
                    if($amt > 0) {
                        $allocStmt->execute([$payId, $a['bill_id'], $amt]);
                        $updateBill->execute([$amt, $amt, $a['bill_id']]);
                    }
                }
                $db->commit();
                header("Location: /expenses/bill-payments");
            } catch (Exception $e) { $db->rollBack(); die($e->getMessage()); }
        }
    }
}