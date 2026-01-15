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

        // 2. BUILD QUERY (Join Lines + Headers)
        $where = "1=1";
        $params = [];

        // Search: Matches DR, PO, or Item Code (as requested)
        if ($search) {
            $where .= " AND (d.dr_number LIKE ? OR d.po_number LIKE ? OR d.gr_number LIKE ? OR l.item_code LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($customer) {
            $where .= " AND d.customer_name LIKE ?";
            $params[] = "%$customer%";
        }
        if ($fromDate) {
            $where .= " AND d.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where .= " AND d.date <= ?";
            $params[] = $toDate;
        }

        // 3. COUNT TOTAL (For Pagination)
        $countSql = "SELECT COUNT(*) as total 
                     FROM dr_lines l 
                     JOIN delivery_receipts d ON l.dr_id = d.id 
                     WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. FETCH DATA (Matches Screenshot columns)
        // We select Line details + Header details
        $sql = "SELECT l.*, d.dr_number, d.date, d.customer_name, d.po_number, d.gr_number, d.status, d.currency, d.is_vat_inc
                FROM dr_lines l
                JOIN delivery_receipts d ON l.dr_id = d.id
                WHERE $where
                ORDER BY d.date DESC, d.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $drs = $stmt->fetchAll();

        // 5. Customer Dropdown Data (Unique list from existing DRs)
        $customers = $db->query("SELECT DISTINCT customer_name FROM delivery_receipts ORDER BY customer_name")->fetchAll();

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

    // --- MANUAL ENTRY FORM ---
    public function create() {
        $pageTitle = "Create Delivery Receipt";
        $childView = ROOT_PATH . '/app/views/revenue/dr/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE MANUAL DR ---
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
                    $_POST['status'], // Manual allows status selection
                    $_POST['currency'], $_POST['is_vat_inc']
                ]);
                $drId = $db->lastInsertId();

                // 2. Lines
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

    // --- CSV IMPORT (Updated: Auto 'Delivered') ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); 

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    // Check if DR exists
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '{$row[6]}'")->fetch();
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, 'delivered', ?, ?)");
                        // Status hardcoded to 'delivered'
                        $stmt->execute([$row[6], $row[7], $row[9], $row[8], $row[11], $row[12], $row[4], $row[10]]);
                        $drId = $db->lastInsertId();
                    } else {
                        $drId = $dr['id'];
                    }

                    $amount = floatval($row[2]) * floatval($row[5]);
                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$drId, $row[0], $row[1], $row[2], $row[3], $row[5], $amount]);
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); }
            header("Location: /revenue/dr");
        }
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