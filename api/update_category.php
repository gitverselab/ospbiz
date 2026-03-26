<?php
require_once "../config/database.php";
session_start();
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    header("HTTP/1.1 401 Unauthorized");
    exit;
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    $name = trim($_POST['name']);
    $type = $_POST['type'];

    if (empty($id) || empty($name) || empty($type)) {
        header("location: ../categories.php?error=emptyfields");
        exit;
    }

    $sql = "UPDATE categories SET name = ?, type = ? WHERE id = ?";
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("ssi", $name, $type, $id);
        if ($stmt->execute()) {
            header("location: ../categories.php?success=updated");
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
