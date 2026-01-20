<?php
class FundTransferController {

    // --- LIST TRANSFERS ---
    public function index() {
        $db = Database::getInstance();

        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $sourceId = $_GET['source_id'] ?? '';
        $destId = $_GET['dest_id'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1"; 
        $params = [];
        
        if ($search) {
            $where .= " AND (ft.reference_no LIKE ? OR ft.description LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($fromDate) { $where .= " AND ft.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND ft.date <= ?"; $params[] = $toDate; }
        if ($sourceId) { $where .= " AND ft.from_account_id = ?"; $params[] = $sourceId; }
        if ($destId) { $where .= " AND ft.to_account_id = ?"; $params[] = $destId; }

        // Fetch Transfers with Account Names
        $sql = "SELECT ft.*, 
                       f1.name as from_acc, f1.type as from_type,
                       f2.name as to_acc, f2.type as to_type
                FROM fund_transfers ft
                JOIN financial_accounts f1 ON ft.from_account_id = f1.id
                JOIN financial_accounts f2 ON ft.to_account_id = f2.id
                WHERE $where
                ORDER BY ft.date DESC, ft.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $transfers = $stmt->fetchAll();

        // Count for pagination
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM fund_transfers ft WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Fetch Accounts for the Dropdowns
        $accounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();

        $filters = [
            'search' => $search, 'from' => $fromDate, 'to' => $toDate,
            'source_id' => $sourceId, 'dest_id' => $destId,
            'page' => $page, 'limit' => $limit, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Fund Transfers";
        $childView = ROOT_PATH . '/app/views/bank/transfers/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        $accounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();
        
        $pageTitle = "New Fund Transfer";
        $childView = ROOT_PATH . '/app/views/bank/transfers/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE (CREATE REQUEST ONLY) ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            
            $date = $_POST['date'];
            $method = $_POST['method']; // 'cash_handover', 'bank_transfer', 'check'
            $sourceId = $_POST['from_account_id'];
            $destId = $_POST['to_account_id'];
            $amount = floatval($_POST['amount']);
            $desc = $_POST['description'];
            $ref = $_POST['reference_no'];
            $checkNum = $_POST['check_number'] ?? null;

            if ($sourceId == $destId) {
                die("Error: Source and Destination accounts cannot be the same.");
            }

            // Save as PENDING (No balance update yet)
            $sql = "INSERT INTO fund_transfers (company_id, date, reference_no, from_account_id, to_account_id, amount, description, method, check_number, status) 
                    VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')";
            $db->prepare($sql)->execute([$date, $ref, $sourceId, $destId, $amount, $desc, $method, $checkNum]);

            header("Location: /bank/transfers");
        }
    }

    // --- APPROVE (EXECUTE TRANSFER) ---
    public function approve() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];

            try {
                $db->beginTransaction();

                // 1. Get Transfer Details
                $ft = $db->query("SELECT * FROM fund_transfers WHERE id = $id")->fetch();
                
                if (!$ft || $ft['status'] !== 'pending') {
                    throw new Exception("Invalid transfer or already processed.");
                }

                // 2. DEDUCT from Source
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$ft['amount'], $ft['from_account_id']]);
                
                // 3. ADD to Destination
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$ft['amount'], $ft['to_account_id']]);

                // 4. Log Transactions (Source Side)
                $descSrc = "Transfer Out to Account #{$ft['to_account_id']}";
                if ($ft['method'] === 'check') $descSrc .= " (Check #{$ft['check_number']})";
                
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, 'credit', ?, ?, ?, ?)")
                   ->execute([$ft['from_account_id'], $ft['date'], $ft['amount'], $descSrc, $ft['reference_no'], $ft['to_account_id']]);

                // 5. Log Transactions (Destination Side)
                $descDest = "Transfer In from Account #{$ft['from_account_id']}";
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, 'debit', ?, ?, ?, ?)")
                   ->execute([$ft['to_account_id'], $ft['date'], $ft['amount'], $descDest, $ft['reference_no'], $ft['from_account_id']]);

                // 6. IF CHECK: Create Check Registry Entry (Cleared/Issued?)
                // Since this is a fund transfer we are treating as "Approved/Done", we can mark it as CLEARED immediately 
                // OR ISSUED if you want to track it.
                // Based on "Reflect in system", we usually mark it Cleared if we already deducted the balance above.
                if ($ft['method'] === 'check') {
                   $payee = "Transfer to Acc #" . $ft['to_account_id'];
                   // We mark as CLEARED because we already deducted the balance in step 2.
                   $checkSql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                                VALUES (1, ?, ?, ?, ?, ?, ?, 'cleared', 'transfer')";
                   $db->prepare($checkSql)->execute([$ft['from_account_id'], $ft['check_number'], $payee, $ft['date'], $ft['amount'], $ft['description']]);
                }

                // 7. Update Status
                $db->prepare("UPDATE fund_transfers SET status = 'approved', approved_at = NOW() WHERE id = ?")
                   ->execute([$id]);

                $db->commit();
                header("Location: /bank/transfers");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error processing transfer: " . $e->getMessage());
            }
        }
    }

    // --- DELETE / REJECT ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            // Only allow deleting pending transfers
            $db->prepare("DELETE FROM fund_transfers WHERE id = ? AND status = 'pending'")->execute([$id]);
            header("Location: /bank/transfers");
        }
    }
}