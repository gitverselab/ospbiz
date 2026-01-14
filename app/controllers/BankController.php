<?php
class BankController {
    
    // List all Passbooks
    public function index() {
        $db = Database::getInstance();
        $passbooks = $db->query("SELECT * FROM financial_accounts WHERE type = 'bank'")->fetchAll();
        
        $pageTitle = "Passbook Management";
        $childView = ROOT_PATH . '/app/views/bank/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // View Transactions for a Specific Passbook
    public function show() {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();
        
        // Get Account Info
        $stmt = $db->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) { header("Location: /bank/passbooks"); exit; }

        // Get Transactions
        $stmt = $db->prepare("SELECT * FROM account_transactions WHERE financial_account_id = ? ORDER BY date DESC, id DESC");
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll();

        // Get COA for Dropdown (Expenses/Revenue)
        $coa = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

        $pageTitle = $account['name'];
        $childView = ROOT_PATH . '/app/views/bank/show.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // Create New Passbook
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $sql = "INSERT INTO financial_accounts (company_id, type, name, bank_name, account_number, account_holder, current_balance) VALUES (?, 'bank', ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([1, $_POST['name'], $_POST['bank_name'], $_POST['account_number'], $_POST['account_holder'], $_POST['opening_balance']]);
            header("Location: /bank/passbooks");
        }
    }

    // Add Transaction (Deposit/Withdrawal)
    public function storeTransaction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $accId = $_POST['financial_account_id'];
            $amount = $_POST['amount'];
            $type = $_POST['type']; // 'debit' (In) or 'credit' (Out)

            // 1. Insert Transaction
            $sql = "INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$accId, $_POST['date'], $type, $amount, $_POST['description'], $_POST['reference_no'], $_POST['contra_account_id']]);

            // 2. Update Account Balance
            // Asset Logic: Debit increases, Credit decreases
            $operator = ($type === 'debit') ? '+' : '-';
            $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance $operator ? WHERE id = ?");
            $update->execute([$amount, $accId]);

            header("Location: /bank/passbooks/view?id=$accId");
        }
    }
}