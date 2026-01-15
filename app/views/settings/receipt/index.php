<div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    
    <div class="bg-white p-6 rounded shadow border">
        <h2 class="text-xl font-bold mb-4 text-gray-800">Register New Booklet</h2>
        <form action="/settings/receipt/store_booklet" method="POST">
            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase">Booklet Number / Label</label>
                <input type="text" name="booklet_number" placeholder="e.g. Bk-001" class="w-full border p-2 rounded">
            </div>
            <div class="grid grid-cols-2 gap-4 mb-4">
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Series Start</label>
                    <input type="number" name="series_start" placeholder="e.g. 1001" class="w-full border p-2 rounded" required>
                </div>
                <div>
                    <label class="block text-xs font-bold text-gray-500 uppercase">Series End</label>
                    <input type="number" name="series_end" placeholder="e.g. 1050" class="w-full border p-2 rounded" required>
                </div>
            </div>
            <button type="submit" class="w-full bg-blue-600 text-white py-2 rounded font-bold hover:bg-blue-700">Register Booklet</button>
        </form>
    </div>

    <div class="bg-white p-6 rounded shadow border">
        <h2 class="text-xl font-bold mb-4 text-gray-800">Active Booklets</h2>
        <table class="w-full text-sm text-left">
            <thead class="bg-gray-100 uppercase text-xs">
                <tr>
                    <th class="p-2">Label</th>
                    <th class="p-2">Range</th>
                    <th class="p-2">Next No.</th>
                    <th class="p-2">Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($booklets as $b): ?>
                <tr class="border-b">
                    <td class="p-2 font-bold"><?= $b['booklet_number'] ?></td>
                    <td class="p-2"><?= $b['series_start'] ?> - <?= $b['series_end'] ?></td>
                    <td class="p-2 text-blue-600 font-mono font-bold"><?= $b['current_counter'] ?></td>
                    <td class="p-2">
                        <span class="px-2 py-1 rounded text-xs <?= ($b['status']=='active') ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-600' ?>">
                            <?= ucfirst($b['status']) ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>