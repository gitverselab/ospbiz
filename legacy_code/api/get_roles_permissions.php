<?php
// api/get_roles_permissions.php

// --- FIX: Load database FIRST ---
require_once "../config/database.php";

// --- Now we can safely check permissions ---
require_once "../config/access_control.php";
check_permission('roles.manage'); // Only users with this permission can run this

header('Content-Type: application/json');

$response = [
    'success' => false,
    'roles' => [],
    'permissions' => [],
    'role_permissions' => []
];

try {
    // 1. Get all roles
    $response['roles'] = $conn->query("SELECT id, role_name FROM roles ORDER BY role_name")->fetch_all(MYSQLI_ASSOC);

    // 2. Get all permissions
    $response['permissions'] = $conn->query("SELECT id, permission_name FROM permissions ORDER BY permission_name")->fetch_all(MYSQLI_ASSOC);

    // 3. Get the mapping
    $mapping_result = $conn->query("SELECT role_id, permission_id FROM role_permissions");
    $mapping = [];
    while ($row = $mapping_result->fetch_assoc()) {
        if (!isset($mapping[$row['role_id']])) {
            $mapping[$row['role_id']] = [];
        }
        $mapping[$row['role_id']][] = (int)$row['permission_id'];
    }
    $response['role_permissions'] = $mapping;

    $response['success'] = true;
    $conn->close();
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500); // Send a 500 error on exception
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
exit;
?>