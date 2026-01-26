<?php
class PurchasePaymentController {

    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $method = $_GET['method'] ?? '';

        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $whereSql = "1=1"; 
        $params = [];

        if ($search) {
            $whereSql .= " AND (pp.reference_no LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) { $whereSql .= " AND pp.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $whereSql .= " AND pp.date <= ?"; $params[] = $toDate; }
        if ($method) { $whereSql .= " AND pp.payment_method = ?"; $params[] = $method; }

        $countSql = "SELECT COUNT(*) as total FROM purchase_payments pp LEFT JOIN suppliers s ON pp.supplier_id = s.id WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT pp.*, s.name as supplier_name, fa.name as account_name 
                FROM purchase_payments pp
                LEFT JOIN suppliers s ON pp.supplier_id = s.id
                LEFT JOIN financial_accounts fa ON pp.financial_account_id = fa.id
                WHERE $whereSql
                ORDER BY pp.date DESC, pp.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        $filters = [
            'search' => $search, 'from' => $fromDate, 'to' => $toDate, 'method' => $method,
            'limit' => $limit, 'page' => $page, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Purchase Payments";
        $childView = ROOT_PATH . '/app/views/expenses/payments/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();
        $accounts = $db->query("SELECT * FROM financial_accounts ORDER BY type DESC, name ASC")->fetchAll();

        $openPOs = [];
        if (isset($_GET['supplier_id']) && !empty($_GET['supplier_id'])) {
            $supId = $_GET['supplier_id'];
            $sql = "SELECT * FROM purchase_orders WHERE supplier_id = ? AND status != 'paid' ORDER BY date ASC";
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

                $totalPaid = floatval($_POST['total_paid']);
                $accId = $_POST['financial_account_id'];
                $supplierId = $_POST['supplier_id'];
                $method = $_POST['payment_method']; 
                $refNo = $_POST['reference_no'];
                $date = $_POST['date'];
                
                // Get Allocations to build Memo
                $allocations = json_decode($_POST['allocations_json'], true);
                $poIds = array_column($allocations, 'po_id');
                
                // Build Dynamic Memo (e.g., "Payment for PO-101, PO-102")
                $memo = "Payment for POs";
                if (!empty($poIds)) {
                    $placeholders = str_repeat('?,', count($poIds) - 1) . '?';
                    $stmtPo = $db->prepare("SELECT po_number FROM purchase_orders WHERE id IN ($placeholders)");
                    $stmtPo->execute($poIds);
                    $poNumbers = $stmtPo->fetchAll(PDO::FETCH_COLUMN);
                    if ($poNumbers) {
                        $memo = "Payment for " . implode(', ', $poNumbers);
                    }
                }

                // 1. Create Payment Header
                $sql = "INSERT INTO purchase_payments (company_id, supplier_id, financial_account_id, payment_method, reference_no, date, total_paid) VALUES (1, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$supplierId, $accId, $method, $refNo, $date, $totalPaid]);
                $paymentId = $db->lastInsertId();

                // 2. HANDLE FUNDS
                if ($method === 'check') {
                    // Check Logic: Issued status, no deduction yet
                    $sup = $db->query("SELECT name FROM suppliers WHERE id = $supplierId")->fetch();
                    $payee = $sup['name'] ?? 'Supplier';

                    // Use the dynamic $memo here
                    $chkSql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                               VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'purchase_payment')";
                    $db->prepare($chkSql)->execute([$accId, $refNo, $payee, $date, $totalPaid, $memo]);

                } else {
                    // Cash/Transfer Logic: Immediate deduction
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                       ->execute([$totalPaid, $accId]);

                    $desc = ucfirst($method) . " Payment to Supplier (" . implode(', ', $poNumbers ?? []) . ")";
                    $transSql = "INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) 
                                 VALUES (?, ?, 'credit', ?, ?, ?)";
                    $db->prepare($transSql)->execute([$accId, $date, $totalPaid, $desc, $refNo]);
                }

                // 3. Allocate
                $allocStmt = $db->prepare("INSERT INTO purchase_payment_allocations (purchase_payment_id, purchase_order_id, amount_applied) VALUES (?, ?, ?)");
                $updatePO = $db->prepare("UPDATE purchase_orders SET amount_paid = amount_paid + ?, status = CASE WHEN (amount_paid + ?) >= total_amount THEN 'paid' ELSE 'partial' END WHERE id = ?");

                foreach ($allocations as $alloc) {
                    $amount = floatval($alloc['amount']);
                    if ($amount > 0) {
                        $allocStmt->execute([$paymentId, $alloc['po_id'], $amount]);
                        $updatePO->execute([$amount, $amount, $alloc['po_id']]);
                    }
                }

                $db->commit();
                header("Location: /expenses/payments");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error: " . $e->getMessage());
            }
        }
    }
}