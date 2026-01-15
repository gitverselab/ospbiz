<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">RTS Management</h2>
    <div class="flex gap-2">
        <a href="/revenue/rts/template" class="bg-green-600 text-white px-3 py-2 rounded text-sm">Download Template</a>
        <button onclick="document.getElementById('importRts').classList.remove('hidden')" class="bg-blue-600 text-white px-3 py-2 rounded text-sm">Import CSV</button>
    </div>
</div>

<div class="bg-white rounded shadow overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">RD Number</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Plant</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Orig GR #</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($rts as $r): ?>
            <tr class="hover:bg-gray-50">
                <td class="px-6 py-4 text-sm font-mono text-red-600"><?= $r['rd_number'] ?></td>
                <td class="px-6 py-4 text-sm"><?= $r['date'] ?></td>
                <td class="px-6 py-4 text-sm"><?= $r['plant_name'] ?></td>
                <td class="px-6 py-4 text-sm"><?= $r['gr_number'] ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div id="importRts" class="hidden fixed inset-0 bg-black bg-opacity-50 flex items-center justify-center">
    <div class="bg-white p-6 rounded w-96">
        <h3 class="font-bold mb-4">Import RTS CSV</h3>
        <form action="/revenue/rts/import" method="POST" enctype="multipart/form-data">
            <input type="file" name="csv_file" accept=".csv" required class="w-full border p-2 mb-4">
            <div class="flex justify-end gap-2">
                <button type="button" onclick="document.getElementById('importRts').classList.add('hidden')" class="px-3 py-1 bg-gray-200 rounded">Cancel</button>
                <button type="submit" class="px-3 py-1 bg-blue-600 text-white rounded">Upload</button>
            </div>
        </form>
    </div>
</div>