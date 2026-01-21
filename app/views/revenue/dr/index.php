<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">DR Management</h2>
    <div class="flex gap-2">
        <a href="/revenue/dr/create" class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700 shadow font-bold">
            <i class="fa-solid fa-plus"></i> Manual Input
        </a>
        <div class="h-8 w-px bg-gray-300 mx-1"></div>
        <a href="/revenue/dr/template" class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700">
            <i class="fa-solid fa-download"></i> Template
        </a>
        <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-blue-500 text-white px-3 py-2 rounded text-sm hover:bg-blue-600">
            <i class="fa-solid fa-file-import"></i> Import CSV
        </button>
        <a href="/revenue/dr/export" class="bg-gray-600 text-white px-3 py-2 rounded text-sm hover:bg-gray-700">
            <i class="fa-solid fa-file-export"></i> Export
        </a>
    </div>
</div>

<?php session_start(); if (isset($_SESSION['import_msg'])): ?>
    <div class="mb-4 p-4 rounded-md <?php echo empty($_SESSION['import_errors']) ? 'bg-green-100 text-green-700' : 'bg-yellow-100 text-yellow-700'; ?>">
        <strong><?= $_SESSION['import_msg'] ?></strong>
        <?php if (!empty($_SESSION['import_errors'])): ?>
            <ul class="mt-2 list-disc list-inside text-sm text-red-600">
                <?php foreach ($_SESSION['import_errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php unset($_SESSION['import_msg']); unset($_SESSION['import_errors']); ?>
<?php endif; ?>

<form method="GET" action="/revenue/dr" class="bg-white p-4 rounded-lg shadow-sm border border-gray-200 mb-6">
   <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded text-sm font-medium hover:bg-blue-700">Filter</button>
</form>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">DR #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Customer</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Refs</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Total Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($drs)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No records found.</td></tr>
            <?php else: ?>
                <?php foreach($drs as $d): ?>
                <tr class="hover:bg-gray-50">
                    <td class="px-6 py-4 font-bold text-gray-800"><?= htmlspecialchars($d['dr_number']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?= $d['date'] ?></td>
                    <td class="px-6 py-4 text-sm font-medium text-gray-800"><?= htmlspecialchars($d['customer_name']) ?></td>
                    <td class="px-6 py-4 text-xs text-gray-500">
                        <?php if($d['po_number']) echo "PO: " . htmlspecialchars($d['po_number']) . "<br>"; ?>
                        <?php if($d['gr_number']) echo "GR: " . htmlspecialchars($d['gr_number']); ?>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">
                        <?= $d['currency'] ?> <?= number_format($d['total_amount'], 2) ?>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <span class="px-2 py-1 text-xs font-bold text-white rounded-full <?= ($d['status'] == 'delivered') ? 'bg-green-500' : 'bg-yellow-500' ?>">
                            <?= ucfirst($d['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-center text-sm flex justify-center gap-2">
                        <a href="/revenue/dr/edit?id=<?= $d['id'] ?>" class="text-blue-600 hover:text-blue-800 font-medium">Edit</a>
                        
                        <form action="/revenue/dr/delete" method="POST" onsubmit="return confirm('Delete this DR permanently?');">
                            <input type="hidden" name="id" value="<?= $d['id'] ?>">
                            <button type="submit" class="text-red-500 hover:text-red-700 font-medium">Delete</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="flex justify-between items-center mt-4">
    <div class="text-sm text-gray-500">
        Page <?= $filters['page'] ?> of <?= $filters['total_pages'] ?> (Total <?= $filters['total_records'] ?>)
    </div>
    <div class="flex gap-2">
        <?php 
            $params = $_GET; unset($params['page']); 
            $baseUrl = '?' . http_build_query($params) . '&page=';
        ?>
        <?php if ($filters['page'] > 1): ?>
            <a href="<?= $baseUrl . ($filters['page'] - 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Prev</a>
        <?php endif; ?>
        <?php if ($filters['page'] < $filters['total_pages']): ?>
            <a href="<?= $baseUrl . ($filters['page'] + 1) ?>" class="px-3 py-1 bg-white border rounded text-sm">Next</a>
        <?php endif; ?>
    </div>
</div>

<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center z-50">
    <div class="bg-white p-6 rounded-lg shadow-xl w-96">
        <h3 class="font-bold text-lg mb-4 text-gray-800">Import DR CSV</h3>
        <form action="/revenue/dr/import" method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required class="w-full border p-2 mb-4 rounded bg-gray-50 text-sm">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded hover:bg-gray-300">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">Upload</button>
            </div>
        </form>
    </div>
</div>