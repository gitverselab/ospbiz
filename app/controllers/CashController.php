<?php
class CashController {
    
    // --- LIST CASH ACCOUNTS ---
    public function index() {
        $db = Database::getInstance();
        
        // 1. Fetch Cash Accounts linked with their GL Account info
        $sql = "SELECT fa.*, a.code as gl_code, a.name as gl_name 
                FROM financial_accounts fa 
                LEFT JOIN accounts a ON fa.account_id = a.id 
                WHERE fa.type = 'cash' 
                ORDER BY fa.name";
        $accounts = $db->query($sql)->fetchAll();
        
        // 2. Fetch Asset Accounts for the "Add Fund" dropdown
        $assetAccounts = $db->query("SELECT * FROM accounts WHERE type = 'Asset' ORDER BY code")->fetchAll();
        
        $pageTitle = "Cash on Hand Management";
        // Note: Keeping your existing view path
        $childView = ROOT_PATH . '/app/views/cash/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- VIEW SPECIFIC LEDGER ---
    public function show() {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) { header("Location: /bank/cash-on-hand"); exit; }

        $stmt = $db->prepare("SELECT t.*, a.name as contra_account_name 
                              FROM account_transactions t 
                              LEFT JOIN accounts a ON t.contra_account_id = a.id 
                              WHERE t.financial_account_id = ? 
                              ORDER BY t.date DESC");
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll();

        // COA for manual transactions dropdown
        $coa = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

        $pageTitle = $account['name'];
        $childView = ROOT_PATH . '/app/views/cash/show.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE NEW CASH FUND (With Journal) ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $name = $_POST['name'];
                $holder = $_POST['account_holder'] ?? ''; 
                $balance = floatval($_POST['opening_balance'] ?? 0);
                $glAccountId = !empty($_POST['account_id']) ? $_POST['account_id'] : NULL;

                // 1. Insert Cash Account
                $sql = "INSERT INTO financial_accounts (company_id, type, name, account_holder, current_balance, opening_balance, account_id) VALUES (?, 'cash', ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([1, $name, $holder, $balance, $balance, $glAccountId]);
                $cashId = $db->lastInsertId();

                // 2. AUTOMATIC JOURNAL ENTRY (Opening Balance)
                if ($balance > 0 && $glAccountId) {
                    $equityInfo = $db->query("SELECT id FROM accounts WHERE type = 'Equity' LIMIT 1")->fetch();
                    $equityId = $equityInfo ? $equityInfo['id'] : 0;

                    if ($equityId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                        require_once ROOT_PATH . '/app/controllers/JournalController.php';

                        $lines = [
                            ['account_id' => $glAccountId, 'desc' => "Opening Cash Fund", 'debit' => $balance, 'credit' => 0],
                            ['account_id' => $equityId, 'desc' => "Opening Cash Fund", 'debit' => 0, 'credit' => $balance]
                        ];

                        JournalController::post(
                            date('Y-m-d'), 
                            'CSH-OP-'.$cashId, 
                            "Opening Fund: $name", 
                            'cash_setup', 
                            $cashId, 
                            $lines
                        );
                    }
                }

                $db->commit();
                header("Location: /bank/cash-on-hand");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error creating cash account: " . $e->getMessage());
            }
        }
    }
    
    // --- RECORD TRANSACTION (With Journal) ---
    public function storeTransaction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $accId = $_POST['financial_account_id'];
                $amount = floatval($_POST['amount']);
                $type = $_POST['type']; // 'debit' (In) or 'credit' (Out)
                $date = $_POST['date'];
                $desc = $_POST['description'];
                $ref = $_POST['reference_no'];
                $contraId = $_POST['contra_account_id'];

                // 1. Insert Transaction
                $sql = "INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$accId, $date, $type, $amount, $desc, $ref, $contraId]);
                $transId = $db->lastInsertId();

                // 2. Update Balance
                $operator = ($type === 'debit') ? '+' : '-';
                $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance $operator ? WHERE id = ?");
                $update->execute([$amount, $accId]);

                // 3. AUTOMATIC JOURNAL ENTRY
                // Get the GL Account linked to this Cash Fund
                $cashInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = $accId")->fetch();
                $cashGLId = $cashInfo['account_id'];

                if ($cashGLId && $contraId && file_exists(ROOT_PATH . '/app/controllers/JournalController.php')) {
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';
                    
                    if ($type === 'debit') {
                        // Cash IN (Debit Cash, Credit Source/Income)
                        $lines = [
                            ['account_id' => $cashGLId, 'desc' => $desc, 'debit' => $amount, 'credit' => 0],
                            ['account_id' => $contraId, 'desc' => $desc, 'debit' => 0, 'credit' => $amount]
                        ];
                    } else {
                        // Cash OUT (Debit Expense/Asset, Credit Cash)
                        $lines = [
                            ['account_id' => $contraId, 'desc' => $desc, 'debit' => $amount, 'credit' => 0],
                            ['account_id' => $cashGLId, 'desc' => $desc, 'debit' => 0, 'credit' => $amount]
                        ];
                    }

                    JournalController::post(
                        $date, 
                        $ref ?: 'CSH-'.$transId, 
                        $desc, 
                        'cash_trans', 
                        $transId, 
                        $lines
                    );
                }

                $db->commit();
                header("Location: /bank/cash-on-hand/view?id=$accId");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error processing transaction: " . $e->getMessage());
            }
        }
    }
}