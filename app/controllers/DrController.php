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

        // --- PAGINATION FIX (FAIL-SAFE) ---
        // 1. Give the count a specific name: 'total_count'
        // 2. Fetch as an Associative Array to guarantee we get the number
        $countSql = "SELECT COUNT(*) as total_count FROM dr_lines l JOIN delivery_receipts d ON l.dr_id = d.id WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $row = $stmtCount->fetch(PDO::FETCH_ASSOC);
        
        // Force integer cast. If row is false, default to 0.
        $totalRecords = ($row && isset($row['total_count'])) ? (int)$row['total_count'] : 0;

        $totalPages = ceil($totalRecords / $limit);
        if ($totalPages < 1) $totalPages = 1;

        // FETCH DATA
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

    // --- IMPORT (With Safe Date & Price Fixes) ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            
            // Skip Header
            fgetcsv($file); 

            $success = 0;
            $rowNum = 1;
            
            $overrideCustomer = !empty($_POST['import_customer_name']) ? $_POST['import_customer_name'] : null;

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    $rowNum++;
                    
                    // --- DATE FIX ---
                    $rawDate = isset($row[7]) ? trim($row[7]) : '';
                    $finalDate = date('Y-m-d'); 

                    if (!empty($rawDate)) {
                        $cleanDate = preg_replace('/[^0-9\/\-]/', '', $rawDate);
                        
                        // Try strict formats first
                        $d = DateTime::createFromFormat('m/d/Y', $cleanDate);
                        if (!$d) $d = DateTime::createFromFormat('n/j/Y', $cleanDate);
                        if (!$d) $d = DateTime::createFromFormat('Y-m-d', $cleanDate);

                        if ($d) {
                            $finalDate = $d->format('Y-m-d');
                        } elseif (is_numeric($cleanDate)) {
                            $unixDate = ($cleanDate - 25569) * 86400;
                            $finalDate = gmdate("Y-m-d", $unixDate);
                        } else {
                            $ts = strtotime($cleanDate);
                            if ($ts) $finalDate = date('Y-m-d', $ts);
                        }
                    }

                    // --- COLUMNS ---
                    $drNum = isset($row[6]) ? trim($row[6]) : '';
                    if (empty($drNum)) continue; 

                    $custName = $overrideCustomer ?? ($row[9] ?? 'Unknown');
                    $grNum = isset($row[12]) ? trim($row[12]) : ''; 

                    // --- HEADER ---
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '$drNum'")->fetch();
                    
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, 'delivered', ?, ?)");
                        $stmt->execute([$drNum, $finalDate, $custName, $row[8] ?? '', $row[11] ?? '', $grNum, 'PHP', 1]);
                        $drId = $db->lastInsertId();
                        $success++;
                    } else {
                        $drId = $dr['id'];
                    }

                    // --- PRICE ---
                    $qty = floatval(str_replace(',', '', $row[2] ?? 0));
                    $totalExVat = floatval(str_replace(',', '', $row[5] ?? 0)); 
                    $unitPrice = ($qty > 0) ? ($totalExVat / $qty) : 0;
                    $finalAmount = $totalExVat * 1.12; 

                    // --- LINE ---
                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount, gr_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$drId, $row[0] ?? '', $row[1] ?? '', $qty, $row[3] ?? '', $unitPrice, $finalAmount, $grNum]);
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
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'GR Number', 'Price', 'DR Number', 'Date (MM/DD/YYYY)', 'Plant Code', 'Customer', 'Vat Inc (1=Yes)', 'PO Number', 'GR Number']);
        fputcsv($output, ['ITEM001', 'Sample Item', '10', 'PCS', 'GR-888', '100.00', 'DR-2023-001', date('m/d/Y'), 'PL01', 'Customer Name', '1', 'PO-999', 'GR-999']);
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