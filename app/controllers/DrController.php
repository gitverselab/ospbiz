<?php
class DrController {

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

        if ($search) {
            $where .= " AND (d.dr_number LIKE ? OR d.po_number LIKE ? OR l.gr_number LIKE ? OR l.item_code LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($customer) { $where .= " AND d.customer_name LIKE ?"; $params[] = "%$customer%"; }
        if ($fromDate) { $where .= " AND d.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND d.date <= ?"; $params[] = $toDate; }

        $countSql = "SELECT COUNT(*) as total FROM dr_lines l JOIN delivery_receipts d ON l.dr_id = d.id WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT l.*, 
                       d.id as dr_id, d.dr_number, d.date, d.customer_name, 
                       d.po_number, d.status, d.currency, d.is_vat_inc
                FROM dr_lines l
                JOIN delivery_receipts d ON l.dr_id = d.id
                WHERE $where
                ORDER BY d.date DESC, d.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $drs = $stmt->fetchAll();

        try { $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(); } catch (Exception $e) { $customers = []; }

        $filters = compact('search', 'customer', 'fromDate', 'toDate', 'limit', 'page', 'totalPages', 'totalRecords');
        $pageTitle = "DR Management";
        $childView = ROOT_PATH . '/app/views/revenue/dr/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE & EDIT ---
    public function create() {
        $db = Database::getInstance();
        try { $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(); } catch (Exception $e) { $customers = []; }
        $pageTitle = "Create DR";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function edit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? 0;
        $dr = $db->query("SELECT * FROM delivery_receipts WHERE id = $id")->fetch();
        if (!$dr) die("DR not found");
        $lines = $db->query("SELECT * FROM dr_lines WHERE dr_id = $id")->fetchAll();
        try { $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(); } catch (Exception $e) { $customers = []; }
        $pageTitle = "Edit DR";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE & UPDATE ---
    public function store() { $this->save(false); }
    public function update() { $this->save(true); }

    private function save($isUpdate) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                if ($isUpdate) {
                    $id = $_POST['id'];
                    $sql = "UPDATE delivery_receipts SET dr_number=?, date=?, customer_name=?, plant_code=?, po_number=?, status=?, currency=?, is_vat_inc=? WHERE id=?";
                    $db->prepare($sql)->execute([
                        $_POST['dr_number'], $_POST['date'], $_POST['customer_name'], 
                        $_POST['plant_code'], $_POST['po_number'], 
                        $_POST['status'], $_POST['currency'], $_POST['is_vat_inc'], $id
                    ]);
                    $db->prepare("DELETE FROM dr_lines WHERE dr_id = ?")->execute([$id]);
                    $drId = $id;
                } else {
                    $sql = "INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $db->prepare($sql)->execute([
                        $_POST['dr_number'], $_POST['date'], $_POST['customer_name'], 
                        $_POST['plant_code'], $_POST['po_number'], 
                        $_POST['status'], $_POST['currency'], $_POST['is_vat_inc']
                    ]);
                    $drId = $db->lastInsertId();
                }

                $lines = json_decode($_POST['lines_json'], true);
                if (is_array($lines)) {
                    $lineStmt = $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount, gr_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    foreach ($lines as $line) {
                        $qty = floatval($line['quantity']);
                        $price = floatval($line['price']);
                        $amount = $qty * $price;
                        $gr = $line['gr_number'] ?? $_POST['gr_number'] ?? ''; 
                        $lineStmt->execute([$drId, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount, $gr]);
                    }
                }

                $db->commit();
                header("Location: /revenue/dr");
            } catch (Exception $e) { $db->rollBack(); die($e->getMessage()); }
        }
    }

    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $db->prepare("DELETE FROM dr_lines WHERE dr_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM delivery_receipts WHERE id = ?")->execute([$id]);
            header("Location: /revenue/dr");
        }
    }

    // --- IMPORT (Fixed GR Column M, Date, & Price Logic) ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            
            // Skip Header Row
            fgetcsv($file); 

            $success = 0;
            $errors = [];
            $rowNum = 1;

            // Get override customer if selected in modal
            $overrideCustomer = !empty($_POST['import_customer_name']) ? $_POST['import_customer_name'] : null;

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    $rowNum++;
                    
                    // --- 1. FIX DATE PARSING (12/27/2025 -> 2025-12-27) ---
                    $rawDate = isset($row[7]) ? trim($row[7]) : '';
                    $finalDate = date('Y-m-d'); // Default to today if fail

                    if (!empty($rawDate)) {
                        // Try typical excel/csv formats
                        $formats = ['m/d/Y', 'n/j/Y', 'Y-m-d', 'd-m-Y'];
                        foreach ($formats as $fmt) {
                            $d = DateTime::createFromFormat($fmt, $rawDate);
                            if ($d && $d->format($fmt) == $rawDate) {
                                $finalDate = $d->format('Y-m-d');
                                break;
                            }
                        }
                    }

                    // --- 2. MAP COLUMNS ---
                    $drNum = isset($row[6]) ? trim($row[6]) : '';
                    if (empty($drNum)) continue; // Skip empty rows

                    // Customer Name: Override or CSV Column J (Index 9)
                    $custName = $overrideCustomer ?? ($row[9] ?? 'Unknown');
                    
                    // GR Number: User specified Column M (Index 12)
                    $grNumHeader = isset($row[12]) ? trim($row[12]) : ''; 

                    // --- 3. CREATE/FIND HEADER ---
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '$drNum'")->fetch();
                    
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, 'delivered', ?, ?)");
                        
                        // Mapping:
                        // $row[8] = Plant Code (Col I)
                        // $row[11] = PO Number (Col L)
                        // $row[10] = Vat Inc (Col K) -> 1 or 0
                        
                        $stmt->execute([
                            $drNum, 
                            $finalDate, 
                            $custName, 
                            $row[8] ?? '', 
                            $row[11] ?? '', 
                            $grNumHeader, // Save GR to Header too
                            'PHP', 
                            $row[10] ?? 0
                        ]);
                        $drId = $db->lastInsertId();
                        $success++;
                    } else {
                        $drId = $dr['id'];
                    }

                    // --- 4. PRICE LOGIC (Total Ex-Vat) ---
                    // User Rule: "Price from import is VAT EX price for the declared qty"
                    // Therefore: CSV Value = LINE TOTAL (Ex VAT)
                    
                    $qty = floatval(str_replace(',', '', $row[2] ?? 0));
                    $csvTotalExVat = floatval(str_replace(',', '', $row[5] ?? 0)); // Col F
                    $isVatInc = $row[10] ?? 0;

                    // Calculate Unit Price (Backwards)
                    $unitPrice = ($qty > 0) ? ($csvTotalExVat / $qty) : 0;

                    // Calculate Final Amount for DB
                    // If DB expects VAT Inclusive amount when is_vat_inc=1:
                    if ($isVatInc == 1) {
                        $finalAmount = $csvTotalExVat * 1.12; // Add 12% VAT
                    } else {
                        $finalAmount = $csvTotalExVat; // Already Ex-Vat
                    }

                    // --- 5. INSERT LINE ITEM ---
                    // GR Number: Column M (Index 12)
                    $lineGr = isset($row[12]) ? trim($row[12]) : '';

                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount, gr_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute([
                           $drId, 
                           $row[0] ?? '', // Item Code
                           $row[1] ?? '', // Description
                           $qty, 
                           $row[3] ?? '', // UOM
                           $unitPrice,    // Saved as Unit Price
                           $finalAmount,  // Saved as Total Amount (Inc VAT if applicable)
                           $lineGr        // GR Number on Line Item
                       ]);
                }
                $db->commit();
                
                if (session_status() == PHP_SESSION_NONE) session_start();
                $_SESSION['import_msg'] = "Imported $success New DRs successfully.";

            } catch (Exception $e) { 
                $db->rollBack(); 
                die("Import Failed at Row $rowNum: " . $e->getMessage()); 
            }
            header("Location: /revenue/dr");
        }
    }
    
    // --- TEMPLATE & EXPORT ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'GR Number', 'Price', 'DR Number', 'Date (MM/DD/YYYY)', 'Plant Code', 'Customer', 'Vat Inc (1=Yes)', 'PO Number']);
        fputcsv($output, ['ITEM001', 'Sample Item', '10', 'PCS', 'GR-888', '100.00', 'DR-2023-001', date('m/d/Y'), 'PL01', 'Customer Name', '1', 'PO-999']);
        fclose($output);
        exit();
    }

    public function export() {
        $db = Database::getInstance();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc', 'Date', 'Plant Code', 'Customer', 'Vat Inc', 'PO Number', 'GR Number', 'Status']);
        $sql = "SELECT l.item_code, l.description, l.quantity, l.uom, d.currency, l.price, d.dr_number, d.date, d.plant_code, d.customer_name, d.is_vat_inc, d.po_number, d.gr_number, d.status FROM delivery_receipts d JOIN dr_lines l ON d.id = l.dr_id";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) fputcsv($output, $row);
        fclose($output);
        exit();
    }
}