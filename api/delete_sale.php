<?php
// api/delete_sale.php
require_once "../config/database.php";
header('Content-Type: application/json');
$response = ['success' => false];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $sale_id = $_POST['id'];
    
    $conn->begin_transaction();
    try {
        // 1. Get all DR IDs to un-invoice them
        $stmt_get_drs = $conn->prepare("SELECT delivery_receipt_id FROM sales_items WHERE sale_id = ?");
        $stmt_get_drs->bind_param("i", $sale_id);
        $stmt_get_drs->execute();
        $result = $stmt_get_drs->get_result();
        $dr_ids = [];
        while($row = $result->fetch_assoc()) {
            $dr_ids[] = $row['delivery_receipt_id'];
        }
        $stmt_get_drs->close();

        // Un-mark those DRs as invoiced
        if (!empty($dr_ids)) {
            $placeholders = implode(',', array_fill(0, count($dr_ids), '?'));
            $types = str_repeat('i', count($dr_ids));
            $stmt_update_dr = $conn->prepare("UPDATE delivery_receipts SET is_invoiced = 0 WHERE id IN ($placeholders)");
            $stmt_update_dr->bind_param($types, ...$dr_ids);
            $stmt_update_dr->execute();
            $stmt_update_dr->close();
        }

        // 2. NEW: Get all RTS IDs to un-link them
        $stmt_get_rts = $conn->prepare("SELECT return_receipt_id FROM sales_rts WHERE sale_id = ?");
        $stmt_get_rts->bind_param("i", $sale_id);
        $stmt_get_rts->execute();
        $rts_result = $stmt_get_rts->get_result();
        $rts_ids = [];
        while($row = $rts_result->fetch_assoc()) {
            $rts_ids[] = $row['return_receipt_id'];
        }
        $stmt_get_rts->close();

        // Un-mark those RTS as Deducted (Revert them to Pending/Available)
        if (!empty($rts_ids)) {
            $rts_placeholders = implode(',', array_fill(0, count($rts_ids), '?'));
            $rts_types = str_repeat('i', count($rts_ids));
            $stmt_update_rts = $conn->prepare("UPDATE return_receipts SET status = 'Pending' WHERE id IN ($rts_placeholders)");
            $stmt_update_rts->bind_param($rts_types, ...$rts_ids);
            $stmt_update_rts->execute();
            $stmt_update_rts->close();
        }

        // 3. Delete the sale itself (ON DELETE CASCADE in the DB will automatically delete the records in sales_items, sales_rts, and sales_payments)
        $stmt_delete_sale = $conn->prepare("DELETE FROM sales WHERE id = ?");
        $stmt_delete_sale->bind_param("i", $sale_id);
        $stmt_delete_sale->execute();
        $stmt_delete_sale->close();

        $conn->commit();
        $response['success'] = true;
        $response['message'] = 'Sale deleted successfully. DRs and Returns have been unlinked.';

    } catch (Exception $e) {
        $conn->rollback();
        $response['message'] = 'Error: ' . $e->getMessage();
    }
    $conn->close();
}
echo json_encode($response);
?>