<?php
class DailyExpenseController {

    public function index() {
        $db = Database::getInstance();
        
        // --- 1. GET PARAMETERS ---
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $categoryId = $_GET['category'] ?? '';
        
        // Pagination Settings
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 lines
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        // Base: Credit transactions on Cash accounts
        $whereSql = "fa.type = 'cash' AND t.type = 'credit'";
        $params = [];

        // Apply Filters
        if ($search) {
            $whereSql .= " AND (t.description LIKE ? OR t.reference_no LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) {
            $whereSql .= " AND t.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $whereSql .= " AND t.date <= ?";
            $params[] = $toDate;
        }
        if ($categoryId) {
            $whereSql .= " AND t.contra_account_id = ?";
            $params[] = $categoryId;
        }

        // --- 3. COUNT TOTAL (For Pagination) ---
        $countSql = "SELECT COUNT(*) as total 
                     FROM account_transactions t
                     JOIN financial_accounts fa ON t.financial_account_id = fa.id
                     WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // --- 4. FETCH DATA ---
        $sql = "SELECT t.*, fa.name as source_account, a.name as category_name 
                FROM account_transactions t
                JOIN financial_accounts fa ON t.financial_account_id = fa.id
                LEFT JOIN accounts a ON t.contra_account_id = a.id
                WHERE $whereSql
                ORDER BY t.date DESC, t.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        // --- 5. DROPDOWNS ---
        $allFinancialAccounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();
        $categories = $db->query("SELECT * FROM accounts WHERE type IN ('expense', 'asset', 'liability') ORDER BY code ASC")->fetchAll();
        $cashAccounts = $db->query("SELECT * FROM financial_accounts WHERE type='cash'")->fetchAll(); // For the Add Modal

        // Pass filters back to view
        $filters = [
            'search' => $search, 
            'from' => $fromDate, 
            'to' => $toDate, 
            'category' => $categoryId,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ];

        $pageTitle = "Daily Expenses";
        $childView = ROOT_PATH . '/app/views/expenses/daily/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $accId = $_POST['financial_account_id'];
            $date = $_POST['date'];
            $desc = $_POST['description'];
            $catId = $_POST['category_id'];
            
            // LOGIC CHANGE:
            // If "Change Later" is checked, we deduct the TENDERED amount (e.g. 1000) now.
            // If not, we deduct the ACTUAL amount.
            $isPending = isset($_POST['is_pending_change']) ? 1 : 0;
            $tendered = floatval($_POST['tendered_amount']);
            $actual = floatval($_POST['actual_amount']);

            $amountToDeduct = $isPending ? $tendered : $actual;

            $sql = "INSERT INTO account_transactions 
                    (financial_account_id, date, type, amount, description, contra_account_id, is_pending_change, tendered_amount) 
                    VALUES (?, ?, 'credit', ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$accId, $date, $amountToDeduct, $desc, $catId, $isPending, $tendered]);

            // Update Balance
            $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?");
            $update->execute([$amountToDeduct, $accId]);

            header("Location: /expenses/daily");
        }
    }

    // NEW: Settle the Change
    public function settle() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $id = $_POST['id'];
            $actualExpense = floatval($_POST['final_actual_amount']);
            
            // 1. Get Original Transaction
            $stmt = $db->prepare("SELECT * FROM account_transactions WHERE id = ?");
            $stmt->execute([$id]);
            $txn = $stmt->fetch();

            if ($txn && $txn['is_pending_change']) {
                $tendered = floatval($txn['tendered_amount']);
                $changeReturned = $tendered - $actualExpense;

                // 2. Update Transaction to Final Amount and remove Pending status
                $updateTxn = $db->prepare("UPDATE account_transactions SET amount = ?, is_pending_change = 0 WHERE id = ?");
                $updateTxn->execute([$actualExpense, $id]);

                // 3. Restore the Change to the Cash Balance
                // (We originally deducted 1000. Now we say it's only 300. So we add back 700).
                $updateBal = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?");
                $updateBal->execute([$changeReturned, $txn['financial_account_id']]);
            }

            header("Location: /expenses/daily");
        }
    }
}