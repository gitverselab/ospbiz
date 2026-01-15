<?php
class FundTransferController {

    // --- LIST TRANSFERS ---
    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1"; 
        $params = [];
        
        if ($search) {
            $where .= " AND (ft.reference_no LIKE ? OR ft.description LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        // Count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM fund_transfers ft WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Fetch Data
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

        $filters = ['search'=>$search, 'page'=>$page, 'limit'=>$limit, 'total_pages'=>$totalPages, 'total_records'=>$totalRecords];

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

    // --- STORE TRANSFER ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $fromId = $_POST['from_account_id'];
                $toId   = $_POST['to_account_id'];
                $amount = floatval($_POST['amount']);
                $date   = $_POST['date'];
                $ref    = $_POST['reference_no'];
                $method = $_POST['method'];

                if ($fromId == $toId) {
                    die("Error: Source and Destination accounts cannot be the same.");
                }

                // 1. Record Transfer
                $sql = "INSERT INTO fund_transfers (company_id, date, reference_no, from_account_id, to_account_id, amount, description, method) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$date, $ref, $fromId, $toId, $amount, $_POST['description'], $method]);
                
                // 2. Deduct from Source (Credit)
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$amount, $fromId]);

                // 3. Add to Destination (Debit)
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$amount, $toId]);

                // 4. Record Transaction Logs
                // Log for Sender
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, 'credit', ?, 'Transfer Out', ?, ?)")
                   ->execute([$fromId, $date, $amount, $ref, $toId]);
                
                // Log for Receiver
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, 'debit', ?, 'Transfer In', ?, ?)")
                   ->execute([$toId, $date, $amount, $ref, $fromId]);

                // 5. If it's a Check Withdrawal, Insert into Check Registry too
                if ($method === 'check') {
                   $payee = "Cash Withdrawal (Transfer)";
                   $checkSql = "INSERT INTO checks (company_id, financial_account_id, check_number, payee_name, date, amount, memo, status, source_type) 
                                VALUES (1, ?, ?, ?, ?, ?, 'Fund Transfer to Cash', 'issued', 'transfer')";
                   $db->prepare($checkSql)->execute([$fromId, $ref, $payee, $date, $amount]);
                }

                $db->commit();
                header("Location: /bank/transfers");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}