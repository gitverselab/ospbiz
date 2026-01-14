<?php
class AuditLogger {
    public static function log($userId, $companyId, $branchId, $event, $entityType, $entityId, $before, $after) {
        $db = Database::getInstance();
        
        // 1. Get the hash of the very last log entry to chain from
        $stmt = $db->query("SELECT curr_hash FROM audit_logs ORDER BY id DESC LIMIT 1");
        $lastLog = $stmt->fetch();
        $prevHash = $lastLog ? $lastLog['curr_hash'] : 'GENESIS_HASH';

        // 2. Prepare data for current hash
        $timestamp = date('Y-m-d H:i:s');
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'CLI';
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'CLI';
        $beforeJson = json_encode($before);
        $afterJson = json_encode($after);

        // 3. Create Hash: SHA256(prevHash + content + timestamp)
        $dataToHash = $prevHash . $userId . $event . $entityType . $entityId . $afterJson . $timestamp;
        $currHash = hash('sha256', $dataToHash);

        // 4. Insert
        $sql = "INSERT INTO audit_logs (company_id, branch_id, user_id, event_type, entity_type, entity_id, before_json, after_json, ip_address, user_agent, created_at, prev_hash, curr_hash) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->execute([$companyId, $branchId, $userId, $event, $entityType, $entityId, $beforeJson, $afterJson, $ip, $ua, $timestamp, $prevHash, $currHash]);
    }
}