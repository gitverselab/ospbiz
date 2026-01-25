<?php
class PurchaseController {

    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $status = $_GET['status'] ?? '';
        
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 10;
        $offset = ($page - 1) * $limit;

        $whereSql = "1=1"; 
        $params = [];

        if ($search) {
            $whereSql .= " AND (po.po_number LIKE ? OR s.name LIKE ?)";
            $params[] = "%$search%";
            $params[] = "%$search%";
        }
        if ($fromDate) { $whereSql .= " AND po.date >= ?"; $params[] = $fromDate; }
        if ($toDate) { $whereSql .= " AND po.date <= ?"; $params[] = $toDate; }
        if ($status) { $whereSql .= " AND po.status = ?"; $params[] = $status; }

        $countSql = "SELECT COUNT(*) as total FROM purchase_orders po LEFT JOIN suppliers s ON po.supplier_id = s.id WHERE $whereSql";
        $stmtCount = $db->prepare($countSql);
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        $sql = "SELECT po.*, s.name as supplier_name 
                FROM purchase_orders po
                LEFT JOIN suppliers s ON po.supplier_id = s.id
                WHERE $whereSql
                ORDER BY po.date DESC, po.id DESC
                LIMIT $limit OFFSET $offset";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $pos = $stmt->fetchAll();

        $filters = compact('search', 'fromDate', 'toDate', 'status', 'limit', 'page', 'totalPages', 'totalRecords');

        $pageTitle = "Purchase Orders";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function create() {
        $db = Database::getInstance();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();
        
        $pageTitle = "Create Purchase Order";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    public function store() {
        $this->save(false);
    }

    // --- NEW: VIEW PO & HISTORY ---
    public function show() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? 0;

        // 1. Get PO Header
        $stmt = $db->prepare("SELECT po.*, s.name as supplier_name, s.address, s.email 
                              FROM purchase_orders po 
                              LEFT JOIN suppliers s ON po.supplier_id = s.id 
                              WHERE po.id = ?");
        $stmt->execute([$id]);
        $po = $stmt->fetch();

        if (!$po) { header("Location: /expenses/purchases"); exit; }

        // 2. Get Lines
        $lines = $db->query("SELECT * FROM purchase_order_lines WHERE purchase_order_id = $id")->fetchAll();

        // 3. Get Payment History (Allocations)
        $paySql = "SELECT pp.reference_no, pp.date, pp.payment_method, ppa.amount_applied 
                   FROM purchase_payment_allocations ppa
                   JOIN purchase_payments pp ON ppa.purchase_payment_id = pp.id
                   WHERE ppa.purchase_order_id = ?
                   ORDER BY pp.date DESC";
        $stmtPay = $db->prepare($paySql);
        $stmtPay->execute([$id]);
        $payments = $stmtPay->fetchAll();

        $pageTitle = "View PO: " . $po['po_number'];
        $childView = ROOT_PATH . '/app/views/expenses/purchases/show.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- NEW: EDIT FORM ---
    public function edit() {
        $db = Database::getInstance();
        $id = $_GET['id'] ?? 0;

        $stmt = $db->prepare("SELECT * FROM purchase_orders WHERE id = ?");
        $stmt->execute([$id]);
        $po = $stmt->fetch();

        if (!$po) { header("Location: /expenses/purchases"); exit; }
        
        // Prevent editing if Paid (Optional rule, usually good practice)
        if ($po['status'] === 'paid') {
            echo "<script>alert('Cannot edit a fully paid Purchase Order.'); window.location.href='/expenses/purchases';</script>";
            exit;
        }

        $lines = $db->query("SELECT * FROM purchase_order_lines WHERE purchase_order_id = $id")->fetchAll();
        $suppliers = $db->query("SELECT * FROM suppliers WHERE is_active=1 ORDER BY name ASC")->fetchAll();

        $pageTitle = "Edit Purchase Order";
        $childView = ROOT_PATH . '/app/views/expenses/purchases/create.php'; // Reuse create form
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- NEW: UPDATE ACTION ---
    public function update() {
        $this->save(true);
    }

    private function save($isUpdate) {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $lines = json_decode($_POST['lines_json'], true);
                $total = array_sum(array_column($lines, 'amount'));
                $deliveryDate = !empty($_POST['expected_delivery_date']) ? $_POST['expected_delivery_date'] : null;

                if ($isUpdate) {
                    $id = $_POST['id'];
                    $sql = "UPDATE purchase_orders SET supplier_id=?, po_number=?, date=?, expected_delivery_date=?, total_amount=? WHERE id=?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([$_POST['supplier_id'], $_POST['po_number'], $_POST['date'], $deliveryDate, $total, $id]);
                    
                    // Clear old lines
                    $db->prepare("DELETE FROM purchase_order_lines WHERE purchase_order_id = ?")->execute([$id]);
                    $poId = $id;
                } else {
                    $sql = "INSERT INTO purchase_orders (company_id, supplier_id, po_number, date, expected_delivery_date, status, total_amount) VALUES (?, ?, ?, ?, ?, 'open', ?)";
                    $stmt = $db->prepare($sql);
                    $stmt->execute([1, $_POST['supplier_id'], $_POST['po_number'], $_POST['date'], $deliveryDate, $total]);
                    $poId = $db->lastInsertId();
                }

                // Insert Lines
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