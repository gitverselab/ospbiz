<?php
class SalesController {

    // --- LIST INVOICES ---
    public function index() {
        $db = Database::getInstance();
        $search = $_GET['search'] ?? '';
        $where = "1=1";
        $params = [];

        if($search) {
            $where .= " AND (invoice_number LIKE ? OR customer_name LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        $sql = "SELECT * FROM sales_invoices WHERE $where ORDER BY date DESC";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $invoices = $stmt->fetchAll();

        $pageTitle = "Sales Invoices";
        $childView = ROOT_PATH . '/app/views/revenue/sales/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE PAGE ---
    public function create() {
        $db = Database::getInstance();
        
        // 1. Get Distinct Customers who have UNINVOICED DRs
        $customers = $db->query("SELECT DISTINCT customer_name FROM delivery_receipts WHERE invoice_status = 'uninvoiced' ORDER BY customer_name")->fetchAll();
        
        // 2. If a customer is selected, get their DRs
        $openDrs = [];
        if (isset($_GET['customer'])) {
            $cust = $_GET['customer'];
            // Fetch DRs and calculate their total amounts
            $drSql = "SELECT d.*, 
                      (SELECT SUM(amount) FROM dr_lines WHERE dr_id = d.id) as grand_total 
                      FROM delivery_receipts d 
                      WHERE d.customer_name = ? AND d.invoice_status = 'uninvoiced'
                      ORDER BY d.date ASC";
            $stmt = $db->prepare($drSql);
            $stmt->execute([$cust]);
            $openDrs = $stmt->fetchAll();
        }

        // Get Invoice Settings for defaults
        $settings = $db->query("SELECT * FROM receipt_settings LIMIT 1")->fetch();

        $pageTitle = "Create Sales Invoice";
        $childView = ROOT_PATH . '/app/views/revenue/sales/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE INVOICE ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Calculate Totals from selected DRs
                $selectedDrIds = $_POST['dr_ids'] ?? []; // Array of IDs
                if (empty($selectedDrIds)) die("No DRs selected.");

                $placeholders = str_repeat('?,', count($selectedDrIds) - 1) . '?';
                
                // Get all lines from these DRs
                $linesSql = "SELECT l.*, d.dr_number, d.is_vat_inc 
                             FROM dr_lines l 
                             JOIN delivery_receipts d ON l.dr_id = d.id 
                             WHERE d.id IN ($placeholders)";
                $stmtLines = $db->prepare($linesSql);
                $stmtLines->execute($selectedDrIds);
                $allLines = $stmtLines->fetchAll();

                $vatable = 0;
                $vatAmount = 0;
                $totalDue = 0;

                foreach ($allLines as $line) {
                    $amount = floatval($line['amount']);
                    $totalDue += $amount;
                    
                    // VAT Logic (Standard 12%)
                    if ($line['is_vat_inc']) {
                        $net = $amount / 1.12;
                        $vat = $amount - $net;
                    } else {
                        $net = $amount;
                        $vat = $amount * 0.12;
                        $totalDue += $vat; // Add VAT to total if not included
                    }
                    $vatable += $net;
                    $vatAmount += $vat;
                }

                // 2. Insert Invoice Header
                $sql = "INSERT INTO sales_invoices (company_id, invoice_number, date, customer_name, tin, address, business_style, terms, vatable_sales, vat_amount, total_amount_due) 
                        VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['invoice_number'], $_POST['date'], $_POST['customer_name'], 
                    $_POST['tin'], $_POST['address'], $_POST['business_style'], $_POST['terms'],
                    $vatable, $vatAmount, $totalDue
                ]);
                $invId = $db->lastInsertId();

                // 3. Insert Invoice Lines & Link DRs
                $insertLine = $db->prepare("INSERT INTO sales_invoice_lines (invoice_id, source_dr_number, item_code, description, quantity, uom, unit_price, amount) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($allLines as $line) {
                    $insertLine->execute([
                        $invId, $line['dr_number'], $line['item_code'], $line['description'], 
                        $line['quantity'], $line['uom'], $line['price'], $line['amount']
                    ]);
                }

                // 4. Mark DRs as Invoiced and Link them
                $linkStmt = $db->prepare("INSERT INTO dr_invoice_links (invoice_id, dr_id) VALUES (?, ?)");
                $updateDr = $db->prepare("UPDATE delivery_receipts SET invoice_status = 'invoiced' WHERE id = ?");

                foreach ($selectedDrIds as $drId) {
                    $linkStmt->execute([$invId, $drId]);
                    $updateDr->execute([$drId]);
                }

                $db->commit();
                header("Location: /revenue/sales");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}