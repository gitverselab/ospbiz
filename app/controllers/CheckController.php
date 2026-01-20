<?php
class CheckController {

    // --- LIST CHECKS ---
    public function index() {
        $db = Database::getInstance();
        
        // [Existing Filter Logic kept same for brevity...]
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

        // Counts & Data
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

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();
        $pageTitle = "Encode Manual Check";
        $childView = ROOT_PATH . '/app/views/bank/checks/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE NEW CHECK ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $amount = floatval($_POST['amount']);
                $bankId = $_POST['financial_account_id'];

                $stmt = $db->prepare("INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'manual')");
                $stmt->execute([$bankId, $_POST['check_number'], $_POST['payee_name'], $_POST['date'], $amount, $_POST['memo']]);

                // Deduct Balance
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")->execute([$amount, $bankId]);

                // Log Transaction
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")->execute([$bankId, $_POST['date'], $amount, "Check Issued: " . $_POST['payee_name'], $_POST['check_number']]);

                $db->commit();
                header("Location: /bank/checks");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- UPDATE DETAILS (Fix Typo) ---
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            // Note: We do NOT allow changing Amount here to prevent balance mismatch. 
            // If amount is wrong, the user must Cancel and re-issue.
            $sql = "UPDATE checks SET check_number = ?, date = ?, payee_name = ?, memo = ? WHERE id = ?";
            $db->prepare($sql)->execute([
                $_POST['check_number'], 
                $_POST['date'], 
                $_POST['payee_name'], 
                $_POST['memo'], 
                $id
            ]);
            header("Location: /bank/checks");
        }
    }

    // --- CHANGE STATUS (Clear, Bounce, Cancel) ---
    public function status() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; // 'cleared', 'bounced', 'cancelled'

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            // Logic: If check was active ('issued') and is now being Voided/Bounced, return money.
            // If it was already cleared, we might need logic to handle that, but for now assuming flow is Issued -> Status
            if (($newStatus === 'cancelled' || $newStatus === 'bounced') && $check['status'] === 'issued') {
                
                // 1. Refund Balance
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$check['amount'], $check['financial_account_id']]);
                
                // 2. Log Reversal
                $desc = ucfirst($newStatus) . " Check #" . $check['check_number'];
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")
                   ->execute([$check['financial_account_id'], $check['amount'], $desc, strtoupper($newStatus)."-" . $check['check_number']]);
            }

            $db->prepare("UPDATE checks SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
            header("Location: /bank/checks");
        }
    }

    // --- DELETE (Admin Only) ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            // SAFETY: If deleting an 'Issued' check, we must refund the balance first!
            if ($check['status'] === 'issued') {
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$check['amount'], $check['financial_account_id']]);
            }

            // Hard Delete
            $db->prepare("DELETE FROM checks WHERE id = ?")->execute([$id]);
            header("Location: /bank/checks");
        }
    }
}