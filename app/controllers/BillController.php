<?php
class BillController {

    public function index() {
        $db = Database::getInstance();
        
        // --- 1. GET PARAMETERS ---
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $status = $_GET['status'] ?? ''; // Added Status Filter
        
        // Pagination Settings
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        $whereSql = "1=1"; 
        $params = [];

        // Apply Filters
        if ($search) {
            $whereSql .= " AND (b.bill_number LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) {
            $whereSql .= " AND b.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $whereSql .= " AND b.date <= ?";
            $params[] = $toDate;
        }
        if ($status) {
            $whereSql .= " AND b.status = ?";
            $params[] = $status;
        }

        // --- 3. COUNT TOTAL (For Pagination) ---
        $countSql = "SELECT COUNT(*) as total 
                     FROM bills b
                     LEFT JOIN suppliers s ON b.supplier_id = s.id
                     WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // --- 4. FETCH DATA ---
        $sql = "SELECT b.*, s.name as supplier_name, (b.total_amount - b.amount_paid) as balance 
                FROM bills b
                LEFT JOIN suppliers s ON b.supplier_id = s.id
                WHERE $whereSql
                ORDER BY b.date DESC, b.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $bills = $stmt->fetchAll();

        // Pass filters back to view
        $filters = [
            'search' => $search, 
            'from' => $fromDate, 
            'to' => $toDate, 
            'status' => $status,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ];

        $pageTitle = "Bills (Accounts Payable)";
        $childView = ROOT_PATH . '/app/views/expenses/bills/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE BILL FORM ---
    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $accounts = $db->query("SELECT * FROM accounts WHERE type IN ('expense','asset') ORDER BY code")->fetchAll();
        
        $pageTitle = "Create Bill";
        $childView = ROOT_PATH . '/app/views/expenses/bills/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE BILL ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $lines = json_decode($_POST['lines_json'], true);
            $total = array_sum(array_column($lines, 'amount'));

            $db->beginTransaction();
            $stmt = $db->prepare("INSERT INTO bills (company_id, supplier_id, bill_number, date, due_date, status, total_amount) VALUES (1, ?, ?, ?, ?, 'open', ?)");
            $stmt->execute([$_POST['supplier_id'], $_POST['bill_number'], $_POST['date'], $_POST['due_date'], $total]);
            $billId = $db->lastInsertId();

            $lineStmt = $db->prepare("INSERT INTO bill_lines (bill_id, expense_account_id, description, amount) VALUES (?, ?, ?, ?)");
            foreach ($lines as $line) {
                $lineStmt->execute([$billId, $line['account_id'], $line['description'], $line['amount']]);
            }
            $db->commit();
            header("Location: /expenses/bills");
        }
    }

    // --- RECURRING BILLS LIST ---
    public function recurringIndex() {
        $db = Database::getInstance();
        
        // 1. GET PARAMETERS
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. BUILD QUERY
        $whereSql = "1=1";
        $params = [];
        
        if ($search) {
            $whereSql .= " AND (r.profile_name LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }

        // 3. PAGINATION COUNTS
        $countSql = "SELECT COUNT(*) as total FROM recurring_bills r LEFT JOIN suppliers s ON r.supplier_id = s.id WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. FETCH DATA
        $sql = "SELECT r.*, s.name as supplier_name, a.name as expense_account 
                FROM recurring_bills r 
                LEFT JOIN suppliers s ON r.supplier_id = s.id 
                LEFT JOIN accounts a ON r.expense_account_id = a.id
                WHERE $whereSql
                ORDER BY r.next_due_date ASC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $recurrings = $stmt->fetchAll();

        // 5. DROPDOWNS (For the Create Modal)
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name")->fetchAll();
        $accounts = $db->query("SELECT * FROM accounts WHERE type IN ('expense','asset') ORDER BY code")->fetchAll();

        $filters = [
            'search' => $search,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ];

        $pageTitle = "Recurring Bills";
        $childView = ROOT_PATH . '/app/views/expenses/bills/recurring.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE NEW RECURRING PROFILE ---
    public function storeRecurring() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $sql = "INSERT INTO recurring_bills (company_id, supplier_id, profile_name, frequency, next_due_date, amount, expense_account_id, description) VALUES (1, ?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $_POST['supplier_id'], $_POST['profile_name'], $_POST['frequency'], 
                $_POST['next_due_date'], $_POST['amount'], $_POST['expense_account_id'], $_POST['description']
            ]);
            header("Location: /expenses/recurring");
        }
    }

    // --- GENERATE BILL FROM RECURRING ---
    public function generate() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            
            // 1. Get Template
            $tpl = $db->query("SELECT * FROM recurring_bills WHERE id=$id")->fetch();

            // 2. Create Real Bill
            $db->beginTransaction();
            $billNum = "REC-" . date('ymd') . "-" . $id; // Auto-generate Bill #
            
            $stmt = $db->prepare("INSERT INTO bills (company_id, supplier_id, bill_number, date, due_date, status, total_amount) VALUES (1, ?, ?, CURDATE(), ?, 'open', ?)");
            $stmt->execute([$tpl['supplier_id'], $billNum, $tpl['next_due_date'], $tpl['amount']]);
            $billId = $db->lastInsertId();

            // 3. Create Line Item
            $db->prepare("INSERT INTO bill_lines (bill_id, expense_account_id, description, amount) VALUES (?, ?, ?, ?)")
               ->execute([$billId, $tpl['expense_account_id'], $tpl['description'], $tpl['amount']]);

            // 4. Update Next Due Date (Advance by 1 month)
            $db->prepare("UPDATE recurring_bills SET next_due_date = DATE_ADD(next_due_date, INTERVAL 1 MONTH) WHERE id = ?")
               ->execute([$id]);

            $db->commit();
            header("Location: /expenses/bills"); // Go to active bills list
        }
    }
}