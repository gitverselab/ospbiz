<?php
require_once "../config/database.php";
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $name = trim($_POST['name']);
    $type = $_POST['type'];

    if (empty($name) || empty($type)) {
        header("location: ../categories.php?error=emptyfields");
        exit;
    }

    $sql = "INSERT INTO categories (name, type) VALUES (?, ?)";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ss", $name, $type);
        if ($stmt->execute()) {
            header("location: ../categories.php?success=added");
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
