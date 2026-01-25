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
        
        $openInvoices = [];
        $openRts = [];

        if (isset($_GET['customer'])) {
            $cust = $_GET['customer'];
            
            // 2. Get Open Invoices
            $invSql = "SELECT * FROM sales_invoices WHERE customer_name = ? AND status = 'unpaid' ORDER BY date ASC";
            $stmt = $db->prepare($invSql);
            $stmt->execute([$cust]);
            $openInvoices = $stmt->fetchAll();

            // 3. Get Open RTS (Deductions) - Assuming plant_name matches customer_name
            // We look for status 'received' (meaning approved return, not yet deducted)
            $rtsSql = "SELECT * FROM rts_records WHERE plant_name = ? AND status = 'received' ORDER BY date ASC";
            $stmtRts = $db->prepare($rtsSql);
            $stmtRts->execute([$cust]);
            
            // Calculate total amount for each RTS header by summing lines
            $rawRts = $stmtRts->fetchAll();
            foreach($rawRts as $r) {
                // Sum lines for this RTS
                $sum = $db->query("SELECT SUM(amount) as total FROM rts_lines WHERE rts_id = {$r['id']}")->fetch()['total'];
                
                // Add VAT if inclusive? Usually returns are gross. 
                // Let's assume the line amount is the value to deduct.
                // If is_vat_inc is 0, we might need to add VAT, but usually line amounts in DB are final.
                // For safety, let's recalculate based on header flag if needed, 
                // but relying on stored line amounts is safer if your previous code stored them correctly.
                $r['total_amount'] = $sum ?? 0;
                $openRts[] = $r;
            }
        }

        // 4. Get Bank Accounts
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
                $selectedRtsIds = $_POST['rts_ids'] ?? [];

                if (empty($selectedInvIds)) die("No invoices selected.");
                if (empty($bankId)) die("Please select a deposit account.");

                // 1. Calculate Invoices Total
                $placeholdersInv = str_repeat('?,', count($selectedInvIds) - 1) . '?';
                $stmtInv = $db->prepare("SELECT * FROM sales_invoices WHERE id IN ($placeholdersInv)");
                $stmtInv->execute($selectedInvIds);
                $invoices = $stmtInv->fetchAll();

                $totalGross = 0;
                $totalVatable = 0;
                foreach ($invoices as $inv) {
                    $totalGross += floatval($inv['total_amount_due']);
                    $totalVatable += floatval($inv['vatable_sales']);
                }

                // 2. Calculate RTS Deductions
                $totalRtsDeduction = 0;
                $rtsRecords = [];
                if (!empty($selectedRtsIds)) {
                    $placeholdersRts = str_repeat('?,', count($selectedRtsIds) - 1) . '?';
                    // We need to sum lines again or fetch strictly.
                    // Let's fetch records first
                    $stmtRts = $db->prepare("SELECT * FROM rts_records WHERE id IN ($placeholdersRts)");
                    $stmtRts->execute($selectedRtsIds);
                    $rtsRecords = $stmtRts->fetchAll();

                    foreach($rtsRecords as $r) {
                        // Sum lines
                        $sum = $db->query("SELECT SUM(amount) as total FROM rts_lines WHERE rts_id = {$r['id']}")->fetch()['total'];
                        // Handle VAT logic for RTS if needed (e.g. if lines are Ex-Vat). 
                        // Assuming lines are stored as Final Amounts (Inc Vat) based on RTS Controller fix.
                        $amt = floatval($sum);
                        if ($r['is_vat_inc'] == 0) { 
                            $amt = $amt * 1.12; // Add VAT if stored exclusive
                        }
                        $totalRtsDeduction += $amt;
                    }
                }

                // 3. Calculate WHT and Net
                $totalWht = $totalVatable * 0.01;
                $netReceived = $totalGross - $totalWht - $totalRtsDeduction;

                if ($netReceived < 0) die("Error: Deductions exceed payment amount.");

                // 4. Insert Remittance
                // Note: You might need to add a 'total_rts_amount' column to your DB table if you want to track it explicitly
                $insertRemit = $db->prepare("INSERT INTO payment_remittances (company_id, date, customer_name, reference_no, financial_account_id, total_gross_amount, total_wht_amount, net_amount_received) VALUES (1, ?, ?, ?, ?, ?, ?, ?)");
                $insertRemit->execute([$date, $customer, $ref, $bankId, $totalGross, $totalWht, $netReceived]);
                $remitId = $db->lastInsertId();

                // 5. Process Invoices (Mark Paid)
                $linkInv = $db->prepare("INSERT INTO remittance_invoice_links (remittance_id, invoice_id, applied_amount) VALUES (?, ?, ?)");
                $updateInv = $db->prepare("UPDATE sales_invoices SET status = 'paid' WHERE id = ?");
                foreach ($invoices as $inv) {
                    $linkInv->execute([$remitId, $inv['id'], $inv['total_amount_due']]);
                    $updateInv->execute([$inv['id']]);
                }

                // 6. Process RTS (Mark Deducted)
                // We need a link table for RTS too. If it doesn't exist, create it:
                // CREATE TABLE remittance_rts_links (id INT PK, remittance_id INT, rts_id INT, amount DECIMAL);
                $linkRts = $db->prepare("INSERT INTO remittance_rts_links (remittance_id, rts_id, amount) VALUES (?, ?, ?)");
                $updateRts = $db->prepare("UPDATE rts_records SET status = 'deducted' WHERE id = ?");
                
                // Re-loop to calculate exact amount per RTS for linking
                foreach($rtsRecords as $r) {
                    $sum = $db->query("SELECT SUM(amount) as total FROM rts_lines WHERE rts_id = {$r['id']}")->fetch()['total'];
                    $amt = floatval($sum);
                    if ($r['is_vat_inc'] == 0) $amt *= 1.12;
                    
                    $linkRts->execute([$remitId, $r['id'], $amt]);
                    $updateRts->execute([$r['id']]);
                }

                // 7. Update Bank
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance + ? WHERE id = ?")
                   ->execute([$netReceived, $bankId]);

                // 8. Journal Entry
                $entries = [];
                
                // Get Bank Code
                $bankAcc = $db->query("SELECT code FROM accounts WHERE id = (SELECT account_id FROM financial_accounts WHERE id=$bankId)")->fetch();
                $bankCode = $bankAcc['code'] ?? '1010'; 

                // Dr Cash
                $entries[] = [ 'code' => $bankCode, 'desc' => "Collection (Ref: $ref)", 'debit' => $netReceived, 'credit' => 0 ];
                
                // Dr WHT
                if ($totalWht > 0) {
                    $entries[] = [ 'code' => '1300', 'desc' => "CWT (1%)", 'debit' => $totalWht, 'credit' => 0 ];
                }

                // Dr Sales Returns (RTS)
                if ($totalRtsDeduction > 0) {
                    $entries[] = [ 'code' => '4100', 'desc' => "Returns/Deductions", 'debit' => $totalRtsDeduction, 'credit' => 0 ];
                }

                // Cr AR (Total Invoice Gross)
                $entries[] = [ 'code' => '1200', 'desc' => "Clear AR - $customer", 'debit' => 0, 'credit' => $totalGross ];

                require_once ROOT_PATH . '/app/controllers/JournalController.php';
                JournalController::post($date, $ref, "Collection from $customer", 'remittance', $remitId, $entries);

                $db->commit();
                header("Location: /revenue/remittance");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- VOID / ROLLBACK REMITTANCE ---
    public function void() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];

            try {
                $db->beginTransaction();

                // 1. Get Remittance Info
                $remit = $db->query("SELECT * FROM payment_remittances WHERE id = $id")->fetch();
                if (!$remit) die("Remittance not found.");

                // 2. Revert Bank Balance
                $db->prepare("UPDATE financial_accounts SET current_balance = current_balance - ? WHERE id = ?")
                   ->execute([$remit['net_amount_received'], $remit['financial_account_id']]);

                // 3. Revert Invoices (Set to 'unpaid')
                $db->prepare("UPDATE sales_invoices SET status = 'unpaid' 
                              WHERE id IN (SELECT invoice_id FROM remittance_invoice_links WHERE remittance_id=?)")
                   ->execute([$id]);

                // 4. Revert RTS (Set to 'received')
                // Check if table exists first to avoid error if no RTS were linked
                $db->prepare("UPDATE rts_records SET status = 'received' 
                              WHERE id IN (SELECT rts_id FROM remittance_rts_links WHERE remittance_id=?)")
                   ->execute([$id]);

                // 5. Reverse Journal Entry
                // We fetch the original journal for this remittance
                $journal = $db->query("SELECT * FROM journals WHERE reference_type = 'remittance' AND reference_id = $id")->fetch();
                if ($journal) {
                    // Create Reversal (Swap Debits and Credits)
                    $lines = $db->query("SELECT * FROM journal_lines WHERE journal_id = {$journal['id']}")->fetchAll();
                    $newEntries = [];
                    foreach($lines as $line) {
                        $newEntries[] = [
                            'code' => $line['account_code'],
                            'desc' => "VOID/REVERSAL: " . $line['description'],
                            'debit' => $line['credit_amount'], // Swap
                            'credit' => $line['debit_amount']  // Swap
                        ];
                    }
                    require_once ROOT_PATH . '/app/controllers/JournalController.php';
                    JournalController::post(date('Y-m-d'), "VOID-".$remit['reference_no'], "Void Remittance #$id", 'journal', 0, $newEntries);
                }

                // 6. Delete Links
                $db->prepare("DELETE FROM remittance_invoice_links WHERE remittance_id = ?")->execute([$id]);
                $db->prepare("DELETE FROM remittance_rts_links WHERE remittance_id = ?")->execute([$id]);

                // 7. Delete Remittance Record
                $db->prepare("DELETE FROM payment_remittances WHERE id = ?")->execute([$id]);

                $db->commit();
                header("Location: /revenue/remittance");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}