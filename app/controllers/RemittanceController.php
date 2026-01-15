<?php
class RemittanceController {

    // --- LIST REMITTANCES ---
    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $customer = $_GET['customer'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];

        if($search) {
            $where .= " AND (reference_no LIKE ?)";
            $params[] = "%$search%"; 
        }
        if($customer) {
            $where .= " AND customer_name = ?";
            $params[] = $customer;
        }
        if($fromDate) {
            $where .= " AND date >= ?";
            $params[] = $fromDate;
        }
        if($toDate) {
            $where .= " AND date <= ?";
            $params[] = $toDate;
        }

        // Pagination Count
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM payment_remittances WHERE $where");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Fetch Data
        $sql = "SELECT pr.*, fa.name as bank_name 
                FROM payment_remittances pr
                LEFT JOIN financial_accounts fa ON pr.financial_account_id = fa.id
                WHERE $where ORDER BY date DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $remittances = $stmt->fetchAll();

        // Get Customers for Dropdown
        $customers = $db->query("SELECT DISTINCT customer_name FROM payment_remittances ORDER BY customer_name")->fetchAll();

        $filters = [
            'search' => $search, 'customer' => $customer, 
            'from' => $fromDate, 'to' => $toDate,
            'page' => $page, 'limit' => $limit, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Payment Remittances";
        $childView = ROOT_PATH . '/app/views/revenue/remittance/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        
        // 1. Get Customers with UNPAID Invoices
        $customers = $db->query("SELECT DISTINCT customer_name FROM sales_invoices WHERE status = 'unpaid' ORDER BY customer_name")->fetchAll();
        
        // 2. Get Open Invoices for Selected Customer
        $openInvoices = [];
        if (isset($_GET['customer'])) {
            $cust = $_GET['customer'];
            $invSql = "SELECT * FROM sales_invoices WHERE customer_name = ? AND status = 'unpaid' ORDER BY date ASC";
            $stmt = $db->prepare($invSql);
            $stmt->execute([$cust]);
            $openInvoices = $stmt->fetchAll();
        }

        // 3. Get Bank Accounts for Deposit
        $banks = $db->query("SELECT * FROM financial_accounts WHERE type IN ('bank', 'cash') ORDER BY name")->fetchAll();

        $pageTitle = "Record Payment Remittance";
        $childView = ROOT_PATH . '/app/views/revenue/remittance/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE REMITTANCE ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $customer = $_POST['customer_name'];
                $date = $_POST['date'];
                $ref = $_POST['reference_no'];
                $bankId = $_POST['financial_account_id'];
                $selectedInvIds = $_POST['invoice_ids'] ?? [];

                if (empty($selectedInvIds)) die("No invoices selected.");
                if (empty($bankId)) die("Please select a deposit account.");

                // 1. Calculate Totals from Selected Invoices
                $placeholders = str_repeat('?,', count($selectedInvIds) - 1) . '?';
                $sql = "SELECT * FROM sales_invoices WHERE id IN ($placeholders)";
                $stmt = $db->prepare($sql);
                $stmt->execute($selectedInvIds);
                $invoices = $stmt->fetchAll();

                $totalGross = 0;
                $totalVatable = 0;

                foreach ($invoices as $inv) {
                    $totalGross += floatval($inv['total_amount_due']);
                    $totalVatable += floatval($inv['vatable_sales']);
                }

                // 2. Calculate WHT (1% of Total Vatable) - Matches the remittance image
                $totalWht = $totalVatable * 0.01;
                
                // 3. Calculate Net Received
                $netReceived = $totalGross - $totalWht;

                // 4. Insert Remittance Record
                $insertRemit = $db->prepare("INSERT INTO payment_remittances (company_id, date, customer_name, reference_no, financial_account_id, total_gross_amount, total_wht_amount, net_amount_received) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
                $insertRemit->execute([$date, $customer, $ref, $bankId, $totalGross, $totalWht, $netReceived]);
                $remitId = $db->lastInsertId();

                // 5. Link Invoices and Mark as Paid
                $linkStmt = $db->prepare("INSERT INTO remittance_invoice_links (remittance_id, invoice_id, applied_amount) VALUES (?, ?, ?)");
                $updateInv = $db->prepare("UPDATE sales_invoices SET status = 'paid' WHERE id = ?");

                foreach ($invoices as $inv) {
                    // Link the full invoice amount
                    $linkStmt->execute([$remitId, $inv['id'], $inv['total_amount_due']]);
                    // Mark as paid
                    $updateInv->execute([$inv['id']]);
                }

                // 6. Update Bank Balance (Add Net Amount Received)
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$netReceived, $bankId]);

                // 7. Record Transaction Log
                $desc = "Payment from $customer for " . count($invoices) . " invoices (Ref: $ref)";
                $db->prepare("INSERT INTO account_transactions (financial_account_id, date, type, amount, description, reference_no) VALUES (?, ?, 'debit', ?, ?, ?)")
                   ->execute([$bankId, $date, $netReceived, $desc, $ref]);

                // 8. AUTOMATIC JOURNAL ENTRY
                $entries = [];

                // Entry 1: Debit Cash in Bank (Net Amount Received)
                // We need to fetch the Account Code of the selected Bank
                $bankAcc = $db->query("SELECT code FROM accounts WHERE id = (SELECT account_id FROM financial_accounts WHERE id=$bankId)")->fetch();
                $bankCode = $bankAcc['code'] ?? '1010'; // Fallback if not linked

                $entries[] = [
                    'code' => $bankCode, 
                    'desc' => "Payment from $customer (Ref: $ref)",
                    'debit' => $netReceived,
                    'credit' => 0
                ];

                // Entry 2: Debit Creditable Withholding Tax (CWT)
                if ($totalWht > 0) {
                    $entries[] = [
                        'code' => '1300', // Ensure this matches "Creditable Withholding Tax"
                        'desc' => "CWT (1%) - Ref: $ref",
                        'debit' => $totalWht,
                        'credit' => 0
                    ];
                }

                // Entry 3: Credit Accounts Receivable (Total Gross Amount Paid)
                $entries[] = [
                    'code' => '1200', // Accounts Receivable
                    'desc' => "Payment Received - $customer",
                    'debit' => 0,
                    'credit' => $totalGross
                ];

                // Call the Journal Helper
                require_once ROOT_PATH . '/app/controllers/JournalController.php';
                JournalController::post(
                    $date, 
                    $ref, 
                    "Collection from $customer", 
                    'remittance', 
                    $remitId, 
                    $entries
                );

                $db->commit(); // <--- Commit AFTER posting
                header("Location: /revenue/remittance");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}