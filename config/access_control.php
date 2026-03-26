<?php
// config/access_control.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Checks if the current user has a specific permission.
 * If not, it stops the script and sends an error.
 * THIS IS THE "SINGLE UNIFIED LINE" for your API files.
 *
 * @param string $permission_name The name of the permission (e.g., 'bills.create')
 */
function check_permission($permission_name) {
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        http_response_code(401);
        echo json_encode(['success' => false, 'message' => 'Authentication required.']);
        exit;
    }

    if (!isset($_SESSION['role_id'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied. No role assigned.']);
        exit;
    }

    // Check the database
    if (!has_permission($permission_name)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Access Denied. You do not have permission for this action.']);
        exit;
    }
    
    return true;
}

/**
 * Silently checks if the current user has a specific permission.
 * Returns true or false. Used for hiding/showing buttons in the UI.
 *
 * @param string $permission_name The name of the permission (e.g., 'bills.create')
 * @return bool
 */
function can($permission_name) {
    if (!isset($_SESSION["loggedin"], $_SESSION['role_id'])) {
        return false;
    }
    return has_permission($permission_name);
}

/**
 * The core database check.
 * Checks if the session's role_id is linked to the permission_name.
 */
function has_permission($permission_name) {
    // --- THIS IS THE FIX ---
    // We must get the $conn variable from the global scope.
    global $conn;

    // If $conn is still null, it means database.php was NOT included by the parent script.
    // We must include it.
    if (!isset($conn)) {
        // This path is relative to *this file* (config/access_control.php)
        require_once __DIR__ . '/database.php';
    }
    // --- END FIX ---
    
    $role_id = $_SESSION['role_id'];

    $sql = "SELECT 1
            FROM role_permissions rp
            JOIN permissions p ON rp.permission_id = p.id
            WHERE rp.role_id = ? AND p.permission_name = ?
            LIMIT 1";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("is", $role_id, $permission_name);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();
    
    return $result->num_rows === 1;
}

/**
 * This is the old function. We still need it to protect web pages.
 * But now it just checks for *any* valid role.
 */
function require_role(array $required_roles) {
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
        $login_path = str_repeat('../', substr_count(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/')) . 'login.php';
        header("location: $login_path");
        exit;
    }

    // Use the string role name from the session for this simple check
    if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], $required_roles, true)) {
        $index_path = str_repeat('../', substr_count(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/')) . 'index.php';
        die("<h2>Access Denied</h2><p>You do not have permission to view this page.</p><a href='$index_path'>Go to Dashboard</a>");
    }
    return true;
}

/**
 * Helper function for the old check.
 */
function has_role(array $roles) {
    if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true || !isset($_SESSION['role'])) {
        return false;
    }
    return in_array($_SESSION['role'], $roles, true);
}
?>