<?php
class CheckController {

    public function index() {
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

        if ($search) {
            $where .= " AND (c.check_number LIKE ? OR c.payee_name LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($bankId) { $where .= " AND c.financial_account_id = ?"; $params[] = $bankId; }
        if ($status) { $where .= " AND c.status = ?"; $params[] = $status; }
        if ($fromDate) { $where .= " AND c.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND c.date <= ?"; $params[] = $toDate; }

        $stmt = $db->prepare("SELECT COUNT(*) as total FROM checks c WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT c.*, fa.name as bank_name, fa.account_number 
                FROM checks c 
                JOIN financial_accounts fa ON c.financial_account_id = fa.id 
                WHERE $where 
                ORDER BY c.date DESC, c.check_number DESC 
                LIMIT $limit OFFSET $offset";
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
        $db = Database::getInstance();
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();
        $pageTitle = "Encode Manual Check";
        $childView = ROOT_PATH . '/app/views/bank/checks/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $amount = floatval($_POST['amount']);
                $bankId = $_POST['financial_account_id'];
                $sql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'manual')";
                $stmt = $db->prepare($sql);
                $stmt->execute([$bankId, $_POST['check_number'], $_POST['payee_name'], $_POST['date'], $amount, $_POST['memo']]);
                
                // Manual checks deduct immediately
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $bankId]);
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")->execute([$bankId, $_POST['date'], $amount, "Check Issued to " . $_POST['payee_name'], $_POST['check_number']]);
                
                header("Location: /bank/checks");
            } catch (Exception $e) {
                die($e->getMessage());
            }
        }
    }

    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $sql = "UPDATE checks SET check_number = ?, date = ?, payee_name = ?, memo = ? WHERE id = ?";
            $db->prepare($sql)->execute([$_POST['check_number'], $_POST['date'], $_POST['payee_name'], $_POST['memo'], $id]);
            header("Location: /bank/checks");
        }
    }

    // --- UPDATE STATUS (Fixed to Reverse POs) ---
    public function status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; 

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            try {
                $db->beginTransaction();

                // 1. CLEARING (Issued -> Cleared)
                if ($newStatus === 'cleared' && $check['status'] === 'issued') {
                    $db->prepare("UPDATE checks SET status = 'cleared' WHERE id = ?")->execute([$id]);
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$check['amount'], $check['financial_account_id']]);

                    // Link Expense
                    $findSql = "SELECT id FROM account_transactions WHERE reference_no = ? AND financial_account_id IS NULL AND amount = ?";
                    $pendingTxn = $db->prepare($findSql);
                    $pendingTxn->execute([$check['check_number'], $check['amount']]);
                    $existing = $pendingTxn->fetch();

                    if ($existing) {
                        $db->prepare("UPDATE account_transactions SET financial_account_id = ?, date = NOW() WHERE id = ?")->execute([$check['financial_account_id'], $existing['id']]);
                    } else {
                        $desc = "Check #{$check['check_number']} Cleared ({$check['payee_name']})";
                        $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'credit', ?, ?, ?)")->execute([$check['financial_account_id'], $check['amount'], $desc, "CHK-".$check['check_number']]);
                    }
                }

                // 2. VOIDING / CANCELLING / BOUNCING
                elseif (($newStatus === 'void' || $newStatus === 'cancelled' || $newStatus === 'bounced') && $check['status'] !== 'void') {
                    
                    // A. Refund Bank Balance (Only if cleared or manual)
                    $shouldRefund = ($check['status'] === 'cleared' || $check['source_type'] === 'manual');
                    
                    if ($shouldRefund) {
                        $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$check['amount'], $check['financial_account_id']]);
                        $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")->execute([$check['financial_account_id'], $check['amount'], ucfirst($newStatus)." Check #" . $check['check_number'], strtoupper($newStatus)."-" . $check['check_number']]);
                    }

                    // B. REVERSE PURCHASE ORDERS (Fix for your issue)
                    if ($check['source_type'] === 'purchase_payment') {
                        // 1. Find the Purchase Payment record
                        $paySql = "SELECT * FROM purchase_payments WHERE reference_no = ? AND payment_method = 'check'";
                        $stmtPay = $db->prepare($paySql);
                        $stmtPay->execute([$check['check_number']]);
                        $payment = $stmtPay->fetch();

                        if ($payment) {
                            // 2. Find Allocations (Which POs were paid?)
                            $allocs = $db->query("SELECT * FROM purchase_payment_allocations WHERE purchase_payment_id = {$payment['id']}")->fetchAll();

                            foreach ($allocs as $a) {
                                // 3. Revert PO Balance and Status
                                // Logic: amount_paid decreases. If result <= 0, status becomes 'open'. Otherwise 'partial'.
                                $revSql = "UPDATE purchase_orders 
                                           SET amount_paid = amount_paid - ?, 
                                               status = CASE 
                                                   WHEN (amount_paid - ?) <= 0.01 THEN 'open' 
                                                   ELSE 'partial' 
                                               END 
                                           WHERE id = ?";
                                $db->prepare($revSql)->execute([$a['amount_applied'], $a['amount_applied'], $a['purchase_order_id']]);
                            }

                            // 4. Delete the Payment Records (so it vanishes from history)
                            $db->prepare("DELETE FROM purchase_payment_allocations WHERE purchase_payment_id = ?")->execute([$payment['id']]);
                            $db->prepare("DELETE FROM purchase_payments WHERE id = ?")->execute([$payment['id']]);
                        }
                    }
                    
                    // C. Update Check Status
                    $db->prepare("UPDATE checks SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                }

                $db->commit();
                header("Location: /bank/checks");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            
            if ($check['status'] === 'cleared') {
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$check['amount'], $check['financial_account_id']]);
            }
            $db->prepare("DELETE FROM checks WHERE id = ?")->execute([$id]);
            header("Location: /bank/checks");
        }
    }
}