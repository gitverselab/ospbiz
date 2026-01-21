<?php
class DrController {

    public function index() {
        $db = Database::getInstance();
        
        // 1. GET FILTERS
        $search = $_GET['search'] ?? '';
        $customer = $_GET['customer'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. BUILD QUERY
        $where = "1=1";
        $params = [];

        if ($search) {
            $where .= " AND (d.dr_number LIKE ? OR d.po_number LIKE ? OR d.gr_number LIKE ? OR l.item_code LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($customer) {
            $where .= " AND d.customer_name LIKE ?";
            $params[] = "%$customer%";
        }
        if ($fromDate) { $where .= " AND d.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND d.date <= ?"; $params[] = $toDate; }

        // 3. COUNT
        // DISTINCT d.id is important because joining lines multiplies rows
        $countSql = "SELECT COUNT(DISTINCT d.id) as total 
                     FROM delivery_receipts d 
                     LEFT JOIN dr_lines l ON l.dr_id = d.id 
                     WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. FETCH DATA (Group by DR to avoid duplicate rows in the table list)
        $sql = "SELECT d.*, SUM(l.amount) as total_amount
                FROM delivery_receipts d
                LEFT JOIN dr_lines l ON l.dr_id = d.id
                WHERE $where
                GROUP BY d.id
                ORDER BY d.date DESC, d.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $drs = $stmt->fetchAll();

        // 5. Customer Dropdown (Try to get from Master Customers table if exists, else DISTINCT)
        try {
            $customers = $db->query("SELECT name as customer_name FROM customers ORDER BY name")->fetchAll();
        } catch (Exception $e) {
            $customers = $db->query("SELECT DISTINCT customer_name FROM delivery_receipts ORDER BY customer_name")->fetchAll();
        }

        $filters = [
            'search' => $search, 'customer' => $customer, 
            'from' => $fromDate, 'to' => $toDate,
            'limit' => $limit, 'page' => $page, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "DR Management";
        $childView = ROOT_PATH . '/app/views/revenue/dr/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE FORM ---
    public function create() {
        $db = Database::getInstance();
        // Fetch real customers for the dropdown
        try {
            $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll();
        } catch (Exception $e) { $customers = []; }

        $pageTitle = "Create Delivery Receipt";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE MANUAL DR ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Header
                $sql = "INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['dr_number'], $_POST['date'], $_POST['customer_name'], 
                    $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                    $_POST['status'], $_POST['currency'], $_POST['is_vat_inc']
                ]);
                $drId = $db->lastInsertId();

                // 2. Lines
                $lines = json_decode($_POST['lines_json'], true);
                if (is_array($lines)) {
                    $lineStmt = $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($lines as $line) {
                        $qty = floatval($line['quantity']);
                        $price = floatval($line['price']);
                        $amount = $qty * $price;
                        $lineStmt->execute([$drId, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount]);
                    }
                }

                $db->commit();
                header("Location: /revenue/dr");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error: " . $e->getMessage());
            }
        }
    }

    // --- EDIT FORM ---
    public function edit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? 0;
        
        $dr = $db->query("SELECT * FROM delivery_receipts WHERE id = $id")->fetch();
        if (!$dr) die("DR not found");

        $lines = $db->query("SELECT * FROM dr_lines WHERE dr_id = $id")->fetchAll();
        
        // Fetch customers
        try {
            $customers = $db->query("SELECT * FROM customers ORDER BY name")->fetchAll();
        } catch (Exception $e) { $customers = []; }

        $pageTitle = "Edit Delivery Receipt";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php'; // Reuse create view
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- UPDATE DR ---
    public function update() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();
                $id = $_POST['id'];

                // 1. Update Header
                $sql = "UPDATE delivery_receipts SET dr_number=?, date=?, customer_name=?, plant_code=?, po_number=?, gr_number=?, status=?, currency=?, is_vat_inc=? WHERE id=?";
                $db->prepare($sql)->execute([
                    $_POST['dr_number'], $_POST['date'], $_POST['customer_name'], 
                    $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                    $_POST['status'], $_POST['currency'], $_POST['is_vat_inc'], $id
                ]);

                // 2. Replace Lines (Delete all old, insert new)
                $db->prepare("DELETE FROM dr_lines WHERE dr_id = ?")->execute([$id]);

                $lines = json_decode($_POST['lines_json'], true);
                if (is_array($lines)) {
                    $lineStmt = $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                    foreach ($lines as $line) {
                        $qty = floatval($line['quantity']);
                        $price = floatval($line['price']);
                        $amount = $qty * $price;
                        $lineStmt->execute([$id, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount]);
                    }
                }

                $db->commit();
                header("Location: /revenue/dr");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error: " . $e->getMessage());
            }
        }
    }

    // --- DELETE DR ---
    public function delete() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            $id = $_POST['id'];
            $db->prepare("DELETE FROM delivery_receipts WHERE id = ?")->execute([$id]);
            header("Location: /revenue/dr");
        }
    }

    // --- SMART IMPORT (Fixes Dates & Duplicates) ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); // Skip Header

            $success = 0;
            $errors = [];
            $rowNum = 1;

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    $rowNum++;
                    // Map CSV Columns (Adjust indices if your CSV is different)
                    // 0:Code, 1:Desc, 2:Qty, 3:UOM, 4:GR#, 5:Price, 6:DR#, 7:Date, 8:Plant, 9:Cust, 10:Vat, 11:PO
                    
                    $drNum = trim($row[6]);
                    $rawDate = trim($row[7]);
                    
                    // FIX: Convert Date format (MM/DD/YYYY -> YYYY-MM-DD)
                    $dateObj = DateTime::createFromFormat('m/d/Y', $rawDate);
                    if (!$dateObj) {
                        // Try standard format just in case
                        $dateObj = DateTime::createFromFormat('Y-m-d', $rawDate);
                    }
                    $finalDate = $dateObj ? $dateObj->format('Y-m-d') : date('Y-m-d');

                    if (empty($drNum)) {
                        $errors[] = "Row $rowNum: Missing DR Number.";
                        continue;
                    }

                    // Check/Create Header
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '$drNum'")->fetch();
                    
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, 'delivered', ?, ?)");
                        // Defaults: Currency=PHP, Vat=1
                        $stmt->execute([$drNum, $finalDate, $row[9], $row[8], $row[11], $row[4], 'PHP', $row[10]]);
                        $drId = $db->lastInsertId();
                        $success++;
                    } else {
                        $drId = $dr['id'];
                        // Optional: Update status or details of existing DR?
                    }

                    // Insert Line
                    $qty = floatval(str_replace(',', '', $row[2]));
                    $price = floatval(str_replace(',', '', $row[5]));
                    $amount = $qty * $price;

                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$drId, $row[0], $row[1], $qty, $row[3], $price, $amount]);
                }
                $db->commit();
                
                // Store result in Session to show on index
                session_start();
                $_SESSION['import_msg'] = "Imported $success DRs successfully. " . count($errors) . " errors.";
                $_SESSION['import_errors'] = $errors;

            } catch (Exception $e) { 
                $db->rollBack(); 
                die("Import Failed: " . $e->getMessage());
            }
            header("Location: /revenue/dr");
        }
    }

    // --- DOWNLOAD TEMPLATE (Updated: No Status Column) ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_template.csv"');
        $output = fopen('php://output', 'w');
        // Removed 'Status' column as requested
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc (DR)', 'GR Date (YYYY-MM-DD)', 'Plant Code', 'Plant Name', 'Vat Inc (1=Yes,0=No)', 'PO Number', 'GR Number']);
        fputcsv($output, ['ITEM001', 'Sample Item', '10', 'PCS', 'PHP', '100.00', 'DR-2023-001', date('Y-m-d'), 'PL01', 'Manila Plant', '1', 'PO-999', 'GR-888']);
        fclose($output);
        exit();
    }

    // --- CSV EXPORT ---
    public function export() {
        $db = Database::getInstance();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_export.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc', 'GR Date', 'Plant Code', 'Plant Name', 'Vat Inc', 'PO Number', 'GR Number', 'Status']);

        $sql = "SELECT l.item_code, l.description, l.quantity, l.uom, d.currency, l.price, d.dr_number, d.date, d.plant_code, d.customer_name, d.is_vat_inc, d.po_number, d.gr_number, d.status 
                FROM delivery_receipts d JOIN dr_lines l ON d.id = l.dr_id";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        foreach ($rows as $row) fputcsv($output, $row);
        fclose($output);
        exit();
    }
}