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

        // Fetch Journals with their Lines (Grouped)
        // Note: Fetching headers first, then we can lazy load lines or join. 
        // For a clean UI, let's fetch headers and then fetch lines for each.
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
        
        // Get Chart of Accounts
        $accounts = $db->query("SELECT * FROM accounts ORDER BY code ASC")->fetchAll();
        
        // Suggest Next JV Number
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

                // 1. Validate Balance
                $totalDebit = 0;
                $totalCredit = 0;
                foreach($lines as $l) {
                    $totalDebit += floatval($l['debit']);
                    $totalCredit += floatval($l['credit']);
                }
                
                // Allow small floating point difference
                if (abs($totalDebit - $totalCredit) > 0.01) {
                    throw new Exception("Journal Entry is not balanced. Debit: $totalDebit, Credit: $totalCredit");
                }

                // 2. Insert Header
                $stmt = $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, status) VALUES (1, ?, ?, ?, 'manual', 'posted')");
                $stmt->execute([$date, $ref, $desc]);
                $journalId = $db->lastInsertId();

                // 3. Insert Lines
                $stmtLine = $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");
                
                foreach ($lines as $l) {
                    $stmtLine->execute([
                        $journalId, 
                        $l['account_id'], 
                        $l['description'] ?: $desc, // Use header desc if line desc empty
                        floatval($l['debit']), 
                        floatval($l['credit'])
                    ]);
                    
                    // 4. Update Account Current Balance (Simple accumulation)
                    // Asset/Expense: Debit increases, Credit decreases
                    // Liab/Equity/Income: Credit increases, Debit decreases
                    // For simplicity in this non-coder setup, we might skip complex balance caching 
                    // and rely on Reports to sum up transactions later.
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
    // This allows Sales, Bills, etc. to create journal entries easily
    public static function post($date, $ref, $desc, $module, $sourceId, $lines) {
        $db = Database::getInstance();
        
        // 1. Insert Header
        $stmt = $db->prepare("INSERT INTO journal_entries (company_id, date, reference_no, description, source_module, source_id, status) VALUES (1, ?, ?, ?, ?, ?, 'posted')");
        $stmt->execute([$date, $ref, $desc, $module, $sourceId]);
        $journalId = $db->lastInsertId();

        // 2. Insert Lines
        $stmtLine = $db->prepare("INSERT INTO journal_lines (journal_id, account_id, description, debit, credit) VALUES (?, ?, ?, ?, ?)");
        
        foreach ($lines as $l) {
            // Find Account ID based on Code (We assume you have these standard codes)
            // You might need to adjust these codes to match your specific Chart of Accounts
            $acc = $db->query("SELECT id FROM accounts WHERE code = '{$l['code']}'")->fetch();
            $accId = $acc ? $acc['id'] : 0; // Fallback to 0 if not found (needs setup)

            $stmtLine->execute([
                $journalId, 
                $accId, 
                $l['desc'], 
                floatval($l['debit']), 
                floatval($l['credit'])
            ]);
        }
    }
}