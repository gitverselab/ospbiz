<?php
class LoanController {

    // --- LIST LOANS ---
    public function index() {
        $db = Database::getInstance();
        
        // 1. Get Filters
        $search = $_GET['search'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // Pagination
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. Build Query
        $whereSql = "1=1";
        $params = [];

        if ($search) {
            $whereSql .= " AND (l.lender_name LIKE ? OR l.reference_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($status) {
            $whereSql .= " AND l.status = ?";
            $params[] = $status;
        }

        // 3. Count Total
        $countSql = "SELECT COUNT(*) as total FROM loans l WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. Fetch Data
        $sql = "SELECT l.*, fa.name as deposit_account, a.name as liability_account, 
                (l.principal_amount - l.amount_paid) as balance 
                FROM loans l
                LEFT JOIN financial_accounts fa ON l.financial_account_id = fa.id
                LEFT JOIN accounts a ON l.liability_account_id = a.id
                WHERE $whereSql
                ORDER BY l.date DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $loans = $stmt->fetchAll();

        // Data for Filters
        $filters = [
            'search' => $search, 'status' => $status,
            'limit' => $limit, 'page' => $page, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Credits / Loans";
        $childView = ROOT_PATH . '/app/views/expenses/loans/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        // Where to put money (Banks/Cash)
        $financialAccounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();
        // Liability Accounts (Chart of Accounts - Liabilities only)
        $liabilityAccounts = $db->query("SELECT * FROM accounts WHERE type IN ('liability') ORDER BY code")->fetchAll();

        $pageTitle = "New Loan";
        $childView = ROOT_PATH . '/app/views/expenses/loans/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE LOAN ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $amount = floatval($_POST['principal_amount']);
                $bankId = $_POST['financial_account_id'];
                $liabId = $_POST['liability_account_id'];
                $date   = $_POST['date'];

                // 1. Create Loan Record
                $sql = "INSERT INTO loans (company_id, lender_name, reference_no, date, principal_amount, interest_rate, maturity_date, financial_account_id, liability_account_id, description, status) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'active')";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['lender_name'], $_POST['reference_no'], $date, $amount, 
                    $_POST['interest_rate'], $_POST['maturity_date'], $bankId, $liabId, $_POST['description']
                ]);

                // 2. Add Money to Bank/Cash Balance (Loan Proceeds)
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$amount, $bankId]);

                // 3. Record Transaction Log (Debit Bank, Credit Liability)
                // Type is 'debit' because Asset (Bank) is increasing.
                $desc = "Loan Proceeds from " . $_POST['lender_name'];
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no, contra_account_id) VALUES (?, ?, 'debit', ?, ?, ?, ?)")
                   ->execute([$bankId, $date, $amount, $desc, $_POST['reference_no'], $liabId]);

                $db->commit();
                header("Location: /expenses/loans");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}