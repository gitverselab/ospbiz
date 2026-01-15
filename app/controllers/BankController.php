<?php
class BankController {
    
    // --- LIST PASSBOOKS ---
    public function index() {
        $db = Database::getInstance();
        // Fetch banks joined with their Linked GL Account name for clarity
        $sql = "SELECT fa.*, a.code as gl_code, a.name as gl_name 
                FROM financial_accounts fa 
                LEFT JOIN accounts a ON fa.account_id = a.id 
                WHERE fa.type = 'bank' 
                ORDER BY fa.name";
        $passbooks = $db->query($sql)->fetchAll();
        
        // Fetch Asset Accounts for the "Add Bank" dropdown
        $assetAccounts = $db->query("SELECT * FROM accounts WHERE type = 'Asset' ORDER BY code")->fetchAll();
        
        $pageTitle = "Passbook Management";
        $childView = ROOT_PATH . '/app/views/bank/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- VIEW SPECIFIC PASSBOOK ---
    public function show() {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();
        
        // Get Account Info
        $stmt = $db->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) { header("Location: /bank/passbooks"); exit; }

        // Get Transactions
        $stmt = $db->prepare("SELECT t.*, a.name as contra_account_name 
                              FROM account_transactions t 
                              LEFT JOIN accounts a ON t.contra_account_id = a.id 
                              WHERE t.financial_account_id = ? 
                              ORDER BY t.date DESC, t.id DESC");
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll();

        // Get COA for Dropdown (Expenses/Revenue/Equity/Liabilities for manual transactions)
        $coa = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

        $pageTitle = $account['name'];
        $childView = ROOT_PATH . '/app/views/bank/show.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE NEW PASSBOOK (With Journal Entry) ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $name = $_POST['name'];
                $bankName = $_POST['bank_name'];
                $acctNum = $_POST['account_number'];
                $holder = $_POST['account_holder'];
                $balance = floatval($_POST['opening_balance']);
                $glAccountId = $_POST['account_id']; // The User selected "1010 - BDO"

                // 1. Insert Bank Account with Link
                $sql = "INSERT INTO financial_accounts (company_id, type, name, bank_name, account_number, account_holder, current_balance, opening_balance, account_id) VALUES (?, 'bank', ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([1, $name, $bankName, $acctNum, $holder, $balance, $balance, $glAccountId]);
                $bankId = $db->lastInsertId();

                // 2. AUTOMATIC JOURNAL ENTRY (Opening Balance)
                if ($balance > 0 && $glAccountId) {
                    
                    // Find an Equity Account (Credit Side)
                    $equityInfo = $db->query("SELECT id FROM accounts WHERE type = 'Equity' LIMIT 1")->fetch();
                    $equityId = $equityInfo ? $equityInfo['id'] : 0; // Fallback if no equity account found

                    if ($equityId) {
                        require_once ROOT_PATH . '/app/controllers/JournalController.php';

                        // Manual Journal Insertion (Precise ID linking)
                        // Header
                        $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, source_id, status) VALUES (1, CURDATE(), ?, ?, 'bank_setup', ?, 'posted')")
                           ->execute(['OP-'.$bankId, "Opening Balance: $name", $bankId]);
                        $jid = $db->lastInsertId();

                        // Debit: Bank (Asset)
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                           ->execute([$jid, $glAccountId, "Opening Balance", $balance]);

                        // Credit: Equity (Capital)
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                           ->execute([$jid, $equityId, "Opening Balance", $balance]);
                    }
                }

                $db->commit();
                header("Location: /bank/passbooks");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error creating bank: " . $e->getMessage());
            }
        }
    }

    // --- ADD TRANSACTION (With Journal Entry) ---
    public function storeTransaction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $accId = $_POST['financial_account_id'];
                $amount = floatval($_POST['amount']);
                $type = $_POST['type']; // 'debit' (Deposit/In) or 'credit' (Withdrawal/Out)
                $date = $_POST['date'];
                $desc = $_POST['description'];
                $ref = $_POST['reference_no'];
                $contraId = $_POST['contra_account_id']; // The other side of the entry

                // 1. Insert Transaction Record (For Passbook View)
                $sql = "INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$accId, $date, $type, $amount, $desc, $ref, $contraId]);
                $transId = $db->lastInsertId();

                // 2. Update Bank Balance
                $operator = ($type === 'debit') ? '+' : '-';
                $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance $operator ? WHERE id = ?");
                $update->execute([$amount, $accId]);

                // 3. AUTOMATIC JOURNAL ENTRY
                // Get the GL Account ID linked to this Bank
                $bankInfo = $db->query("SELECT account_id FROM financial_accounts WHERE id = $accId")->fetch();
                $bankGLId = $bankInfo['account_id'];

                if ($bankGLId && $contraId) {
                    // Header
                    $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, source_id, status) VALUES (1, ?, ?, ?, 'bank_trans', ?, 'posted')")
                       ->execute([$date, $ref ?: 'BNK-'.$transId, $desc, $transId]);
                    $jid = $db->lastInsertId();

                    if ($type === 'debit') {
                        // DEPOSIT: Debit Bank, Credit Contra
                        // Debit Bank
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                           ->execute([$jid, $bankGLId, $desc, $amount]);
                        // Credit Contra
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                           ->execute([$jid, $contraId, $desc, $amount]);
                    } else {
                        // WITHDRAWAL: Debit Contra, Credit Bank
                        // Debit Contra
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, 0)")
                           ->execute([$jid, $contraId, $desc, $amount]);
                        // Credit Bank
                        $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, 0, ?)")
                           ->execute([$jid, $bankGLId, $desc, $amount]);
                    }
                }

                $db->commit();
                header("Location: /bank/passbooks/view?id=$accId");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error processing transaction: " . $e->getMessage());
            }
        }
    }
}
?>