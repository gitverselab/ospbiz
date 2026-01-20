<?php
class DailyExpenseController {

    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $categoryId = $_GET['category'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        $whereSql = "t.type = 'credit'"; 
        $params = [];

        if ($search) {
            $whereSql .= " AND (t.description LIKE ? OR t.reference_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($fromDate) { $whereSql .= " AND t.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $whereSql .= " AND t.date <= ?"; $params[] = $toDate; }
        if ($categoryId) { $whereSql .= " AND t.contra_account_id = ?"; $params[] = $categoryId; }

        // --- 3. COUNT ---
        // FIX: Use LEFT JOIN so we count expenses that aren't linked to a bank yet (Issued Checks)
        $countSql = "SELECT COUNT(*) as total 
                     FROM account_transactions t 
                     LEFT JOIN financial_accounts fa ON t.financial_account_id = fa.id 
                     WHERE $whereSql";
        
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // --- 4. FETCH DATA ---
        // FIX: Use LEFT JOIN here too
        $sql = "SELECT t.*, fa.name as source_account, a.name as category_name 
                FROM account_transactions t
                LEFT JOIN financial_accounts fa ON t.financial_account_id = fa.id
                LEFT JOIN accounts a ON t.contra_account_id = a.id
                WHERE $whereSql
                ORDER BY t.date DESC, t.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $expenses = $stmt->fetchAll();

        $allFinancialAccounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();
        $categories = $db->query("SELECT * FROM accounts WHERE type IN ('expense', 'asset', 'liability', 'cost of goods sold') ORDER BY code ASC")->fetchAll();

        $filters = [
            'search' => $search, 'from' => $fromDate, 'to' => $toDate, 'category' => $categoryId,
            'limit' => $limit, 'page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Daily Expenses";
        $childView = ROOT_PATH . '/app/views/expenses/daily/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE EXPENSE ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $accId = $_POST['financial_account_id'];
                $date = $_POST['date'];
                $desc = $_POST['description'];
                $catId = $_POST['category_id'];
                
                $isPending = isset($_POST['is_pending_change']) ? 1 : 0;
                $tendered = floatval($_POST['tendered_amount']);
                $amount = floatval($_POST['amount']); 
                $finalAmount = ($isPending && $tendered > 0) ? $tendered : $amount;

                $payType = $_POST['payment_source_type'] ?? 'cash';
                $payMethod = $_POST['payment_method'] ?? '';
                $isCheck = ($payType === 'bank' && $payMethod === 'check');

                $refNo = ''; // To store check number

                // 1. HANDLE CHECK CREATION
                if ($isCheck) {
                    $checkNum = $_POST['check_number'];
                    $payee = $_POST['payee_name'];
                    $refNo = $checkNum; // Save for linking later

                    $chkSql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                               VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'expense')";
                    $db->prepare($chkSql)->execute([$accId, $checkNum, $payee, $date, $finalAmount, $desc]);

                    $desc .= " (Check #$checkNum)";
                }

                // 2. RECORD EXPENSE TRANSACTION
                // FIX: If it is a check, set financial_account_id to NULL. 
                // This prevents it from showing in the Bank Passbook until cleared.
                $txnAccId = $isCheck ? null : $accId;

                $sql = "INSERT INTO account_transactions 
                        (financial_account_id, date, type, amount, description, reference_no, contra_account_id, is_pending_change, tendered_amount) 
                        VALUES (?, ?, 'credit', ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$txnAccId, $date, $finalAmount, $desc, $refNo, $catId, $isPending, $tendered]);
                $transId = $db->lastInsertId();

                // 3. BALANCE DEDUCTION (Real-time Rule)
                // Only deduct immediately if NOT a check.
                if (!$isCheck) {
                    $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?");
                    $update->execute([$finalAmount, $accId]);
                }

                // 4. JOURNAL ENTRY
                $bankGLId = 0;
                if (!$isCheck) {
                    $sourceInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = $accId")->fetch();
                    $bankGLId = $sourceInfo['account_id'];
                } else {
                    // For Checks, credit "Accounts Payable" (Liability)
                    $ap = $db->query("SELECT id FROM accounts WHERE type = 'Liability' LIMIT 1")->fetch();
                    $bankGLId = $ap ? $ap['id'] : 0;
                }

                if ($bankGLId && $catId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';
                    $lines = [
                        ['account_id' => $catId, 'desc' => $desc, 'debit' => $finalAmount, 'credit' => 0],
                        ['account_id' => $bankGLId, 'desc' => $desc, 'debit' => 0, 'credit' => $finalAmount]
                    ];
                    JournalController::post($date, 'EXP-'.$transId, $desc, 'daily_expense', $transId, $lines);
                }

                $db->commit();
                header("Location: /expenses/daily");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error saving expense: " . $e->getMessage());
            }
        }
    }
    
    public function settle() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();
                $id = $_POST['id'];
                $actualExpense = floatval($_POST['final_actual_amount']);
                $stmt = $db->prepare("SELECT * FROM account_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $txn = $stmt->fetch();
                if ($txn && $txn['is_pending_change']) {
                    $tendered = floatval($txn['tendered_amount']);
                    $changeReturned = $tendered - $actualExpense;
                    $updateTxn = $db->prepare("UPDATE account_transactions SET amount = ?, is_pending_change = 0 WHERE id = ?");
                    $updateTxn->execute([$actualExpense, $id]);
                    if ($changeReturned > 0) {
                        $updateBal = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?");
                        $updateBal->execute([$changeReturned, $txn['financial_account_id']]);
                    }
                }
                $db->commit();
                header("Location: /expenses/daily");
            } catch (Exception $e) {
                $db->rollBack();
                die("Error settling expense: " . $e->getMessage());
            }
        }
    }
    
    public function verify() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $db->prepare("UPDATE account_transactions SET verified_at = NOW() WHERE id = ?")->execute([$id]);
            header("Location: /expenses/daily");
        }
    }
    
    public function void() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();
                $id = $_POST['id'];
                $reason = $_POST['void_reason'];
                $stmt = $db->prepare("SELECT * FROM account_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $original = $stmt->fetch();
                if (!$original || $original['is_voided']) throw new Exception("Transaction not found or already voided.");
                $db->prepare("UPDATE account_transactions SET is_voided = 1 WHERE id = ?")->execute([$id]);
                $restoreAmount = $original['amount'];
                if ($original['type'] === 'credit' && $original['financial_account_id'] != null) {
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                       ->execute([$restoreAmount, $original['financial_account_id']]);
                }
                $db->commit();
                header("Location: /expenses/daily");
            } catch (Exception $e) {
                $db->rollBack();
                die("Error voiding transaction: " . $e->getMessage());
            }
        }
    }
}