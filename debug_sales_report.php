<?php
// debug_sales_report.php
require_once "includes/header.php";
require_once "config/database.php";

$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// 1. Run the Exact Same Logic as P&L
$sql = "
    SELECT 
        s.id,
        s.invoice_number,
        c.customer_name,
        s.invoice_date,
        s.payment_due_date,
        s.status,
        s.total_amount
    FROM sales s
    JOIN customers c ON s.customer_id = c.id
    WHERE s.payment_due_date BETWEEN ? AND ? 
    AND s.status != 'Void'
    ORDER BY s.total_amount DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$result = $stmt->get_result();

$total_check = 0;
?>

<div class="max-w-4xl mx-auto bg-white p-6 shadow rounded">
    <h2 class="text-2xl font-bold mb-4 text-red-700">Audit: Why is my Sales Due too high?</h2>
    
    <form method="GET" class="mb-6 flex gap-4 items-end bg-gray-100 p-4 rounded">
        <div>
            <label class="block text-xs font-bold">Start Date (Due Date)</label>
            <input type="date" name="start_date" value="<?php echo $start_date; ?>" class="border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold">End Date (Due Date)</label>
            <input type="date" name="end_date" value="<?php echo $end_date; ?>" class="border p-2 rounded">
        </div>
        <button type="submit" class="bg-blue-600 text-white px-4 py-2 rounded font-bold">Check Records</button>
    </form>

    <table class="w-full text-sm border-collapse border border-gray-300">
        <thead class="bg-gray-200">
            <tr>
                <th class="border p-2 text-left">Inv #</th>
                <th class="border p-2 text-left">Customer</th>
                <th class="border p-2 text-left">Due Date</th>
                <th class="border p-2 text-center">Status</th>
                <th class="border p-2 text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php while($row = $result->fetch_assoc()): 
                $total_check += $row['total_amount'];
            ?>
            <tr class="hover:bg-yellow-50">
                <td class="border p-2 font-mono text-blue-600">
                    <a href="sales_invoice.php?id=<?php echo $row['id']; ?>" target="_blank" class="underline">
                        <?php echo $row['invoice_number']; ?>
                    </a>
                </td>
                <td class="border p-2"><?php echo $row['customer_name']; ?></td>
                <td class="border p-2"><?php echo $row['payment_due_date']; ?></td>
                <td class="border p-2 text-center">
                    <span class="px-2 py-1 rounded text-xs font-bold 
                        <?php echo ($row['status']=='Paid')?'bg-green-100 text-green-800':'bg-red-100 text-red-800'; ?>">
                        <?php echo $row['status']; ?>
                    </span>
                </td>
                <td class="border p-2 text-right font-bold"><?php echo number_format($row['total_amount'], 2); ?></td>
            </tr>
            <?php endwhile; ?>
            
            <tr class="bg-gray-800 text-white font-bold text-lg">
                <td colspan="4" class="border p-3 text-right">TOTAL COMPUTED:</td>
                <td class="border p-3 text-right"><?php echo number_format($total_check, 2); ?></td>
            </tr>
        </tbody>
    </table>
</div>

<?php require_once "includes/footer.php"; ?>