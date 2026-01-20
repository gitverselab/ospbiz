<?php
class CheckController {

    public function index() {
        // ... (Keep existing index code exactly the same) ...
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
        if ($bankId) {
            $where .= " AND c.financial_account_id = ?";
            $params[] = $bankId;
        }
        if ($status) {
            $where .= " AND c.status = ?";
            $params[] = $status;
        }
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

    // --- STORE: JUST RECORD, DO NOT DEDUCT ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $amount = floatval($_POST['amount']);
                $bankId = $_POST['financial_account_id'];

                // Just insert the check. No balance update yet.
                $stmt = $db->prepare("INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'manual')");
                $stmt->execute([$bankId, $_POST['check_number'], $_POST['payee_name'], $_POST['date'], $amount, $_POST['memo']]);

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

    // --- STATUS CHANGE: THIS IS WHERE WE DEDUCT/ADD ---
    public function status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; // 'cleared', 'bounced', 'cancelled'

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            try {
                $db->beginTransaction();

                // CASE 1: CLEARING (The bank finally took the money)
                if ($newStatus === 'cleared' && $check['status'] === 'issued') {
                    
                    // 1. DEDUCT Balance Now
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                       ->execute([$check['amount'], $check['financial_account_id']]);

                    // 2. Record the Ledger Transaction
                    $desc = "Check #{$check['check_number']} Cleared ({$check['payee_name']})";
                    $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'credit', ?, ?, ?)")
                       ->execute([$check['financial_account_id'], $check['amount'], $desc, "CHK-".$check['check_number']]);
                
                    // 3. Post Journal Entry (Dr Liability/Expense, Cr Bank)
                    // Since we didn't record expense originally, we record it now?
                    // NOTE: If this was an EXPENSE check, we should have recorded the expense earlier but not the bank impact.
                    // For simplicity in this "Cash Basis" mode, we record the GL impact now.
                    
                    // (Optional: Add JournalController Logic here if you want GL to update only on clear)
                }

                // CASE 2: UN-CLEARING (Mistake? Bounced after clearing?)
                // If it was 'cleared' and we are moving to 'bounced' or 'cancelled', we put money back.
                elseif ($check['status'] === 'cleared' && ($newStatus === 'bounced' || $newStatus === 'cancelled')) {
                    $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                       ->execute([$check['amount'], $check['financial_account_id']]);
                    
                    // Log Reversal
                    $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")
                       ->execute([$check['financial_account_id'], $check['amount'], "Reversal: Check #{$check['check_number']}", "REV-".$check['check_number']]);
                }

                // CASE 3: CANCEL ISSUED (Never cleared)
                // Money never left, so we don't need to add it back. Just change status.

                $db->prepare("UPDATE checks SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
                $db->commit();
                header("Location: /bank/checks");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    public function delete() {
        // ... (Keep existing delete logic, but remove refund logic for 'issued' checks since they were never deducted) ...
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            
            // Only refund if it was CLEARED
            if ($check['status'] === 'cleared') {
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$check['amount'], $check['financial_account_id']]);
            }
            $db->prepare("DELETE FROM checks WHERE id = ?")->execute([$id]);
            header("Location: /bank/checks");
        }
    }
}