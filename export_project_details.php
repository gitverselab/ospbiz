<?php
// export_project_details.php
require_once "config/database.php";

$id = $_GET['id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM projects WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$project = $stmt->get_result()->fetch_assoc();

if (!$project) { echo "Project not found."; exit; }

// FETCH DATA
$expenses = $conn->query("SELECT * FROM expenses WHERE project_id = $id ORDER BY expense_date DESC")->fetch_all(MYSQLI_ASSOC);
$purchases = $conn->query("SELECT * FROM purchases WHERE project_id = $id ORDER BY purchase_date DESC")->fetch_all(MYSQLI_ASSOC);

// CALCULATIONS
$total_expenses = array_sum(array_column($expenses, 'amount'));
$total_purchases = array_sum(array_column($purchases, 'amount'));
$total_spent = $total_expenses + $total_purchases;
$remaining = $project['budget'] - $total_spent;

// HEADERS FOR WORD DOWNLOAD
$filename = "Project_Report_" . preg_replace('/[^a-zA-Z0-9]/', '_', $project['project_name']) . ".doc";
header("Content-type: application/vnd.ms-word");
header("Content-Disposition: attachment;Filename=$filename");
?>

<html>
<head>
<style>
    body { font-family: Arial, sans-serif; font-size: 12pt; }
    table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
    th, td { border: 1px solid #000; padding: 8px; text-align: left; }
    th { background-color: #f2f2f2; }
    .header { text-align: center; margin-bottom: 30px; }
    .summary { margin-bottom: 20px; }
    .status { font-weight: bold; text-transform: uppercase; }
    .amount { text-align: right; }
</style>
</head>
<body>

<div class="header">
    <h1>Project Status Report</h1>
    <h2><?php echo htmlspecialchars($project['project_name']); ?></h2>
    <p>Generated on: <?php echo date("F j, Y"); ?></p>
</div>

<div class="summary">
    <h3>Executive Summary</h3>
    <table style="border: none;">
        <tr style="border: none;"><td style="border: none;"><strong>Status:</strong> <?php echo $project['status']; ?></td><td style="border: none;"><strong>Start Date:</strong> <?php echo $project['start_date']; ?></td></tr>
        <tr style="border: none;"><td style="border: none;"><strong>Budget:</strong> <?php echo number_format($project['budget'], 2); ?></td><td style="border: none;"><strong>Target End:</strong> <?php echo $project['end_date']; ?></td></tr>
        <tr style="border: none;"><td style="border: none;"><strong>Total Spent:</strong> <?php echo number_format($total_spent, 2); ?></td><td style="border: none;"></td></tr>
        <tr style="border: none;"><td style="border: none;"><strong>Remaining:</strong> <?php echo number_format($remaining, 2); ?></td><td style="border: none;"></td></tr>
    </table>
    <p><strong>Goal/Description:</strong><br><?php echo nl2br(htmlspecialchars($project['description'])); ?></p>
</div>

<h3>Financial Breakdown</h3>

<h4>1. Expenses (Direct Costs)</h4>
<?php if(empty($expenses)): ?>
    <p>No expenses recorded.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>Description</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            <?php foreach($expenses as $e): ?>
            <tr>
                <td><?php echo $e['expense_date']; ?></td>
                <td><?php echo htmlspecialchars($e['description']); ?></td>
                <td class="amount"><?php echo number_format($e['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h4>2. Purchases (POs)</h4>
<?php if(empty($purchases)): ?>
    <p>No purchase orders recorded.</p>
<?php else: ?>
    <table>
        <thead><tr><th>Date</th><th>PO Number</th><th>Status</th><th class="amount">Amount</th></tr></thead>
        <tbody>
            <?php foreach($purchases as $p): ?>
            <tr>
                <td><?php echo $p['purchase_date']; ?></td>
                <td><?php echo htmlspecialchars($p['po_number']); ?></td>
                <td><?php echo $p['status']; ?></td>
                <td class="amount"><?php echo number_format($p['amount'], 2); ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<br>
<p style="text-align: center; font-size: 10pt; color: #666;">--- End of Report ---</p>

</body>
</html>