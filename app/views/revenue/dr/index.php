<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">DR Management</h2>
    <div class="flex gap-2">
        <a href="/revenue/dr/template" class="bg-green-600 text-white px-3 py-2 rounded text-sm hover:bg-green-700">
            <i class="fa-solid fa-download"></i> Template
        </a>
        <button onclick="document.getElementById('importModal').classList.remove('hidden')" class="bg-blue-600 text-white px-3 py-2 rounded text-sm hover:bg-blue-700">
            <i class="fa-solid fa-file-import"></i> Import CSV
        </button>
        <a href="/revenue/dr/export" class="bg-gray-600 text-white px-3 py-2 rounded text-sm hover:bg-gray-700">
            <i class="fa-solid fa-file-export"></i> Export
        </a>
    </div>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">DR Number</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Plant / Customer</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">PO #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Status</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php foreach($drs as $d): ?>
            <tr>
                <td class="px-6 py-4 text-sm font-mono text-blue-600"><?= $d['dr_number'] ?></td>
                <td class="px-6 py-4 text-sm"><?= $d['date'] ?></td>
                <td class="px-6 py-4 text-sm"><?= $d['customer_name'] ?> (<?= $d['plant_code'] ?>)</td>
                <td class="px-6 py-4 text-sm"><?= $d['po_number'] ?></td>
                <td class="px-6 py-4 text-sm uppercase font-bold text-gray-600"><?= $d['status'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="importModal" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded w-96">
        <h3 class="font-bold mb-4">Import DR CSV</h3>
        <form action="/revenue/dr/import" method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required class="w-full border p-2 mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importModal').classList.add('hidden')" class="px-3 py-1 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded">Upload</button>
            </div>
        </form>
    </div>
</div>