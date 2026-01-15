<?php
class SalesController {

    // --- 1. LIST INVOICES ---
    public function index() {
        $db = Database::getInstance();
        
        // 1. Get Filters
        $search = $_GET['search'] ?? '';
        $customer = $_GET['customer'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. Build Query
        $where = "1=1";
        $params = [];

        if($search) {
            $where .= " AND invoice_number LIKE ?";
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
        if($status) {
            $where .= " AND status = ?";
            $params[] = $status;
        }

        // 3. Pagination Count
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM sales_invoices WHERE $where");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. Fetch Data
        $sql = "SELECT * FROM sales_invoices WHERE $where ORDER BY date DESC, id DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        // 5. Get Unique Customers for Dropdown
        $customers = $db->query("SELECT DISTINCT customer_name FROM sales_invoices ORDER BY customer_name")->fetchAll();

        $filters = [
            'search' => $search, 'customer' => $customer, 
            'from' => $fromDate, 'to' => $toDate, 'status' => $status,
            'page' => $page, 'limit' => $limit, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "Sales Invoices";
        $childView = ROOT_PATH . '/app/views/revenue/sales/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- 2. CREATE INVOICE FORM ---
    public function create() {
        $db = Database::getInstance();
        
        // A. Get Customers who have UNINVOICED DRs
        $customers = $db->query("SELECT DISTINCT customer_name FROM delivery_receipts WHERE invoice_status = 'uninvoiced' ORDER BY customer_name")->fetchAll();
        
        // B. If a customer is selected, get their Open DRs
        $openDrs = [];
        if (isset($_GET['customer'])) {
            $cust = $_GET['customer'];
            // We sum the lines to show a preview amount for each DR
            $drSql = "SELECT d.*, 
                      (SELECT SUM(amount) FROM dr_lines WHERE dr_id = d.id) as grand_total 
                      FROM delivery_receipts d 
                      WHERE d.customer_name = ? AND d.invoice_status = 'uninvoiced'
                      ORDER BY d.date ASC";
            $stmt = $db->prepare($drSql);
            $stmt->execute([$cust]);
            $openDrs = $stmt->fetchAll();
        }

        // C. Get Next Invoice Number from Active Booklet
        $activeBooklet = $db->query("SELECT * FROM invoice_booklets WHERE status='active' ORDER BY series_start ASC LIMIT 1")->fetch();
        $suggestedInv = $activeBooklet ? $activeBooklet['current_counter'] : '';

        // D. Get Default Settings (Address, etc)
        $settings = $db->query("SELECT * FROM receipt_settings LIMIT 1")->fetch();

        $pageTitle = "Record Sales Invoice";
        $childView = ROOT_PATH . '/app/views/revenue/sales/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- 3. SAVE INVOICE (Manual Recording) ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $invNum = $_POST['invoice_number'];
                $selectedDrIds = $_POST['dr_ids'] ?? [];
                
                // Get the WHT amount calculated by JS (or manually edited)
                $whtAmount = floatval($_POST['wht_amount'] ?? 0);

                if (empty($selectedDrIds)) {
                    throw new Exception("Please select at least one Delivery Receipt (DR).");
                }

                // A. Validate Booklet
                $booklet = $db->query("SELECT * FROM invoice_booklets WHERE '$invNum' BETWEEN series_start AND series_end AND status='active'")->fetch();

                // B. Calculate Totals from DR Lines
                $placeholders = str_repeat('?,', count($selectedDrIds) - 1) . '?';
                $linesSql = "SELECT l.*, d.dr_number, d.is_vat_inc 
                             FROM dr_lines l 
                             JOIN delivery_receipts d ON l.dr_id = d.id 
                             WHERE d.id IN ($placeholders)";
                $stmtLines = $db->prepare($linesSql);
                $stmtLines->execute($selectedDrIds);
                $allLines = $stmtLines->fetchAll();

                $vatable = 0;
                $vatAmount = 0;
                $grossTotal = 0;

                foreach ($allLines as $line) {
                    $amount = floatval($line['amount']);
                    
                    // VAT Calculation Logic
                    if ($line['is_vat_inc']) {
                        // Inclusive
                        $grossTotal += $amount;
                        $net = $amount / 1.12;
                        $vat = $amount - $net;
                    } else {
                        // Exclusive
                        $net = $amount;
                        $vat = $amount * 0.12;
                        $grossTotal += ($amount + $vat);
                    }
                    $vatable += $net;
                    $vatAmount += $vat;
                }

                // C. Calculate Net Amount Receivable
                // This matches the "Paid Amt" in your screenshot
                $netAmountDue = $grossTotal - $whtAmount;

                // D. Insert Invoice Header
                $sql = "INSERT INTO sales_invoices (company_id, invoice_number, date, customer_name, tin, address, business_style, terms, vatable_sales, vat_amount, total_amount_due, wht_amount, net_amount_due, status) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'unpaid')";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $invNum, $_POST['date'], $_POST['customer_name'], 
                    $_POST['tin'], $_POST['address'], $_POST['business_style'], $_POST['terms'],
                    $vatable, $vatAmount, $grossTotal, $whtAmount, $netAmountDue
                ]);
                $invId = $db->lastInsertId();

                // E. Insert Invoice Lines
                $insertLine = $db->prepare("INSERT INTO sales_invoice_lines (invoice_id, source_dr_number, item_code, description, quantity, uom, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                foreach ($allLines as $line) {
                    $insertLine->execute([
                        $invId, $line['dr_number'], $line['item_code'], $line['description'], 
                        $line['quantity'], $line['uom'], $line['price'], $line['amount']
                    ]);
                }

                // F. Link DRs and Mark as Invoiced
                $linkStmt = $db->prepare("INSERT INTO dr_invoice_links (invoice_id, dr_id) VALUES (?, ?)");
                $updateDr = $db->prepare("UPDATE delivery_receipts SET invoice_status = 'invoiced' WHERE id = ?");

                foreach ($selectedDrIds as $drId) {
                    $linkStmt->execute([$invId, $drId]);
                    $updateDr->execute([$drId]);
                }

                // G. Update Booklet Counter
                if ($booklet) {
                    $newCounter = intval($invNum) + 1;
                    $status = ($newCounter > $booklet['series_end']) ? 'full' : 'active';
                    if ($newCounter > $booklet['current_counter']) {
                        $db->prepare("UPDATE invoice_booklets SET current_counter = ?, status = ? WHERE id = ?")
                           ->execute([$newCounter, $status, $booklet['id']]);
                    }
                }
                // ... (After linking DRs and Booklet update) ...

                // H. AUTOMATIC JOURNAL ENTRY (The "Pipe")
                // 1. Define the Accounting Entries
                $entries = [];

                // Entry 1: Debit Accounts Receivable (Total Gross Amount)
                $entries[] = [
                    'code' => '1200', // Ensure this matches your COA "Accounts Receivable"
                    'desc' => "Invoice #$invNum - {$_POST['customer_name']}",
                    'debit' => $grossTotal,
                    'credit' => 0
                ];

                // Entry 2: Credit VAT Payable (Output VAT)
                if ($vatAmount > 0) {
                    $entries[] = [
                        'code' => '2500', // Ensure this matches "VAT Payable"
                        'desc' => "Output VAT - Inv #$invNum",
                        'debit' => 0,
                        'credit' => $vatAmount
                    ];
                }

                // Entry 3: Credit Sales Revenue (Vatable Sales)
                $entries[] = [
                    'code' => '4000', // Ensure this matches "Sales"
                    'desc' => "Sales Revenue - Inv #$invNum",
                    'debit' => 0,
                    'credit' => $vatable
                ];

                // Call the Journal Helper
                require_once ROOT_PATH . '/app/controllers/JournalController.php';
                JournalController::post(
                    $_POST['date'], 
                    $invNum, 
                    "Sales Invoice to {$_POST['customer_name']}", 
                    'sales', 
                    $invId, 
                    $entries
                );

                $db->commit(); // <--- Commit AFTER posting to Journal
                header("Location: /revenue/sales");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error Saving Invoice: " . $e->getMessage());
            }
        }
    }

    // --- 4. CANCEL INVOICE (Releases DRs) ---
    public function cancel() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            
            try {
                $db->beginTransaction();

                // A. Set DRs back to 'uninvoiced'
                $db->prepare("UPDATE delivery_receipts SET invoice_status = 'uninvoiced' 
                              WHERE id IN (SELECT dr_id FROM dr_invoice_links WHERE invoice_id=?)")
                   ->execute([$id]);
                
                // B. Remove Links
                $db->prepare("DELETE FROM dr_invoice_links WHERE invoice_id=?")->execute([$id]);

                // C. Mark Invoice as Cancelled (Zero out amount)
                $db->prepare("UPDATE sales_invoices SET status = 'cancelled', total_amount_due = 0, vat_amount = 0, vatable_sales = 0 WHERE id=?")->execute([$id]);
                
                $db->commit();
                header("Location: /revenue/sales");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
    
    // --- 5. RECORD SPOILED INVOICE (Skipped Number) ---
    public function storeSpoiled() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $invNum = $_POST['invoice_number'];
            
            // Record a dummy invoice with status 'cancelled'
            $db->prepare("INSERT INTO sales_invoices (company_id, invoice_number, date, customer_name, status, total_amount_due) VALUES (1, ?, CURDATE(), 'SPOILED / CANCELLED', 'cancelled', 0)")
               ->execute([$invNum]);
               
            // Update booklet counter so next suggestion is correct
            $booklet = $db->query("SELECT * FROM invoice_booklets WHERE '$invNum' BETWEEN series_start AND series_end")->fetch();
            if ($booklet) {
                 $newCounter = intval($invNum) + 1;
                 if ($newCounter > $booklet['current_counter']) {
                    $db->prepare("UPDATE invoice_booklets SET current_counter = ? WHERE id = ?")->execute([$newCounter, $booklet['id']]);
                 }
            }
            
            header("Location: /revenue/sales");
        }
    }
}