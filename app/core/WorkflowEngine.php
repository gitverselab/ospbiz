<?php
class WorkflowEngine {
    
    /**
     * Transition a transaction status (e.g., Approve a Journal)
     * Checks: Maker-Checker, Role Permissions, and Amount Thresholds
     */
    public static function transition($entityType, $entityId, $action, $userId, $userRole, $companyId) {
        $db = Database::getInstance();
        
        // 1. Fetch current status and transaction details
        $tableMap = ['journal' => 'journal_entries']; // Map 'journal' to table name
        $tableName = $tableMap[$entityType] ?? null;
        
        if (!$tableName) throw new Exception("Unknown entity type: $entityType");

        $stmt = $db->prepare("SELECT status, created_by, total_amount, branch_id FROM $tableName WHERE id = ?");
        $stmt->execute([$entityId]);
        $record = $stmt->fetch();
        
        if (!$record) throw new Exception("Record not found");

        $currentStatus = $record['status'];
        $maker = $record['created_by'];
        $amount = $record['total_amount'] ?? 0;
        $branchId = $record['branch_id'];
        $newStatus = null;

        // 2. Handle Actions
        if ($action === 'submit') {
            if ($currentStatus !== 'draft') throw new Exception("Only drafts can be submitted.");
            $newStatus = 'submitted';
        } 
        elseif ($action === 'approve') {
            if ($currentStatus !== 'submitted') throw new Exception("Item must be Submitted before Approval.");

            // A. Maker-Checker Rule (Maker cannot approve own work)
            if ($maker == $userId && $userRole !== 'super_admin') {
                throw new Exception("Maker-Checker Violation: You cannot approve your own transaction.");
            }

            // B. Check Workflow Thresholds (The new logic)
            // Find the rule that applies to this amount
            $sql = "SELECT required_role FROM workflow_definitions 
                    WHERE company_id = ? 
                    AND module = ? 
                    AND (branch_id IS NULL OR branch_id = ?)
                    AND min_amount <= ? 
                    AND (max_amount >= ? OR max_amount IS NULL)
                    ORDER BY min_amount DESC LIMIT 1";
            
            $ruleStmt = $db->prepare($sql);
            $ruleStmt->execute([$companyId, $entityType, $branchId, $amount, $amount]);
            $rule = $ruleStmt->fetch();

            if ($rule) {
                // If a rule exists, strictly enforce the role
                $requiredRole = $rule['required_role'];
                
                // Hierarchy: super_admin > finance_manager > accountant
                $rolePower = ['accountant' => 1, 'finance_manager' => 2, 'super_admin' => 3];
                
                $userPower = $rolePower[$userRole] ?? 0;
                $reqPower = $rolePower[$requiredRole] ?? 99;

                if ($userPower < $reqPower) {
                    throw new Exception("Insufficient Authority: This transaction requires at least '$requiredRole' approval.");
                }
            } else {
                // Default fallback if no rule matches: Only Finance Manager or above
                if (!in_array($userRole, ['finance_manager', 'super_admin'])) {
                    throw new Exception("No workflow rule found. Defaulting to Finance Manager approval only.");
                }
            }

            $newStatus = 'approved';
        } 
        elseif ($action === 'reject') {
            $newStatus = 'rejected';
        }

        // 3. Update Status
        if ($newStatus) {
            $update = $db->prepare("UPDATE $tableName SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $entityId]);
            
            // Log Workflow History
            $hist = $db->prepare("INSERT INTO workflow_history (entity_type, entity_id, actor_id, action, prev_status, new_status) VALUES (?,?,?,?,?,?)");
            $hist->execute([$entityType, $entityId, $userId, $action, $currentStatus, $newStatus]);
            
            return true;
        }
        return false;
    }
}