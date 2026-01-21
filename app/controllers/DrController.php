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
            $where .= " AND (d.dr_number LIKE ? OR d.po_number LIKE ? OR d.gr_number LIKE ? OR l.item_code LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($customer) {
            $where .= " AND d.customer_name LIKE ?";
            $params[] = "%$customer%";
        }
        if ($fromDate) { $where .= " AND d.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $where .= " AND d.date <= ?"; $params[] = $toDate; }

        $countSql = "SELECT COUNT(*) as total 
                     FROM dr_lines l 
                     JOIN delivery_receipts d ON l.dr_id = d.id 
                     WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // FETCH DATA
        $sql = "SELECT l.*, 
                       d.id as dr_id, d.dr_number, d.date, d.customer_name, 
                       d.po_number, d.gr_number, d.status, d.currency, d.is_vat_inc
                FROM dr_lines l
                JOIN delivery_receipts d ON l.dr_id = d.id
                WHERE $where
                ORDER BY d.date DESC, d.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $drs = $stmt->fetchAll();

        // Customer Dropdown
        try {
            $customers = $db->query("SELECT DISTINCT customer_name FROM delivery_receipts ORDER BY customer_name")->fetchAll();
        } catch (Exception $e) { $customers = []; }

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

    public function create() {
        $pageTitle = "Create Delivery Receipt";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $sql = "INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['dr_number'], $_POST['date'], $_POST['customer_name'], 
                    $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                    $_POST['status'], $_POST['currency'], $_POST['is_vat_inc']
                ]);
                $drId = $db->lastInsertId();

                $lines = json_decode($_POST['lines_json'], true);
                $lineStmt = $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($lines as $line) {
                    $qty = floatval($line['quantity']);
                    $price = floatval($line['price']);
                    $amount = $qty * $price;
                    $lineStmt->execute([$drId, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount]);
                }

                $db->commit();
                header("Location: /revenue/dr");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- IMPORT (Fixed Date Parser) ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); // Skip Header

            $success = 0;
            $overrideCustomer = !empty($_POST['import_customer_name']) ? $_POST['import_customer_name'] : null;

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    
                    // --- 1. DATE FIX (Handles 12/27/2025 correctly) ---
                    $rawDate = isset($row[7]) ? trim($row[7]) : '';
                    $finalDate = date('Y-m-d'); // Default fallback

                    if (!empty($rawDate)) {
                        // Check for standard US Date Format (MM/DD/YYYY)
                        if (strpos($rawDate, '/') !== false) {
                            $parts = explode('/', $rawDate);
                            if (count($parts) == 3) {
                                // $parts[0] = Month, $parts[1] = Day, $parts[2] = Year
                                $finalDate = date("Y-m-d", strtotime($rawDate));
                            }
                        } 
                        // Check for Excel Serial Number
                        elseif (is_numeric($rawDate)) {
                            $unixDate = ($rawDate - 25569) * 86400;
                            $finalDate = gmdate("Y-m-d", $unixDate);
                        }
                    }

                    // --- 2. MAP COLUMNS ---
                    $drNum = isset($row[6]) ? trim($row[6]) : '';
                    if (empty($drNum)) continue; 

                    $custName = $overrideCustomer ?? ($row[9] ?? 'Unknown');
                    $grNum = isset($row[12]) ? trim($row[12]) : ''; 

                    // --- 3. CREATE HEADER ---
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '$drNum'")->fetch();
                    
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, 'delivered', ?, ?)");
                        $stmt->execute([
                            $drNum, $finalDate, $custName, 
                            $row[8] ?? '', $row[11] ?? '', $grNum, 'PHP', 1
                        ]);
                        $drId = $db->lastInsertId();
                        $success++;
                    } else {
                        $drId = $dr['id'];
                    }

                    // --- 4. PRICE LOGIC ---
                    $qty = floatval(str_replace(',', '', $row[2] ?? 0));
                    $totalExVat = floatval(str_replace(',', '', $row[5] ?? 0)); 
                    $unitPrice = ($qty > 0) ? ($totalExVat / $qty) : 0;
                    $finalAmount = $totalExVat * 1.12; 

                    // --- 5. INSERT LINE ---
                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount, gr_number) VALUES (?, ?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$drId, $row[0] ?? '', $row[1] ?? '', $qty, $row[3] ?? '', $unitPrice, $finalAmount, $grNum]);
                }
                $db->commit();
                
                if (session_status() == PHP_SESSION_NONE) session_start();
                $_SESSION['import_msg'] = "Imported $success New DRs successfully.";

            } catch (Exception $e) { 
                $db->rollBack(); 
                die("Import Failed: " . $e->getMessage()); 
            }
            header("Location: /revenue/dr");
        }
    }
    
    // --- TEMPLATE & EXPORT ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc (DR)', 'GR Date (MM/DD/YYYY)', 'Plant Code', 'Plant Name', 'Vat Inc (1=Yes)', 'PO Number', 'GR Number']);
        fputcsv($output, ['ITEM001', 'Sample Item', '10', 'PCS', 'PHP', '100.00', 'DR-2023-001', '12/25/2025', 'PL01', 'Manila Plant', '1', 'PO-999', 'GR-888']);
        fclose($output);
        exit();
    }

    public function export() {
        $db = Database::getInstance();
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_export.csv"');
        
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc', 'Date', 'Plant Code', 'Customer', 'Vat Inc', 'PO Number', 'GR Number', 'Status']);

        $sql = "SELECT l.item_code, l.description, l.quantity, l.uom, d.currency, l.price, d.dr_number, d.date, d.plant_code, d.customer_name, d.is_vat_inc, d.po_number, d.gr_number, d.status 
                FROM delivery_receipts d JOIN dr_lines l ON d.id = l.dr_id";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        
        // Fix Date Format in Export to match user request (MM/DD/YYYY)
        foreach ($rows as $row) {
             $row['date'] = date('m/d/Y', strtotime($row['date'])); 
             fputcsv($output, $row);
        }
        fclose($output);
        exit();
    }
}