<?php
class CashController {
    
    public function index() {
        $db = Database::getInstance();
        $accounts = $db->query("SELECT * FROM financial_accounts WHERE type = 'cash'")->fetchAll();
        
        $pageTitle = "Cash on Hand Management";
        $childView = ROOT_PATH . '/app/views/cash/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function show() {
        $id = $_GET['id'] ?? 0;
        $db = Database::getInstance();
        
        $stmt = $db->prepare("SELECT * FROM financial_accounts WHERE id = ?");
        $stmt->execute([$id]);
        $account = $stmt->fetch();

        if (!$account) { header("Location: /bank/cash-on-hand"); exit; }

        $stmt = $db->prepare("SELECT * FROM account_transactions WHERE financial_account_id = ? ORDER BY date DESC");
        $stmt->execute([$id]);
        $transactions = $stmt->fetchAll();

        $coa = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();

        $pageTitle = $account['name'];
        $childView = ROOT_PATH . '/app/views/cash/show.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $sql = "INSERT INTO financial_accounts (company_id, type, name, current_balance) VALUES (?, 'cash', ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([1, $_POST['name'], $_POST['opening_balance']]);
            header("Location: /bank/cash-on-hand");
        }
    }
    
    // Re-use the logic for adding transactions? Or copy paste storeTransaction from BankController
    // For simplicity, you can copy the storeTransaction method from BankController here and change redirect.
    public function storeTransaction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $accId = $_POST['financial_account_id'];
            $amount = $_POST['amount'];
            $type = $_POST['type']; 

            $sql = "INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $db->prepare($sql);
            $stmt->execute([$accId, $_POST['date'], $type, $amount, $_POST['description'], $_POST['reference_no'], $_POST['contra_account_id']]);

            $operator = ($type === 'debit') ? '+' : '-';
            $update = $db->prepare("UPDATE financial_accounts SET current_balance = current_balance $operator ? WHERE id = ?");
            $update->execute([$amount, $accId]);

            header("Location: /bank/cash-on-hand/view?id=$accId");
        }
    }
}