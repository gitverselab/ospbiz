<?php
class DrController {

    public function index() {
        $db = Database::getInstance();
        $search = $_GET['search'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];
        if ($search) {
            $where .= " AND (dr_number LIKE ? OR customer_name LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }

        $count = $db->prepare("SELECT COUNT(*) as total FROM delivery_receipts WHERE $where");
        $count->execute($params);
        $totalRecords = $count->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $stmt = $db->prepare("SELECT * FROM delivery_receipts WHERE $where ORDER BY date DESC LIMIT $limit OFFSET $offset");
        $stmt->execute($params);
        $drs = $stmt->fetchAll();

        $pageTitle = "DR Management";
        $childView = ROOT_PATH . '/app/views/revenue/dr/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
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

    // --- DOWNLOAD TEMPLATE ---
    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="dr_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc', 'GR Date (YYYY-MM-DD)', 'Plant Code', 'Plant Name', 'Vat Inc (1=Yes,0=No)', 'PO Number', 'GR Number', 'Status (pending/delivered)']);
        // Add sample row
        fputcsv($output, ['ITEM001', 'Sample Item', '10', 'PCS', 'PHP', '100.00', 'DR-2023-001', date('Y-m-d'), 'PL01', 'Manila Plant', '1', 'PO-999', 'GR-888', 'delivered']);
        fclose($output);
        exit();
    }

    // --- CSV IMPORT ---
    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); // Skip Header

            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    // 0=Code, 1=Desc, 2=Qty, 3=UOM, 4=Curr, 5=Price, 6=Ref, 7=Date, 8=PCode, 9=PName, 10=Vat, 11=PO, 12=GR, 13=Status
                    
                    // Check if DR exists to group items, else create new
                    $dr = $db->query("SELECT id FROM delivery_receipts WHERE dr_number = '{$row[6]}'")->fetch();
                    if (!$dr) {
                        $stmt = $db->prepare("INSERT INTO delivery_receipts (company_id, dr_number, date, customer_name, plant_code, po_number, gr_number, status, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$row[6], $row[7], $row[9], $row[8], $row[11], $row[12], $row[13], $row[4], $row[10]]);
                        $drId = $db->lastInsertId();
                    } else {
                        $drId = $dr['id'];
                    }

                    // Insert Line
                    $amount = floatval($row[2]) * floatval($row[5]);
                    $db->prepare("INSERT INTO dr_lines (dr_id, item_code, description, quantity, uom, price, amount) VALUES (?, ?, ?, ?, ?, ?, ?)")
                       ->execute([$drId, $row[0], $row[1], $row[2], $row[3], $row[5], $amount]);
                }
                $db->commit();
            } catch (Exception $e) { $db->rollBack(); echo "Error: " . $e->getMessage(); }
            header("Location: /revenue/dr");
        }
    }
}