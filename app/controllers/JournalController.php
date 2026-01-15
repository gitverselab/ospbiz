<?php
class JournalController {

    // --- JOURNAL HISTORY (General Ledger View) ---
    public function index() {
        $db = Database::getInstance();
        
        $search = $_GET['search'] ?? '';
        $fromDate = $_GET['from'] ?? '';
        $toDate = $_GET['to'] ?? '';
        $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
        $limit = 20; // Show more rows for GL
        $offset = ($page - 1) * $limit;

        $where = "1=1";
        $params = [];

        if ($search) {
            $where .= " AND (je.reference_no LIKE ? OR je.description LIKE ?)";
            $params[] = "%$search%"; $params[] = "%$search%";
        }
        if ($fromDate) {
            $where .= " AND je.date >= ?";
            $params[] = $fromDate;
        }
        if ($toDate) {
            $where .= " AND je.date <= ?";
            $params[] = $toDate;
        }

        // Count
        $stmtCount = $db->prepare("SELECT COUNT(*) as total FROM journal_entries je WHERE $where");
        $stmtCount->execute($params);
        $totalRecords = $stmtCount->fetch()['total'];
        $totalPages = ceil($totalRecords / $limit);

        // Fetch Journals
        $sql = "SELECT * FROM journal_entries je WHERE $where ORDER BY date DESC, id DESC LIMIT $limit OFFSET $offset";
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $journals = $stmt->fetchAll();

        // Attach lines to each journal
        foreach ($journals as &$j) {
            $lSql = "SELECT jl.*, a.code, a.name as account_name 
                     FROM journal_lines jl 
                     JOIN accounts a ON jl.account_id = a.id 
                     WHERE jl.journal_id = ?";
            $stmtL = $db->prepare($lSql);
            $stmtL->execute([$j['id']]);
            $j['lines'] = $stmtL->fetchAll();
        }

        $filters = ['search'=>$search, 'from'=>$fromDate, 'to'=>$toDate, 'page'=>$page, 'total_pages'=>$totalPages, 'total_records'=>$totalRecords];
        
        $pageTitle = "Journal History";
        $childView = ROOT_PATH . '/app/views/journal/index.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- CREATE MANUAL JV ---
    public function create() {
        $db = Database::getInstance();
        $accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();
        
        $lastJv = $db->query("SELECT reference_no FROM journal_entries WHERE source_module='manual' ORDER BY id DESC LIMIT 1")->fetch();
        $nextNum = 'JV-' . str_pad(($lastJv ? intval(substr($lastJv['reference_no'], 3)) + 1 : 1), 6, '0', STR_PAD_LEFT);

        $pageTitle = "Create Journal Entry";
        $childView = ROOT_PATH . '/app/views/journal/create.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }

    // --- STORE JV ---
    public function store() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $db = Database::getInstance();
            try {
                $db->beginTransaction();

                $date = $_POST['date'];
                $ref = $_POST['reference_no'];
                $desc = $_POST['description'];
                $lines = json_decode($_POST['lines_json'], true);

                $totalDebit = 0;
                $totalCredit = 0;
                foreach($lines as $l) {
                    $totalDebit += floatval($l['debit']);
                    $totalCredit += floatval($l['credit']);
                }
                
                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new Exception("Journal Entry is not balanced. Debit: $totalDebit, Credit: $totalCredit");
                }

                $stmt = $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, status) VALUES (1, ?, ?, ?, 'manual', 'posted')");
                $stmt->execute([$date, $ref, $desc]);
                $journalId = $db->lastInsertId();

                $stmtLine = $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($lines as $l) {
                    $stmtLine->execute([
                        $journalId, 
                        $l['account_id'], 
                        $l['description'] ?: $desc, 
                        floatval($l['debit']), 
                        floatval($l['credit'])
                    ]);
                }

                $db->commit();
                header("Location: /journal/list");

            } catch (Exception $e) {
                $db->rollBack();
                die("Error: " . $e->getMessage());
            }
        }
    }

    // --- STATIC HELPER: AUTO-POST FROM OTHER MODULES ---
    // UPDATED: Now supports both 'account_id' OR 'code'
    public static function post($date, $ref, $desc, $module, $sourceId, $lines) {
        $db = Database::getInstance();
        
        // 1. Insert Header
        $stmt = $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, source_id, status) VALUES (1, ?, ?, ?, ?, ?, 'posted')");
        $stmt->execute([$date, $ref, $desc, $module, $sourceId]);
        $journalId = $db->lastInsertId();

        // 2. Insert Lines
        $stmtLine = $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($lines as $l) {
            $accId = 0;

            // FIX: Check if ID is provided directly (Bank Module does this)
            if (isset($l['account_id']) && !empty($l['account_id'])) {
                $accId = $l['account_id'];
            } 
            // Otherwise, look up by Code (Sales Module does this)
            elseif (isset($l['code'])) {
                $acc = $db->query("SELECT id FROM accounts WHERE code = '{$l['code']}'")->fetch();
                $accId = $acc ? $acc['id'] : 0;
            }

            // Only insert if we found a valid account ID
            if ($accId > 0) {
                $stmtLine->execute([
                    $journalId, 
                    $accId, 
                    $l['desc'] ?? $desc, // Fallback description
                    floatval($l['debit']), 
                    floatval($l['credit'])
                ]);
            }
        }
    }
}