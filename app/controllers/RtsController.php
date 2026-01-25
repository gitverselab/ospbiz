<?php
class RtsController {

    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $plant = $_GET['plant'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];

        if ($search) {
            $where .= " AND (r.rd_number LIKE ? OR r.po_number LIKE ? OR r.gr_number LIKE ? OR l.item_code LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($plant) {
            $where .= " AND r.plant_name LIKE ?";
            $params[] = "%$plant%";
        }
        if ($fromDate) { $where .= " AND r.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND r.date <= ?"; $params[] = $toDate; }

        // Pagination Count
        $countSql = "SELECT COUNT(l.id) as total_count FROM rts_lines l JOIN rts_records r ON l.rts_id = r.id WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $row = $stmtCount->fetch(PDO::FETCH_ASSOC);
        $totalRecords = ($row && isset($row['total_count'])) ? (int)$row['total_count'] : 0;

        $totalPages = ceil($totalRecords / $limit);
        if ($totalPages < 1) $totalPages = 1;

        // Fetch Data
        $sql = "SELECT l.*, 
                       r.id as rts_id, r.rd_number, r.date, r.plant_name, r.plant_code, 
                       r.po_number, r.gr_number, r.status, r.currency, r.is_vat_inc
                FROM rts_lines l
                JOIN rts_records r ON l.rts_id = r.id
                WHERE $where
                ORDER BY r.date DESC, r.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rts = $stmt->fetchAll();

        // --- UPDATED: Fetch from 'customers' table instead of 'rts_records' ---
        try { 
            $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll(); 
        } catch (Exception $e) { 
            $customers = []; 
        }

        $filters = compact('search', 'plant', 'fromDate', 'toDate', 'limit', 'page', 'totalPages', 'totalRecords');
        
        // Pass $customers to the view
        $data = ['rts' => $rts, 'filters' => $filters, 'customers' => $customers];

        $pageTitle = "RTS Management";
        $childView = ROOT_PATH . '/app/views/revenue/rts/index.php';
        
        // Extract data for view to use directly
        extract($data); 
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE ---
    public function create() {
        $pageTitle = "Create RTS Record";
        $childView = ROOT_PATH . '/app/views/revenue/rts/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- EDIT ---
    public function edit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? 0;
        
        $rts = $db->query("SELECT * FROM rts_records WHERE id = $id")->fetch();
        if (!$rts) die("RTS Record not found");

        $lines = $db->query("SELECT * FROM rts_lines WHERE rts_id = $id")->fetchAll();

        $pageTitle = "Edit RTS Record";
        $childView = ROOT_PATH . '/app/views/revenue/rts/create.php'; 
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE ---
    public function store() { $this->save(false); }
    
    // --- UPDATE ---
    public function update() { $this->save(true); }

    private function save($isUpdate) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                if ($isUpdate) {
                    $id = $_POST['id'];
                    $sql = "UPDATE rts_records SET rd_number=?, date=?, plant_name=?, plant_code=?, po_number=?, gr_number=?, status=?, currency=?, is_vat_inc=?, reference_doc=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $_POST['rd_number'], $_POST['date'], $_POST['plant_name'], 
                        $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                        $_POST['status'], $_POST['currency'], $_POST['is_vat_inc'], $_POST['reference_doc'], $id
                    ]);
                    $db->prepare("DELETE FROM rts_lines WHERE rts_id = ?")->execute([$id]);
                    $rtsId = $id;
                } else {
                    $sql = "INSERT INTO rts_records (company_id, rd_number, date, plant_name, plant_code, po_number, gr_number, status, currency, is_vat_inc, reference_doc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([
                        $_POST['rd_number'], $_POST['date'], $_POST['plant_name'], 
                        $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                        $_POST['status'], $_POST['currency'], $_POST['is_vat_inc'], $_POST['reference_doc']
                    ]);
                    $rtsId = $db->lastInsertId();
                }

                $lines = json_decode($_POST['lines_json'], true);
                if (is_array($lines)) {
                    $lineStmt = $db->prepare("INSERT INTO rts_lines (rts_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($lines as $line) {
                        $qty = floatval($line['quantity']);
                        $price = floatval($line['price']);
                        $amount = $qty * $price;
                        $lineStmt->execute([$rtsId, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount]);
                    }
                }

                $db->commit();
                header("Location: /revenue/rts");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- DELETE ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $db->prepare("DELETE FROM rts_lines WHERE rts_id = ?")->execute([$id]);
            $db->prepare("DELETE FROM rts_records WHERE id = ?")->execute([$id]);
            header("Location: /revenue/rts");
        }
    }

    // --- TEMPLATE ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rts_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'RD Number', 'Date (MM/DD/YYYY)', 'Plant Code', 'Plant Name', 'Vat Inc (1=Yes)', 'PO Number', 'Reference Doc', 'Orig GR Number']);
        fputcsv($output, ['RET001', 'Damaged Goods', '5', 'PCS', 'PHP', '100.00', 'RD-2023-001', date('m/d/Y'), 'PL01', 'Manila Plant', '1', 'PO-999', 'DR-REF-123', 'GR-ORIG-888']);
        fclose($output);
        exit();
    }

    // --- IMPORT (With Customer Override) ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); 

            // GET OVERRIDE FROM POST
            $overrideCustomer = !empty($_POST['import_customer_name']) ? $_POST['import_customer_name'] : null;

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    
                    // 1. DATE FIX
                    $rawDate = isset($row[7]) ? trim($row[7]) : '';
                    $finalDate = null;
                    if (!empty($rawDate)) {
                        if (is_numeric($rawDate)) {
                            $unixDate = ($rawDate - 25569) * 86400;
                            $finalDate = gmdate("Y-m-d", $unixDate);
                        } else {
                            $cleanDate = preg_replace('/[^0-9\/\-]/', '', $rawDate);
                            $d = DateTime::createFromFormat('m/d/Y', $cleanDate);
                            if (!$d) $d = DateTime::createFromFormat('n/j/Y', $cleanDate);
                            if (!$d) $d = DateTime::createFromFormat('Y-m-d', $cleanDate);
                            if ($d) $finalDate = $d->format('Y-m-d');
                            else {
                                $ts = strtotime($cleanDate);
                                if ($ts) $finalDate = date('Y-m-d', $ts);
                            }
                        }
                    }
                    if (!$finalDate) $finalDate = date('Y-m-d');

                    // 2. CHECK HEADER
                    $rts = $db->query("SELECT id FROM rts_records WHERE rd_number = '{$row[6]}'")->fetch();
                    
                    // --- CUSTOMER NAME LOGIC ---
                    // If override is selected, use it. Otherwise, use CSV value (Column J / Index 9)
                    $plantName = $overrideCustomer ?? ($row[9] ?? 'Unknown');

                    if (!$rts) {
                        $refDoc = isset($row[12]) ? trim($row[12]) : '';
                        $grNum  = isset($row[13]) ? trim($row[13]) : '';

                        $stmt = $db->prepare("INSERT INTO rts_records (company_id, rd_number, date, plant_code, plant_name, po_number, gr_number, reference_doc, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, 'received', ?, ?)");
                        
                        $stmt->execute([
                            $row[6],        // RD Number
                            $finalDate,     // Date
                            $row[8],        // Plant Code
                            $plantName,     // Plant Name (Uses Override if available)
                            $row[11],       // PO Number
                            $grNum,         // GR Number
                            $refDoc,        // Ref Doc
                            $row[4],        // Currency
                            1               // Force VAT Inc = 1
                        ]);
                        $rtsId = $db->lastInsertId();
                    } else {
                        $rtsId = $rts['id'];
                    }

                    // 3. PRICE LOGIC FIX
                    $qty = floatval(str_replace(',', '', $row[2] ?? 0));
                    $totalExVat = floatval(str_replace(',', '', $row[5] ?? 0));

                    $unitPrice = ($qty > 0) ? ($totalExVat / $qty) : 0;
                    $finalAmount = $totalExVat * 1.12; 

                    $db->prepare("INSERT INTO rts_lines (rts_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([
                           $rtsId, 
                           $row[0], 
                           $row[1], 
                           $qty, 
                           $row[3], 
                           $unitPrice,   
                           $finalAmount 
                       ]);
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); }
            header("Location: /revenue/rts");
        }
    }

    public function export() {
        $db = Database::getInstance();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rts_export.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['RD Number', 'Date', 'Plant', 'Item Code', 'Description', 'Qty', 'UOM', 'Price', 'Total', 'Status']);
        $sql = "SELECT r.rd_number, r.date, r.plant_name, l.item_code, l.description, l.quantity, l.uom, l.price, l.amount, r.status FROM rts_lines l JOIN rts_records r ON l.rts_id = r.id";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) fputcsv($output, $row);
        fclose($output);
        exit();
    }
}