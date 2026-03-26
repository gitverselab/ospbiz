<?php
// api/update_role_permissions.php
require_once "../config/access_control.php";
check_permission('roles.manage'); // Only users with this permission can run this

require_once "../config/database.php";
header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$role_id = $_POST['role_id'] ?? null;
$permission_ids = $_POST['permission_ids'] ?? []; // This will be an array of IDs from the checkboxes

if (empty($role_id)) {
    $response['message'] = 'Role ID is required.';
    echo json_encode($response);
    exit;
}

$conn->begin_transaction();
try {
    // 1. Delete all existing permissions for this role
    $stmt_delete = $conn->prepare("DELETE FROM role_permissions WHERE role_id = ?");
    $stmt_delete->bind_param("i", $role_id);
    $stmt_delete->execute();
    $stmt_delete->close();

    // 2. Insert the new set of permissions (if any)
    if (!empty($permission_ids)) {
        $sql_insert = "INSERT INTO role_permissions (role_id, permission_id) VALUES ";
        $params = [];
        $types = "";
        
        foreach ($permission_ids as $perm_id) {
            $sql_insert .= "(?, ?),";
            $params[] = $role_id;
            $params[] = (int)$perm_id;
            $types .= "ii";
        }
        
        // Remove the trailing comma
        $sql_insert = rtrim($sql_insert, ',');
        
        $stmt_insert = $conn->prepare($sql_insert);
        $stmt_insert->bind_param($types, ...$params);
        $stmt_insert->execute();
        $stmt_insert->close();
    }

    $conn->commit();
    $response['success'] = true;
    $response['message'] = 'Permissions updated successfully!';

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = "Database error: " . $e->getMessage();
}

$conn->close();
echo json_encode($response);
exit;
?>