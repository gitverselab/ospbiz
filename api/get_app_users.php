<?php
// api/get_app_users.php

// --- FIX: Load databases FIRST ---
require_once "../config/auth_db.php";  // $authConn
require_once "../config/database.php"; // $conn

// --- Now we can safely check permissions ---
require_once "../config/access_control.php";
check_permission('users.manage'); // Only users with this permission can run this

header('Content-Type: application/json');

$response = ['success' => false, 'users' => []];

try {
    // 1. Get all users from the SHARED auth database
    // --- FIX: Corrected SQL query ---
    $auth_users_sql = "SELECT id as user_id, username, employee_id FROM users ORDER BY username ASC";
    
    $auth_result = $authConn->query($auth_users_sql);
    if (!$auth_result) {
        throw new Exception("Failed to query auth users: " . $authConn->error);
    }
    $all_users = $auth_result->fetch_all(MYSQLI_ASSOC);
    $authConn->close();

    // 2. Get all roles from the LOCAL app database
    // --- FIX: Corrected SQL query to get the name ---
    $roles_sql = "SELECT ar.user_id, r.role_name 
                  FROM app_user_roles ar
                  JOIN roles r ON ar.role_id = r.id";
                  
    $roles_result = $conn->query($roles_sql);
    if (!$roles_result) {
        throw new Exception("Failed to query app roles: " . $conn->error);
    }
    $app_roles = [];
    while ($row = $roles_result->fetch_assoc()) {
        $app_roles[$row['user_id']] = $row['role_name'];
    }
    $conn->close();

    // 3. Merge the two lists
    foreach ($all_users as $user) {
        $user['app_role'] = $app_roles[$user['user_id']] ?? null;
        $response['users'][] = $user;
    }

    $response['success'] = true;
    echo json_encode($response);

} catch (Exception $e) {
    http_response_code(500); // Send a 500 error on exception
    $response['message'] = $e->getMessage();
    echo json_encode($response);
}
exit;
?>