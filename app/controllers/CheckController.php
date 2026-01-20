<?php
class CheckController {

    public function index() {
        // ... (Keep your existing index code) ...
        // Ensure you include the index() method here from your uploaded file
        $db = Database::getInstance();
        $search = $_GET['search'] ?? '';
        $bankId = $_GET['bank_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;
        $where = "1=1";
        $params = [];
        if ($search) { $where .= " AND (c.check_number LIKE ? OR c.payee_name LIKE ?)"; $params[] = "%$search%"; $params[] = "%$search%"; }
        if ($bankId) { $where .= " AND c.financial_account_id = ?"; $params[] = $bankId; }
        if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }
        if ($fromDate) { $where .= " AND c.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND c.date <= ?"; $params[] = $toDate; }
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM checks c WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);
        $sql = "SELECT c.*, fa.name as bank_name, fa.account_number FROM checks c JOIN financial_accounts fa ON c.financial_account_id = fa.id WHERE $where ORDER BY c.date DESC, c.check_number DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $checks = $stmt->fetchAll();
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();
        $filters = compact('search', 'bankId', 'status', 'fromDate', 'toDate', 'limit', 'page', 'totalPages', 'totalRecords');
        $pageTitle = "Check Registry";
        $childView = ROOT_PATH . '/app/views/bank/checks/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        // ... (Keep existing create code) ...
        $db = Database::getInstance();
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();
        $pageTitle = "Encode Manual Check";
        $childView = ROOT_PATH . '/app/views/bank/checks/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        // ... (Keep existing store code) ...
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();
                $amount = floatval($_POST['amount']);
                $bankId = $_POST['financial_account_id'];
                $sql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'manual')";
                $stmt = $db->prepare($sql);
                $stmt->execute([$bankId, $_POST['check_number'], $_POST['payee_name'], $_POST['date'], $amount, $_POST['memo']]);
                // For manual checks, we deduct immediately (standard) OR follow the same pending rule. 
                // Let's deduct immediately for manual checks to be safe, as they might not be expenses.
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $bankId]);
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")->execute([$bankId, $_POST['date'], $amount, "Check Issued to " . $_POST['payee_name'], $_POST['check_number']]);
                $db->commit();
                header("Location: /bank/checks");
            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- UPDATE STATUS (Clear / Void) ---
    public function updateStatus() { // Can be accessed via /checks/status or /checks/updateStatus
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; // 'cleared', 'void', 'bounced'

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            try {
                $db->beginTransaction();

                // 1. CLEARING A CHECK
                if ($newStatus === 'cleared' && $check['status'] === 'issued') {
                    
                    // A. Update Check Status
                    $db->prepare("UPDATE checks SET status = 'cleared' WHERE id = ?")->execute([$id]);

                    // B. Deduct Bank Balance (Real-time movement)
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                       ->execute([$check['amount'], $check['financial_account_id']]);

                    // C. SMART LINK: Find the original "Pending" Expense Transaction
                    // We look for a transaction with the same Reference No (Check #) and NULL financial account
                    $findSql = "SELECT id FROM account_transactions 
                                WHERE reference_no = ? AND financial_account_id IS NULL AND amount = ?";
                    $pendingTxn = $db->prepare($findSql);
                    $pendingTxn->execute([$check['check_number'], $check['amount']]);
                    $existing = $pendingTxn->fetch();

                    if ($existing) {
                        // FOUND IT! Link it to the bank. This makes it appear in the Passbook now.
                        $db->prepare("UPDATE account_transactions SET financial_account_id = ?, date = NOW() WHERE id = ?")
                           ->execute([$check['financial_account_id'], $existing['id']]);
                    } else {
                        // Didn't find it (maybe a Manual Check that was pending?). Create new log.
                        $desc = "Check #{$check['check_number']} Cleared ({$check['payee_name']})";
                        $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'credit', ?, ?, ?)")
                           ->execute([$check['financial_account_id'], $check['amount'], $desc, "CHK-".$check['check_number']]);
                    }
                }

                // 2. VOIDING A CHECK
                elseif ($newStatus === 'void' && $check['status'] !== 'void') {
                    // Logic: If it was cleared, return money. If issued (and manual), return money.
                    // If it was Issued+Expense, no money was deducted yet, so don't add back.
                    
                    // Simplified: Only add back if we previously deducted.
                    // Manual checks deduct on creation. Cleared checks deduct on clearing.
                    $shouldRefund = ($check['status'] === 'cleared' || $check['source_type'] === 'manual');
                    
                    if ($shouldRefund) {
                        $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                           ->execute([$check['amount'], $check['financial_account_id']]);
                        
                        $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")
                           ->execute([$check['financial_account_id'], $check['amount'], "Voided Check #" . $check['check_number'], "VOID-" . $check['check_number']]);
                    }
                    
                    $db->prepare("UPDATE checks SET status = 'void' WHERE id = ?")->execute([$id]);
                }

                $db->commit();
                header("Location: /bank/checks");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}