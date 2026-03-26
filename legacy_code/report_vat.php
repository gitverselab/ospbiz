<?php
// report_vat.php
require_once "includes/header.php";
require_once "config/database.php";

// --- Initialize variables ---
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');

// --- OUTPUT VAT (from Sales) ---
$output_vat_sql = "
    SELECT 
        SUM(vatable_sales) as total_vatable_sales,
        SUM(vat_amount) as total_output_vat
    FROM sales
    WHERE invoice_date BETWEEN ? AND ? AND status IN ('Issued', 'Partial', 'Paid')
";
$stmt_output = $conn->prepare($output_vat_sql);
$stmt_output->bind_param("ss", $start_date, $end_date);
$stmt_output->execute();
$output_vat_data = $stmt_output->get_result()->fetch_assoc();
$stmt_output->close();

$total_vatable_sales = $output_vat_data['total_vatable_sales'] ?? 0;
$total_output_vat = $output_vat_data['total_output_vat'] ?? 0;

// --- INPUT VAT (from Purchases and Bills) ---
// For simplicity, we assume the full amount of purchases and bills are vatable.
$input_vat_sql = "
    SELECT 
        SUM(amount / 1.12) as total_vatable_purchases,
        SUM(amount - (amount / 1.12)) as total_input_vat
    FROM (
        SELECT amount, purchase_date as date FROM purchases WHERE status != 'Canceled'
        UNION ALL
        SELECT amount, bill_date as date FROM bills
    ) as expenses
    WHERE date BETWEEN ? AND ?
";
$stmt_input = $conn->prepare($input_vat_sql);
$stmt_input->bind_param("ss", $start_date, $end_date);
$stmt_input->execute();
$input_vat_data = $stmt_input->get_result()->fetch_assoc();
$stmt_input->close();

$total_vatable_purchases = $input_vat_data['total_vatable_purchases'] ?? 0;
$total_input_vat = $input_vat_data['total_input_vat'] ?? 0;

// --- FINAL CALCULATION ---
$vat_payable = $total_output_vat - $total_input_vat;

$conn->close();
?>

<style>
    @media print {
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
    }
</style>
<div class="flex justify-between items-center mb-6 no-print">
    <h2 class="text-3xl font-bold text-gray-800">VAT Report</h2>
    <button onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg">Print Report</button>
</div>

<div class="bg-white p-4 rounded-lg shadow-md mb-6 no-print">
    <form method="GET" action="report_vat.php" class="flex items-center space-x-4">
        <div>
            <label for="start_date" class="text-sm font-medium">From:</label>
            <input type="date" name="start_date" id="start_date" value="<?php echo htmlspecialchars($start_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <div>
            <label for="end_date" class="text-sm font-medium">To:</label>
            <input type="date" name="end_date" id="end_date" value="<?php echo htmlspecialchars($end_date); ?>" class="px-3 py-2 border border-gray-300 rounded-md">
        </div>
        <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded-lg">Generate Report</button>
    </form>
</div>

<div id="printable-area">
    <div class="bg-white p-6 rounded-lg shadow-md print:shadow-none">
        <div class="text-center mb-6">
            <h3 class="text-2xl font-bold">Onyang's Food Inc.</h3>
            <p class="text-lg">Value-Added Tax Summary</p>
            <p class="text-gray-600">For the period of <?php echo date("F j, Y", strtotime($start_date)); ?> to <?php echo date("F j, Y", strtotime($end_date)); ?></p>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-2 gap-8">
            <div>
                <h4 class="text-xl font-semibold border-b pb-2 mb-2">Output VAT (Sales)</h4>
                <table class="w-full">
                    <tbody>
                        <tr><td class="py-2">Total Vatable Sales</td><td class="py-2 text-right">₱<?php echo number_format($total_vatable_sales, 2); ?></td></tr>
                        <tr class="font-bold"><td class="py-2">Output VAT (12%)</td><td class="py-2 text-right">₱<?php echo number_format($total_output_vat, 2); ?></td></tr>
                    </tbody>
                </table>
            </div>
            <div>
                <h4 class="text-xl font-semibold border-b pb-2 mb-2">Input VAT (Purchases & Expenses)</h4>
                <table class="w-full">
                    <tbody>
                        <tr><td class="py-2">Total Vatable Purchases & Expenses</td><td class="py-2 text-right">₱<?php echo number_format($total_vatable_purchases, 2); ?></td></tr>
                        <tr class="font-bold"><td class="py-2">Input VAT (12%)</td><td class="py-2 text-right">₱<?php echo number_format($total_input_vat, 2); ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
        
        <div class="mt-8 border-t-4 border-gray-800 pt-4">
            <table class="w-full max-w-sm mx-auto">
                <tbody>
                    <tr class="text-lg"><td class="py-1">Total Output VAT</td><td class="py-1 text-right">₱<?php echo number_format($total_output_vat, 2); ?></td></tr>
                    <tr class="text-lg"><td class="py-1">Less: Total Input VAT</td><td class="py-1 text-right">(₱<?php echo number_format($total_input_vat, 2); ?>)</td></tr>
                    <tr class="text-2xl font-bold border-t-2">
                        <td class="py-2">VAT Payable</td>
                        <td class="py-2 text-right text-red-600">₱<?php echo number_format($vat_payable, 2); ?></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php require_once "includes/footer.php"; ?>