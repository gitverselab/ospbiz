<?php
class RtsController {

    public function index() {
        $db = Database::getInstance();
        
        // 1. GET FILTERS
        $search = $_GET['search'] ?? '';
        $plant = $_GET['plant'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        // 2. BUILD QUERY
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
        if ($fromDate) {
            $where .= " AND r.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where .= " AND r.date <= ?";
            $params[] = $toDate;
        }

        // 3. PAGINATION COUNTS
        $countSql = "SELECT COUNT(*) as total 
                     FROM rts_lines l 
                     JOIN rts_records r ON l.rts_id = r.id 
                     WHERE $where";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // 4. FETCH DATA
        $sql = "SELECT l.*, r.rd_number, r.date, r.plant_name, r.plant_code, r.po_number, r.gr_number, r.status, r.currency, r.is_vat_inc
                FROM rts_lines l
                JOIN rts_records r ON l.rts_id = r.id
                WHERE $where
                ORDER BY r.date DESC, r.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rts = $stmt->fetchAll();

        // 5. Plant Dropdown Data
        $plants = $db->query("SELECT DISTINCT plant_name FROM rts_records ORDER BY plant_name")->fetchAll();

        $filters = [
            'search' => $search, 'plant' => $plant, 
            'from' => $fromDate, 'to' => $toDate,
            'limit' => $limit, 'page' => $page, 
            'total_pages' => $totalPages, 'total_records' => $totalRecords
        ];

        $pageTitle = "RTS Management";
        $childView = ROOT_PATH . '/app/views/revenue/rts/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- MANUAL ENTRY FORM ---
    public function create() {
        $pageTitle = "Create RTS Record";
        $childView = ROOT_PATH . '/app/views/revenue/rts/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- SAVE MANUAL RTS ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Header
                $sql = "INSERT INTO rts_records (company_id, rd_number, date, plant_name, plant_code, po_number, gr_number, status, currency, is_vat_inc, reference_doc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $_POST['rd_number'], $_POST['date'], $_POST['plant_name'], 
                    $_POST['plant_code'], $_POST['po_number'], $_POST['gr_number'], 
                    $_POST['status'], $_POST['currency'], $_POST['is_vat_inc'], $_POST['reference_doc']
                ]);
                $rtsId = $db->lastInsertId();

                // 2. Lines
                $lines = json_decode($_POST['lines_json'], true);
                $lineStmt = $db->prepare("INSERT INTO rts_lines (rts_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)");
                
                foreach ($lines as $line) {
                    $qty = floatval($line['quantity']);
                    $price = floatval($line['price']);
                    $amount = $qty * $price;
                    $lineStmt->execute([$rtsId, $line['item_code'], $line['description'], $qty, $line['uom'], $price, $amount]);
                }

                $db->commit();
                header("Location: /revenue/rts");

            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }

    // --- DOWNLOAD TEMPLATE ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rts_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'RD Number', 'Date (YYYY-MM-DD)', 'Plant Code', 'Plant Name', 'Vat Inc (1=Yes)', 'PO Number', 'Orig GR Number', 'Ref Doc']);
        fputcsv($output, ['RET001', 'Damaged Goods', '5', 'PCS', 'PHP', '100.00', 'RD-2023-001', date('Y-m-d'), 'PL01', 'Manila Plant', '1', 'PO-999', 'GR-ORIG-888', 'DR-REF-123']);
        fclose($output);
        exit();
    }

    // --- CSV IMPORT ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); 

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    $rts = $db->query("SELECT id FROM rts_records WHERE rd_number = '{$row[6]}'")->fetch();
                    if (!$rts) {
                        // Auto-set status to 'received'
                        $stmt = $db->prepare("INSERT INTO rts_records (company_id, rd_number, date, plant_code, plant_name, po_number, gr_number, reference_doc, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, 'received', ?, ?)");
                        $stmt->execute([$row[6], $row[7], $row[8], $row[9], $row[11], $row[12], $row[13], $row[4], $row[10]]);
                        $rtsId = $db->lastInsertId();
                    } else {
                        $rtsId = $rts['id'];
                    }

                    $amount = floatval($row[2]) * floatval($row[5]);
                    $db->prepare("INSERT INTO rts_lines (rts_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$rtsId, $row[0], $row[1], $row[2], $row[3], $row[5], $amount]);
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

        $sql = "SELECT r.rd_number, r.date, r.plant_name, l.item_code, l.description, l.quantity, l.uom, l.price, l.amount, r.status 
                FROM rts_lines l JOIN rts_records r ON l.rts_id = r.id";
        $rows = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) fputcsv($output, $row);
        fclose($output);
        exit();
    }
}