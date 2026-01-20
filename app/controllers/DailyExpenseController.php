<?php
class DailyExpenseController {

    public function index() {
        $db = Database::getInstance();
        
        // --- 1. GET PARAMETERS ---
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $categoryId = $_GET['category'] ?? '';
        
        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        $whereSql = "t.type = 'credit'"; // Expenses are credits (money out)
        $params = [];

        // Apply Filters
        if ($search) {
            $whereSql .= " AND (t.description LIKE ? OR t.reference_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
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

        // --- 3. COUNT ---
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM account_transactions t WHERE $whereSql");
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
        // Fetch Expense/Asset/Liability accounts for the dropdown
        $categories = $db->query("SELECT * FROM accounts WHERE type IN ('expense', 'asset', 'liability', 'cost of goods sold') ORDER BY code ASC")->fetchAll();

        $filters = [
            'search' => $search, 'from' => $fromDate, 'to' => $toDate, 'category' => $categoryId,
            'limit' => $limit, 'page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Daily Expenses";
        $childView = ROOT_PATH . '/app/views/expenses/daily/index.php'; // Verify this path matches your folder structure
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE EXPENSE (With Journal) ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $accId = $_POST['financial_account_id']; // Source (Cash/Bank)
                $date = $_POST['date'];
                $desc = $_POST['description'];
                $catId = $_POST['category_id']; // This IS the Expense Account ID
                
                $isPending = isset($_POST['is_pending_change']) ? 1 : 0;
                $tendered = floatval($_POST['tendered_amount']);
                $actual = floatval($_POST['actual_amount']);

                // If "Change Later", we deduct the full TENDERED amount now.
                $amountToDeduct = $isPending ? $tendered : $actual;

                // 1. Save Transaction
                $sql = "INSERT INTO account_transactions 
                        (financial_account_id, date, type, amount, description, contra_account_id, is_pending_change, tendered_amount) 
                        VALUES (?, ?, 'credit', ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$accId, $date, $amountToDeduct, $desc, $catId, $isPending, $tendered]);
                $transId = $db->lastInsertId();

                // 2. Update Source Balance
                $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?");
                $update->execute([$amountToDeduct, $accId]);

                // 3. AUTOMATIC JOURNAL ENTRY
                // Get Source GL Account (e.g., Cash on Hand - 1000)
                $sourceInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = $accId")->fetch();
                $sourceGLId = $sourceInfo['account_id'];
                
                // Expense GL Account is just $catId
                
                if ($sourceGLId && $catId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';

                    $lines = [
                        [
                            'account_id' => $catId,       // Debit: Expense (e.g. Market Expense)
                            'desc' => $desc,
                            'debit' => $amountToDeduct,
                            'credit' => 0
                        ],
                        [
                            'account_id' => $sourceGLId,  // Credit: Asset (e.g. Cash)
                            'desc' => $desc,
                            'debit' => 0,
                            'credit' => $amountToDeduct
                        ]
                    ];

                    JournalController::post(
                        $date, 
                        'EXP-'.$transId, 
                        $desc, 
                        'daily_expense', 
                        $transId, 
                        $lines
                    );
                }

                $db->commit();
                header("Location: /expenses/daily");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error saving expense: " . $e->getMessage());
            }
        }
    }

    // --- SETTLE CHANGE (With Journal Adjustment) ---
    public function settle() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $id = $_POST['id'];
                $actualExpense = floatval($_POST['final_actual_amount']);
                
                // 1. Get Original Transaction
                $stmt = $db->prepare("SELECT * FROM account_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $txn = $stmt->fetch();

                if ($txn && $txn['is_pending_change']) {
                    $tendered = floatval($txn['tendered_amount']);
                    $changeReturned = $tendered - $actualExpense;

                    // 2. Update Transaction to Final Amount
                    $updateTxn = $db->prepare("UPDATE account_transactions SET amount = ?, is_pending_change = 0 WHERE id = ?");
                    $updateTxn->execute([$actualExpense, $id]);

                    // 3. Restore the Change to the Cash Balance
                    // We originally deducted full 1000. Now we add back 200 change.
                    if ($changeReturned > 0) {
                        $updateBal = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?");
                        $updateBal->execute([$changeReturned, $txn['financial_account_id']]);

                        // 4. JOURNAL ADJUSTMENT (Return of Cash)
                        // We need to Reverse the expense for the change amount
                        // Debit: Cash (Money comes back)
                        // Credit: Expense (Reduce the expense cost)
                        
                        $sourceInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = {$txn['financial_account_id']}")->fetch();
                        $sourceGLId = $sourceInfo['account_id'];
                        $expenseGLId = $txn['contra_account_id'];

                        if ($sourceGLId && $expenseGLId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                            require_once ROOT_PATH . '/app/controllers/JournalController.php';

                            $lines = [
                                [
                                    'account_id' => $sourceGLId,  // Debit: Cash (Change returned)
                                    'desc' => "Change Returned: " . $txn['description'],
                                    'debit' => $changeReturned,
                                    'credit' => 0
                                ],
                                [
                                    'account_id' => $expenseGLId, // Credit: Expense (Reducing cost)
                                    'desc' => "Change Returned: " . $txn['description'],
                                    'debit' => 0,
                                    'credit' => $changeReturned
                                ]
                            ];

                            JournalController::post(
                                date('Y-m-d'), 
                                'RET-'.$id, 
                                "Change Return: " . $txn['description'], 
                                'expense_settle', 
                                $id, 
                                $lines
                            );
                        }
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

    // --- VERIFY (APPROVE) TRANSACTION ---
    public function verify() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            
            // Mark as Verified (Approved)
            $db->prepare("UPDATE account_transactions SET verified_at = NOW() WHERE id = ?")
               ->execute([$id]);
            
            header("Location: /expenses/daily");
        }
    }

    // --- VOID (REVERSE) TRANSACTION ---
    public function void() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $id = $_POST['id'];
                $reason = $_POST['void_reason'];

                // 1. Fetch Original Transaction
                $stmt = $db->prepare("SELECT * FROM account_transactions WHERE id = ?");
                $stmt->execute([$id]);
                $original = $stmt->fetch();

                if (!$original || $original['is_voided']) {
                    throw new Exception("Transaction not found or already voided.");
                }

                // 2. Mark Original as Voided
                $db->prepare("UPDATE account_transactions SET is_voided = 1 WHERE id = ?")->execute([$id]);

                // 3. RESTORE BALANCE (Reverse the money movement)
                // If original was Credit (Money Out), we Put Money In (Debit)
                $restoreAmount = $original['amount'];
                if ($original['type'] === 'credit') {
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                       ->execute([$restoreAmount, $original['financial_account_id']]);
                }

                // 4. CREATE REVERSING JOURNAL ENTRY
                // We need to swap the Debit and Credit accounts from the original entry.
                // Original: Debit Expense (Cat), Credit Asset (Source)
                // Void:     Debit Asset (Source), Credit Expense (Cat)
                
                $sourceInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = {$original['financial_account_id']}")->fetch();
                $sourceGLId = $sourceInfo['account_id']; // Asset
                $expenseGLId = $original['contra_account_id']; // Expense Category

                if ($sourceGLId && $expenseGLId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';

                    $lines = [
                        [
                            'account_id' => $sourceGLId,   // DEBIT: Asset (Put money back)
                            'desc' => "VOID: " . $original['description'],
                            'debit' => $restoreAmount,
                            'credit' => 0
                        ],
                        [
                            'account_id' => $expenseGLId,  // CREDIT: Expense (Cancel expense)
                            'desc' => "VOID: " . $original['description'],
                            'debit' => 0,
                            'credit' => $restoreAmount
                        ]
                    ];

                    JournalController::post(
                        date('Y-m-d'), 
                        'VOID-'.$id, 
                        "VOIDED: " . $original['description'] . " ($reason)", 
                        'void_expense', 
                        $id, 
                        $lines
                    );
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