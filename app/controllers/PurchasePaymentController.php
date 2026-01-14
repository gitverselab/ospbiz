<?php
class PurchasePaymentController {

    public function index() {
        $db = Database::getInstance();
        
        // --- 1. GET PARAMETERS ---
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $method = $_GET['method'] ?? ''; // Filter by Cash/Check/Transfer

        // Pagination Settings
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        $whereSql = "1=1"; 
        $params = [];

        // Apply Filters
        if ($search) {
            $whereSql .= " AND (pp.reference_no LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) {
            $whereSql .= " AND pp.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $whereSql .= " AND pp.date <= ?";
            $params[] = $toDate;
        }
        if ($method) {
            $whereSql .= " AND pp.payment_method = ?";
            $params[] = $method;
        }

        // --- 3. COUNT TOTAL (For Pagination) ---
        $countSql = "SELECT COUNT(*) as total 
                     FROM purchase_payments pp
                     LEFT JOIN suppliers s ON pp.supplier_id = s.id
                     WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // --- 4. FETCH DATA ---
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

        // Pass filters back to view
        $filters = [
            'search' => $search, 
            'from' => $fromDate, 
            'to' => $toDate, 
            'method' => $method,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
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
            // Fetch POs that are NOT fully paid
            $sql = "SELECT * FROM purchase_orders 
                    WHERE supplier_id = ? AND status != 'paid'
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

                $totalPaid = floatval($_POST['total_paid']);
                $accId = $_POST['financial_account_id'];

                // 1. Create Payment Header (The "Binder")
                $sql = "INSERT INTO purchase_payments (company_id, supplier_id, financial_account_id, payment_method, reference_no, date, total_paid) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    1, 
                    $_POST['supplier_id'], 
                    $accId, 
                    $_POST['payment_method'], 
                    $_POST['reference_no'], 
                    $_POST['date'], 
                    $totalPaid
                ]);
                $paymentId = $db->lastInsertId();

                // 2. Deduct Money from Bank/Cash
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$totalPaid, $accId]);

                // 3. Allocate Payment to Specific POs
                $allocations = json_decode($_POST['allocations_json'], true);
                
                $allocSql = "INSERT INTO purchase_payment_allocations (purchase_payment_id, purchase_order_id, amount_applied) VALUES (?, ?, ?)";
                $allocStmt = $db->prepare($allocSql);
                
                // SAFETY FIX: Explicitly calculate (amount_paid + ?) in the condition to ensure accuracy
                $updatePOSql = "UPDATE purchase_orders SET amount_paid = amount_paid + ?, status = CASE WHEN (amount_paid + ?) >= total_amount THEN 'paid' ELSE 'partial' END WHERE id = ?";
                $updatePOStmt = $db->prepare($updatePOSql);

                foreach ($allocations as $alloc) {
                    $amount = floatval($alloc['amount']);
                    if ($amount > 0) {
                        // Link Payment to PO
                        $allocStmt->execute([$paymentId, $alloc['po_id'], $amount]);
                        
                        // Update PO Status (Passing amount twice: once for math, once for check)
                        $updatePOStmt->execute([$amount, $amount, $alloc['po_id']]);
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