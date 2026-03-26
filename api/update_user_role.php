<?php
// api/update_user_role.php

// --- FIX: Load database FIRST ---
require_once "../config/database.php"; // $conn

// --- Now we can safely check permissions ---
require_once "../config/access_control.php";
check_permission('users.manage'); // Only Admins can change roles

header('Content-Type: application/json');

$response = ['success' => false];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    echo json_encode($response);
    exit;
}

$user_id = $_POST['user_id'] ?? null;
$role_name = $_POST['role'] ?? null; // This will be 'Admin', 'Viewer', 'None', etc.

if (empty($user_id) || empty($role_name)) {
    $response['message'] = 'User ID and Role are required.';
    echo json_encode($response);
    exit;
}

try {
    $conn->begin_transaction();

    if ($role_name === 'None') {
        // Delete the role from the table to revoke access
        $stmt = $conn->prepare("DELETE FROM app_user_roles WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
    } else {
        // 1. Get the role_id from the role_name
        $stmt_role = $conn->prepare("SELECT id FROM roles WHERE role_name = ?");
        $stmt_role->bind_param("s", $role_name);
        $stmt_role->execute();
        $role_result = $stmt_role->get_result();
        
        if ($role_result->num_rows === 0) {
            throw new Exception("Role '{$role_name}' not found.");
        }
        $role_id = $role_result->fetch_assoc()['id'];
        $stmt_role->close();

        // 2. Use INSERT ... ON DUPLICATE KEY UPDATE to either create or update the role
        $stmt = $conn->prepare("
            INSERT INTO app_user_roles (user_id, role_id) 
            VALUES (?, ?) 
            ON DUPLICATE KEY UPDATE role_id = ?
        ");
        $stmt->bind_param("iii", $user_id, $role_id, $role_id);
    }

    if ($stmt->execute()) {
        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'User role updated successfully.';
    } else {
        throw new Exception("Database error: " . $stmt->error);
    }
    
    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    $conn->rollback();
    $response['message'] = $e->getMessage();
}

echo json_encode($response);
exit;
?>