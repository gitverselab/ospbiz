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
            $params[] = "%$search%"; $params[] = "%$search%";
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

        $filters = compact('search', 'fromDate', 'toDate', 'method', 'limit', 'page', 'totalPages', 'totalRecords');

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
                $method = $_POST['payment_method'];
                $ref = $_POST['reference_no'];
                $date = $_POST['date'];
                $supplierId = $_POST['supplier_id'];

                $isCheck = ($method === 'check');

                // 1. Handle Check Logic (If Check)
                if ($isCheck) {
                    // Get Supplier Name for Payee
                    $sup = $db->query("SELECT name FROM suppliers WHERE id = $supplierId")->fetch();
                    $payee = $sup['name'];

                    // Create Check Record (Status: Issued)
                    $chkSql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                               VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'payment')";
                    $db->prepare($chkSql)->execute([$accId, $ref, $payee, $date, $totalPaid, "Payment to $payee"]);
                }

                // 2. Create Payment Header
                // FIX: If Check, we can still link the account ID for reference, OR keep it null if you want strictly no link.
                // Usually, we keep the link but don't deduct balance.
                $sql = "INSERT INTO purchase_payments (company_id, supplier_id, financial_account_id, payment_method, reference_no, date, total_paid) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([1, $supplierId, $accId, $method, $ref, $date, $totalPaid]);
                $paymentId = $db->lastInsertId();

                // 3. Deduct Money (ONLY IF NOT CHECK)
                if (!$isCheck) {
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                       ->execute([$totalPaid, $accId]);
                    
                    // Log Transaction
                    $desc = "Purchase Payment ($method) - Ref: $ref";
                    $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")
                       ->execute([$accId, $date, $totalPaid, $desc, $ref]);
                }

                // 4. Allocate Payment to POs
                $allocations = json_decode($_POST['allocations_json'], true);
                $allocSql = "INSERT INTO purchase_payment_allocations (purchase_payment_id, purchase_order_id, amount_applied) VALUES (?, ?, ?)";
                $allocStmt = $db->prepare($allocSql);
                
                $updatePOSql = "UPDATE purchase_orders SET amount_paid = amount_paid + ?, status = CASE WHEN (amount_paid + ?) >= total_amount THEN 'paid' ELSE 'partial' END WHERE id = ?";
                $updatePOStmt = $db->prepare($updatePOSql);

                foreach ($allocations as $alloc) {
                    $amount = floatval($alloc['amount']);
                    if ($amount > 0) {
                        $allocStmt->execute([$paymentId, $alloc['po_id'], $amount]);
                        $updatePOStmt->execute([$amount, $amount, $alloc['po_id']]);
                    }
                }

                // 5. Journal Entry
                if (file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';
                    
                    // Get Bank GL Account
                    $bankInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = $accId")->fetch();
                    $bankGLId = $bankInfo['account_id'];

                    // Debit Accounts Payable (2000)
                    // Credit Bank (Asset) OR Credit Checks Payable (Liability)
                    
                    $creditAccount = $bankGLId;
                    
                    // Ideally, if check, credit a clearing account. 
                    // If you don't have one, you can Credit Bank GL but since passbook isn't updated, 
                    // your reconciliation will show the difference (Outstanding Check).
                    // Standard practice: Credit Bank GL now. Reconciliation handles the timing difference.
                    
                    $lines = [
                        ['code' => '2000', 'desc' => "Payment to Supplier - $ref", 'debit' => $totalPaid, 'credit' => 0], // Dr AP
                        ['account_id' => $creditAccount, 'desc' => "Payment Out ($method)", 'debit' => 0, 'credit' => $totalPaid] // Cr Bank
                    ];
                    
                    JournalController::post($date, $ref, "Purchase Payment", 'purchase_payment', $paymentId, $lines);
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