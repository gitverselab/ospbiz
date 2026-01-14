<?php
class JournalModel {
    private $db;

    public function __construct() {
        $this->db = Database::getInstance();
    }

    public function create($data, $lines, $user) {
        try {
            $this->db->beginTransaction();

            // 1. Period Lock Check
            $stmt = $this->db->prepare("SELECT is_locked FROM fiscal_periods WHERE company_id = ? AND branch_id = ? AND ? BETWEEN start_date AND end_date");
            $stmt->execute([$user['company_id'], $user['branch_id'], $data['date']]);
            $period = $stmt->fetch();
            if ($period && $period['is_locked']) throw new Exception("Period is locked.");

            // 2. Validate Debits = Credits
            $totalDebit = 0; $totalCredit = 0;
            foreach ($lines as $line) {
                $totalDebit += $line['debit'];
                $totalCredit += $line['credit'];
            }
            if (abs($totalDebit - $totalCredit) > 0.01) throw new Exception("Journal is not balanced.");

            // 3. Insert Header
            $sql = "INSERT INTO journal_entries (company_id, branch_id, date, reference_no, description, created_by, status) VALUES (?, ?, ?, ?, ?, ?, 'draft')";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$user['company_id'], $user['branch_id'], $data['date'], $data['ref'], $data['desc'], $user['id']]);
            $jeId = $this->db->lastInsertId();

            // 4. Insert Lines
            $lineSql = "INSERT INTO journal_lines (journal_entry_id, account_id, debit, credit, memo) VALUES (?, ?, ?, ?, ?)";
            $lineStmt = $this->db->prepare($lineSql);
            foreach ($lines as $line) {
                $lineStmt->execute([$jeId, $line['account_id'], $line['debit'], $line['credit'], $line['memo']]);
            }

            // 5. Audit Log
            AuditLogger::log($user['id'], $user['company_id'], $user['branch_id'], 'create', 'journal', $jeId, null, $data);

            $this->db->commit();
            return $jeId;
        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
    }
}