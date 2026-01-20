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

    // --- FIXED UPDATE STATUS FUNCTION ---
    public function status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; 

            // FIX: Map 'cancelled' or 'bounced' to 'void' logic if you want them treated similarly
            // Or handle them explicitly. For now, let's treat 'cancelled' as 'void' logic.
            
            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            try {
                $db->beginTransaction();

                // 1. CLEARING
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
                // FIX: Added check for 'cancelled' and 'bounced' here
                elseif (($newStatus === 'void' || $newStatus === 'cancelled' || $newStatus === 'bounced') && $check['status'] !== 'void') {
                    
                    // Only refund if it was CLEARED or MANUAL
                    $shouldRefund = ($check['status'] === 'cleared' || $check['source_type'] === 'manual');
                    
                    if ($shouldRefund) {
                        $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")->execute([$check['amount'], $check['financial_account_id']]);
                        $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")->execute([$check['financial_account_id'], $check['amount'], ucfirst($newStatus)." Check #" . $check['check_number'], strtoupper($newStatus)."-" . $check['check_number']]);
                    }
                    
                    // Update status to whatever was requested (void, cancelled, bounced)
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