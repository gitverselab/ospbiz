<?php
class PurchaseController {

    public function index() {
        $db = Database::getInstance();
        
        // --- 1. GET PARAMETERS ---
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $status = $_GET['status'] ?? '';
        
        // Pagination Settings
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10; // Default 10 lines
        $offset = ($page - 1) * $limit;

        // --- 2. BUILD QUERY ---
        // Base Condition
        $whereSql = "1=1"; 
        $params = [];

        // Apply Filters
        if ($search) {
            $whereSql .= " AND (po.po_number LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) {
            $whereSql .= " AND po.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $whereSql .= " AND po.date <= ?";
            $params[] = $toDate;
        }
        if ($status) {
            $whereSql .= " AND po.status = ?";
            $params[] = $status;
        }

        // --- 3. COUNT TOTAL (For Pagination) ---
        $countSql = "SELECT COUNT(*) as total 
                     FROM purchase_orders po
                     LEFT JOIN suppliers s ON po.supplier_id = s.id
                     WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // --- 4. FETCH DATA ---
        $sql = "SELECT po.*, s.name as supplier_name 
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE $whereSql
                ORDER BY po.date DESC, po.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pos = $stmt->fetchAll();

        // Pass all filter data back to view so inputs stay filled
        $filters = [
            'search' => $search, 
            'from' => $fromDate, 
            'to' => $toDate, 
            'status' => $status,
            'limit' => $limit,
            'page' => $page,
            'total_pages' => $totalPages,
            'total_records' => $totalRecords
        ];

        $pageTitle = "Purchase Orders";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();
        $items = $db->query("SELECT * FROM items")->fetchAll(); 

        $pageTitle = "Create Purchase Order";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                // 1. Create PO Header
                $sql = "INSERT INTO purchase_orders (company_id, supplier_id, po_number, date, expected_delivery_date, status, total_amount) VALUES (?, ?, ?, ?, ?, 'open', ?)";
                $stmt = $db->prepare($sql);
                
                $lines = json_decode($_POST['lines_json'], true);
                $total = array_sum(array_column($lines, 'amount'));
                
                // Handle optional delivery date
                $deliveryDate = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;

                $stmt->execute([1, $_POST['supplier_id'], $_POST['po_number'], $_POST['date'], $deliveryDate, $total]);
                $poId = $db->lastInsertId();

                // 2. Create PO Lines
                $lineSql = "INSERT INTO purchase_order_lines (purchase_order_id, description, quantity, unit_price, amount) VALUES (?, ?, ?, ?, ?)";
                $lineStmt = $db->prepare($lineSql);
                
                foreach ($lines as $line) {
                    $lineStmt->execute([$poId, $line['description'], $line['quantity'], $line['unit_price'], $line['amount']]);
                }

                $db->commit();
                header("Location: /expenses/purchases");
            } catch (Exception $e) {
                $db->rollBack();
                die($e->getMessage());
            }
        }
    }
}