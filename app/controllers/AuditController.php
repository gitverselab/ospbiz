<?php
class AuditController {
    public function index() {
        $db = Database::getInstance();
        
        // Fetch logs with user details
        $sql = "SELECT a.*, u.username 
                FROM audit_logs a 
                LEFT JOIN users u ON a.user_id = u.id 
                ORDER BY a.created_at DESC 
                LIMIT 100";
        
        $logs = $db->query($sql)->fetchAll();

        $childView = ROOT_PATH . '/app/views/audit/list.php';
        require_once ROOT_PATH . '/app/views/layouts/main.php';
    }
}