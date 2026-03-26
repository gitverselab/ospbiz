<?php
// api/delete_credit.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $id = $_POST['id'];
    if (empty($id)) {
        $response['message'] = 'Invalid ID.';
        echo json_encode($response); exit;
    }

    $sql = "DELETE FROM credits WHERE id = ? AND status = 'Pending'"; // Safety: only delete pending credits
    if ($stmt = $conn->prepare($sql)) {
        $stmt->bind_param("i", $id);
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                $response['success'] = true;
            } else {
                $response['message'] = 'Could not delete. The credit may have already been received or paid.';
            }
        } else {
            $response['message'] = 'Database error.';
        }
        $stmt->close();
    }
    $conn->close();
}
echo json_encode($response);
?>