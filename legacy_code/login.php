<?php
session_start();

require_once __DIR__ . "/config/auth_db.php"; 
require_once __DIR__ . "/config/database.php";  

$username = $_POST['username'] ?? '';
$password = $_POST['password'] ?? '';
$username_err = '';
$password_err = '';
$login_err    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (empty(trim($username))) $username_err = 'Please enter your username.';
    if (empty(trim($password))) $password_err = 'Please enter your password.';

    if (empty($username_err) && empty($password_err)) {
        
        $sql = "SELECT id, username, password FROM users WHERE username = ? LIMIT 1";

        try {
            if ($stmt_auth = $authConn->prepare($sql)) {
                $stmt_auth->bind_param("s", $username);
                $stmt_auth->execute();
                $stmt_auth->store_result();

                if ($stmt_auth->num_rows === 1) {
                    $stmt_auth->bind_result($id, $db_username, $db_password);
                    $stmt_auth->fetch();

                    $verified = password_verify($password, $db_password);
                    $looksHashed = (strpos($db_password, '$2y$') === 0) || (strpos($db_password, '$argon2') === 0);
                    if (!$verified && !$looksHashed) {
                        $verified = hash_equals($db_password, $password);
                    }

                    if ($verified) {
                        // --- THIS IS THE UPDATED LOGIC ---
                        // Get the user's assigned role_id AND the role's name
                        $stmt_role = $conn->prepare("
                            SELECT ar.role_id, r.role_name 
                            FROM app_user_roles ar
                            JOIN roles r ON ar.role_id = r.id
                            WHERE ar.user_id = ?
                        ");
                        $stmt_role->bind_param("i", $id);
                        $stmt_role->execute();
                        $role_result = $stmt_role->get_result();
                        
                        if ($role_result->num_rows === 1) {
                            $app_role = $role_result->fetch_assoc();

                            // Store everything in the session
                            session_regenerate_id(true);
                            $_SESSION['loggedin'] = true;
                            $_SESSION['id']       = (int)$id;
                            $_SESSION['username'] = $db_username;
                            $_SESSION['role_id']  = (int)$app_role['role_id']; // The new ID
                            $_SESSION['role']     = $app_role['role_name']; // The name (Admin, etc.)

                            header('Location: index.php');
                            exit;
                        } else {
                            $login_err = "You do not have permission to access this application.";
                        }
                        $stmt_role->close();
                    } else {
                        $login_err = "Invalid username or password.";
                    }
                } else {
                    $login_err = "Invalid username or password.";
                }
                $stmt_auth->close();
            } else {
                $login_err = "Could not prepare login query.";
            }
        } catch (mysqli_sql_exception $e) {
            $login_err = "Login failed: " . $e->getMessage();
        }
    }
    if (isset($authConn)) $authConn->close();
    if (isset($conn)) $conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Accounting App</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style> body { font-family: 'Inter', sans-serif; } </style>
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
    <div class="w-full max-w-md">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="bg-white shadow-lg rounded-xl px-8 pt-6 pb-8 mb-4">
            <div class="mb-8 text-center">
                <h1 class="text-3xl font-bold text-gray-800">Welcome Back!</h1>
                <p class="text-gray-500 mt-2">Sign in to continue to your dashboard.</p>
            </div>
            <?php 
            if(!empty($login_err)){
                echo '<div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative mb-4" role="alert">' . htmlspecialchars($login_err, ENT_QUOTES, 'UTF-8') . '</div>';
            }        
            ?>
            <div class="mb-4">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="username">Username</label>
                <input class="shadow-sm appearance-none border <?php echo (!empty($username_err)) ? 'border-red-500' : ''; ?> rounded-lg w-full py-3 px-4 text-gray-700 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" id="username" name="username" type="text" placeholder="Enter your username" value="<?php echo htmlspecialchars($username, ENT_QUOTES, 'UTF-8'); ?>">
                <span class="text-red-500 text-xs italic"><?php echo htmlspecialchars($username_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="mb-6">
                <label class="block text-gray-700 text-sm font-bold mb-2" for="password">Password</label>
                <input class="shadow-sm appearance-none border <?php echo (!empty($password_err)) ? 'border-red-500' : ''; ?> rounded-lg w-full py-3 px-4 text-gray-700 mb-3 leading-tight focus:outline-none focus:ring-2 focus:ring-blue-500" id="password" name="password" type="password" placeholder="******************">
                <span class="text-red-500 text-xs italic"><?php echo htmlspecialchars($password_err, ENT_QUOTES, 'UTF-8'); ?></span>
            </div>
            <div class="flex items-center justify-between">
                <button class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 px-4 rounded-lg w-full focus:outline-none focus:shadow-outline" type="submit">
                    Sign In
                </button>
            </div>
        </form>
        <p class="text-center text-gray-500 text-xs">&copy;2025 Accounting App Inc. All rights reserved.</p>
    </div>
</body>
</html>