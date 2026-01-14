<?php
class LoanPaymentController {

    // --- LIST PAYMENTS ---
    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (l.lender_name LIKE ? OR lp.reference_no LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        // Count
        $stmt = $db->prepare("SELECT COUNT(*) as total FROM loan_payments lp LEFT JOIN loans l ON lp.loan_id = l.id WHERE $where");
        $stmt->execute($params);
        $totalRecords = $stmt->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Fetch
        $sql = "SELECT lp.*, l.lender_name, l.reference_no as loan_ref, fa.name as account_name 
                FROM loan_payments lp 
                LEFT JOIN loans l ON lp.loan_id = l.id 
                LEFT JOIN financial_accounts fa ON lp.financial_account_id = fa.id
                WHERE $where 
                ORDER BY lp.date DESC 
                LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $payments = $stmt->fetchAll();

        $filters = ['search'=>$search, 'page'=>$page, 'limit'=>$limit, 'total_pages'=>$totalPages, 'total_records'=>$totalRecords];

        $pageTitle = "Loan Payments";
        $childView = ROOT_PATH . '/app/views/expenses/loan_payments/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        
        // 1. Get Active Loans (Only those with balance remaining)
        // We calculate balance as Principal - Amount Paid
        $loans = $db->query("SELECT *, (principal_amount - amount_paid) as balance 
                             FROM loans 
                             WHERE status = 'active' AND (principal_amount - amount_paid) > 0 
                             ORDER BY lender_name")->fetchAll();

        // 2. Get Payment Accounts
        $accounts = $db->query("SELECT * FROM financial_accounts ORDER BY type, name")->fetchAll();

        $pageTitle = "New Loan Payment";
        $childView = ROOT_PATH . '/app/views/expenses/loan_payments/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE PAYMENT ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $loanId = $_POST['loan_id'];
                $bankId = $_POST['financial_account_id'];
                $principal = floatval($_POST['principal_amount']);
                $interest = floatval($_POST['interest_amount']);
                $total = $principal + $interest;
                $date = $_POST['date'];

                // 1. Record Payment
                $sql = "INSERT INTO loan_payments (company_id, loan_id, financial_account_id, date, reference_no, principal_amount, interest_amount, total_paid) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$loanId, $bankId, $date, $_POST['reference_no'], $principal, $interest, $total]);

                // 2. Deduct Total Cash from Bank
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$total, $bankId]);

                // 3. Update Loan Balance (Only Principal reduces the balance)
                // Also check if fully paid
                $updateLoan = $db->prepare("UPDATE loans SET amount_paid = amount_paid + ? WHERE id = ?");
                $updateLoan->execute([$principal, $loanId]);

                // Check for full payment status update
                $loan = $db->query("SELECT * FROM loans WHERE id=$loanId")->fetch();
                if ($loan['amount_paid'] >= $loan['principal_amount']) {
                    $db->query("UPDATE loans SET status='paid' WHERE id=$loanId");
                }

                // 4. Record Transaction Log
                $desc = "Loan Payment (Prin: $principal, Int: $interest)";
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'credit', ?, ?, ?)")
                   ->execute([$bankId, $date, $total, $desc, $_POST['reference_no']]);

                $db->commit();
                header("Location: /expenses/loan-payments");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}