<?php
require_once "../config/database.php";
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];

    if (empty($id)) {
        header("location: ../categories.php?error=invalidid");
        exit;
    }

    $sql = "DELETE FROM categories WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            header("location: ../categories.php?success=deleted");
        } else {
            header("location: ../categories.php?error=sqlerror");
        }
        $stmt->close();
    }
    $conn->close();
} else {
    header("location: ../categories.php");
    exit;
}
?>
