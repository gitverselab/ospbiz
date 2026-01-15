<?php
class RtsController {
    public function index() {
        $db = Database::getInstance();
        $stmt = $db->query("SELECT * FROM rts_records ORDER BY date DESC");
        $rts = $stmt->fetchAll();
        $pageTitle = "RTS Management";
        $childView = ROOT_PATH . '/app/views/revenue/rts/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function template() {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="rts_template.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, ['Item Code', 'Description', 'Qty', 'UOM', 'Currency', 'Price', 'Reference Doc', 'GR Date', 'Plant Code', 'Plant Name', 'Vat Inc', 'PO Number', 'RD Number', 'GR Number']);
        fclose($output);
        exit();
    }

    public function import() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
            $db = Database::getInstance();
            $file = fopen($_FILES['csv_file']['tmp_name'], 'r');
            fgetcsv($file); // Skip Header
            $db->beginTransaction();
            try {
                while (($row = fgetcsv($file)) !== FALSE) {
                    // Check if RTS header exists
                    $rts = $db->query("SELECT id FROM rts_records WHERE rd_number = '{$row[12]}'")->fetch();
                    if (!$rts) {
                        $stmt = $db->prepare("INSERT INTO rts_records (company_id, rd_number, date, plant_code, plant_name, po_number, gr_number, reference_doc, currency, is_vat_inc) VALUES (1, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$row[12], $row[7], $row[8], $row[9], $row[11], $row[13], $row[6], $row[4], $row[10]]);
                        $rtsId = $db->lastInsertId();
                    } else { $rtsId = $rts['id']; }

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
         // Similar logic to DR Export but querying rts_records/rts_lines
         // (Implementation abbreviated for brevity)
    }
}