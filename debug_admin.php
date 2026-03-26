<?php
// debug_admin.php
session_start();
require_once "config/database.php";

echo "<h2>Admin Access Debugger</h2>";

// 1. Check Session
echo "<h3>1. Session Data</h3>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

$user_id = $_SESSION['user_id'] ?? $_SESSION['id'] ?? 0;

if ($user_id == 0) {
    die("<h3 style='color:red'>❌ You are not logged in (No User ID in Session).</h3>");
}

// 2. Check Database Roles
echo "<h3>2. Database Roles for User #$user_id</h3>";
$sql = "
    SELECT ur.user_id, ur.role_id, r.role_name
    FROM app_user_roles ur
    JOIN roles r ON ur.role_id = r.id
    WHERE ur.user_id = $user_id
";
$res = $conn->query($sql);

if ($res->num_rows > 0) {
    echo "<table border='1' cellpadding='5'><tr><th>User ID</th><th>Role ID</th><th>Role Name</th></tr>";
    while ($row = $res->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['user_id'] . "</td>";
        echo "<td>" . $row['role_id'] . "</td>";
        echo "<td>" . $row['role_name'] . "</td>";
        echo "</tr>";
        
        // Logic Check
        if (trim($row['role_name']) === 'Admin' || $row['role_id'] == 1) {
            echo "<tr><td colspan='3' style='background:lightgreen; color:green'><strong>✅ SUCCESS: System SHOULD recognize you as Admin.</strong></td></tr>";
        }
    }
    echo "</table>";
} else {
    echo "<h3 style='color:red'>❌ No roles found in 'app_user_roles' for this user.</h3>";
}
?>