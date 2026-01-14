<?php
class WorkflowEngine {
    // Enforce Maker-Checker and Status Transitions
    public static function transition($entityType, $entityId, $action, $userId, $userRole) {
        $db = Database::getInstance();
        
        // Fetch current status
        $stmt = $db->prepare("SELECT status, created_by FROM journal_entries WHERE id = ?");
        $stmt->execute([$entityId]);
        $record = $stmt->fetch();
        
        if (!$record) throw new Exception("Record not found");

        $currentStatus = $record['status'];
        $maker = $record['created_by'];
        $newStatus = null;

        // Rules
        if ($action === 'submit' && $currentStatus === 'draft') {
            $newStatus = 'submitted';
        } elseif ($action === 'approve') {
            if ($currentStatus !== 'submitted') throw new Exception("Cannot approve draft/posted items.");
            // Maker-Checker Rule
            if ($maker == $userId && $userRole !== 'super_admin') {
                throw new Exception("Maker-Checker Violation: You cannot approve your own transaction.");
            }
            if (!in_array($userRole, ['finance_manager', 'super_admin'])) {
                throw new Exception("Insufficient permissions to approve.");
            }
            $newStatus = 'approved';
        } elseif ($action === 'post') {
            if ($currentStatus !== 'approved') throw new Exception("Must be approved before posting.");
            $newStatus = 'posted';
        } elseif ($action === 'reject') {
            $newStatus = 'rejected';
        }

        if ($newStatus) {
            $update = $db->prepare("UPDATE journal_entries SET status = ? WHERE id = ?");
            $update->execute([$newStatus, $entityId]);
            
            // Log Workflow History
            $hist = $db->prepare("INSERT INTO workflow_history (entity_type, entity_id, actor_id, action, prev_status, new_status) VALUES (?,?,?,?,?,?)");
            $hist->execute([$entityType, $entityId, $userId, $action, $currentStatus, $newStatus]);
            
            return true;
        }
        return false;
    }
}