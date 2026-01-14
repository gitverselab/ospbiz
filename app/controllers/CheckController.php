<?php
class CheckController {

    // --- LIST CHECKS (Registry) ---
    public function index() {
        $db = Database::getInstance();
        
        // 1. Get Filters
        $search = $_GET['search'] ?? '';
        $bankId = $_GET['bank_id'] ?? '';
        $status = $_GET['status'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';

        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. Build Query
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
        if ($fromDate) {
            $where .= " AND c.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where .= " AND c.date <= ?";
            $params[] = $toDate;
        }

        // 3. Fetch Data
        // Count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM checks c WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Rows
        $sql = "SELECT c.*, fa.name as bank_name 
                FROM checks c 
                JOIN financial_accounts fa ON c.financial_account_id = fa.id 
                WHERE $where 
                ORDER BY c.date DESC, c.check_number DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $checks = $stmt->fetchAll();

        // Dropdowns for Filter
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();

        $filters = [
            'search' => $search, 'bank_id' => $bankId, 'status' => $status,
            'from' => $fromDate, 'to' => $toDate, 'limit' => $limit,
            'page' => $page, 'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Check Registry";
        $childView = ROOT_PATH . '/app/views/bank/checks/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- MANUAL ENTRY FORM ---
    public function create() {
        $db = Database::getInstance();
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type != 'cash' ORDER BY name")->fetchAll();
        
        $pageTitle = "Encode Manual Check";
        $childView = ROOT_PATH . '/app/views/bank/checks/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE MANUAL CHECK ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $amount = floatval($_POST['amount']);
                $bankId = $_POST['financial_account_id'];

                // 1. Insert Check Record
                $sql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, 'issued', 'manual')";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $bankId, $_POST['check_number'], $_POST['payee_name'], 
                    $_POST['date'], $amount, $_POST['memo']
                ]);

                // 2. Deduct from Bank Balance immediately (Standard Bookkeeping)
                // Note: If you only want to deduct when "Cleared", remove this step. 
                // But usually, we deduct when we issue the check.
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$amount, $bankId]);

                // 3. Record Transaction Log
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")
                   ->execute([$bankId, $_POST['date'], $amount, "Check Issued to " . $_POST['payee_name'], $_POST['check_number']]);

                $db->commit();
                header("Location: /bank/checks");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- UPDATE STATUS (Void / Clear) ---
    public function updateStatus() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $newStatus = $_POST['status']; // 'void' or 'cleared'

            $check = $db->query("SELECT * FROM checks WHERE id=$id")->fetch();
            if (!$check) die("Check not found");

            // Logic: If Voiding, we must RETURN the money to the bank balance
            if ($newStatus === 'void' && $check['status'] !== 'void') {
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$check['amount'], $check['financial_account_id']]);
                
                // Add Reversal Transaction Log
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, NOW(), 'debit', ?, ?, ?)")
                   ->execute([$check['financial_account_id'], $check['amount'], "Voided Check #" . $check['check_number'], "VOID-" . $check['check_number']]);
            }

            $db->prepare("UPDATE checks SET status = ? WHERE id = ?")->execute([$newStatus, $id]);
            header("Location: /bank/checks");
        }
    }
}