<div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden min-h-[400px]">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Check #</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Date</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Payee</th>
                <th class="px-6 py-3 text-left text-xs font-bold text-gray-500 uppercase">Bank Account</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Status</th>
                <th class="px-6 py-3 text-right text-xs font-bold text-gray-500 uppercase">Amount</th>
                <th class="px-6 py-3 text-center text-xs font-bold text-gray-500 uppercase">Actions</th>
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-200 bg-white">
            <?php if(empty($checks)): ?>
                <tr><td colspan="7" class="px-6 py-8 text-center text-gray-500 italic">No checks found.</td></tr>
            <?php else: ?>
                <?php foreach ($checks as $c): ?>
                <tr class="hover:bg-gray-50 group">
                    <td class="px-6 py-4 font-mono font-bold text-gray-700"><?= htmlspecialchars($c['check_number']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-600"><?= date('M j, Y', strtotime($c['date'])) ?></td>
                    <td class="px-6 py-4 text-sm font-bold text-gray-900 uppercase"><?= htmlspecialchars($c['payee_name']) ?></td>
                    <td class="px-6 py-4 text-sm text-gray-500">
                        <?= htmlspecialchars($c['bank_name']) ?>
                        <div class="text-xs text-gray-400 font-mono"><?= htmlspecialchars($c['account_number'] ?? '') ?></div>
                    </td>
                    <td class="px-6 py-4 text-center">
                        <?php 
                            $badge = match($c['status']) {
                                'cleared' => 'bg-green-100 text-green-800',
                                'bounced' => 'bg-orange-100 text-orange-800',
                                'cancelled' => 'bg-gray-200 text-gray-500 line-through',
                                default => 'bg-blue-50 text-blue-600'
                            };
                        ?>
                        <span class="px-3 py-1 text-xs font-bold rounded-full <?= $badge ?>">
                            <?= ucfirst($c['status']) ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 text-right font-bold text-gray-800">₱<?= number_format($c['amount'], 2) ?></td>
                    
                    <td class="px-6 py-4 text-center relative">
                        <div class="relative inline-block text-left">
                            <button onclick="toggleDropdown(<?= $c['id'] ?>)" class="bg-white border hover:bg-gray-50 text-gray-700 font-semibold py-1 px-3 rounded inline-flex items-center text-xs shadow-sm">
                                Actions <i class="fa-solid fa-caret-down ml-2"></i>
                            </button>
                            
                            <div id="dropdown-<?= $c['id'] ?>" class="hidden absolute right-0 mt-2 w-48 bg-white rounded-md shadow-lg z-50 border border-gray-200 text-left">
                                <div class="py-1">
                                    
                                    <?php if($c['status'] == 'issued'): ?>
                                    <form action="/bank/checks/status" method="POST">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="status" value="cleared">
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-green-600 hover:bg-green-50">
                                            <i class="fa-solid fa-check mr-2"></i> Reconcile / Clear
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <button onclick='openEditModal(<?= json_encode($c) ?>)' class="w-full text-left px-4 py-2 text-sm text-blue-600 hover:bg-blue-50">
                                        <i class="fa-solid fa-pen mr-2"></i> Edit Details
                                    </button>

                                    <?php if($c['status'] == 'issued'): ?>
                                    <form action="/bank/checks/status" method="POST" onsubmit="return confirm('Mark as BOUNCED? Balance will be restored.');">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="status" value="bounced">
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-orange-600 hover:bg-orange-50">
                                            <i class="fa-solid fa-triangle-exclamation mr-2"></i> Mark as Bounced
                                        </button>
                                    </form>

                                    <form action="/bank/checks/status" method="POST" onsubmit="return confirm('CANCEL check? Balance will be restored.');">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <input type="hidden" name="status" value="cancelled">
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-gray-600 hover:bg-gray-50">
                                            <i class="fa-solid fa-ban mr-2"></i> Cancel Check
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <div class="border-t border-gray-100 my-1"></div>

                                    <form action="/bank/checks/delete" method="POST" onsubmit="return confirm('PERMANENTLY DELETE? This cannot be undone.');">
                                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                                        <button type="submit" class="w-full text-left px-4 py-2 text-sm text-red-600 hover:bg-red-50 font-bold">
                                            <i class="fa-solid fa-trash mr-2"></i> Delete (Admin)
                                        </button>
                                    </form>

                                </div>
                            </div>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div id="dropdownOverlay" onclick="closeAllDropdowns()" class="hidden fixed inset-0 z-40 bg-transparent h-full w-full"></div>

<div id="editCheckModal" class="hidden fixed inset-0 bg-black bg-opacity-50 z-50 flex items-center justify-center p-4">
    <div class="bg-white rounded-lg shadow-xl w-96 p-6">
        <h3 class="text-lg font-bold mb-4 text-gray-800">Edit Check Details</h3>
        <form action="/bank/checks/update" method="POST">
            <input type="hidden" name="id" id="edit_id">
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Check Number</label>
                <input type="text" name="check_number" id="edit_number" class="w-full border p-2 rounded bg-gray-50 font-mono">
            </div>
            
            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Check Date</label>
                <input type="date" name="date" id="edit_date" class="w-full border p-2 rounded">
            </div>

            <div class="mb-3">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Payee Name</label>
                <input type="text" name="payee_name" id="edit_payee" class="w-full border p-2 rounded font-bold">
            </div>

            <div class="mb-4">
                <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Memo</label>
                <input type="text" name="memo" id="edit_memo" class="w-full border p-2 rounded">
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <button type="button" onclick="document.getElementById('editCheckModal').classList.add('hidden')" class="px-4 py-2 bg-gray-200 text-gray-700 rounded text-sm font-bold">Cancel</button>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded text-sm font-bold shadow hover:bg-blue-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
function toggleDropdown(id) {
    const menu = document.getElementById('dropdown-' + id);
    const overlay = document.getElementById('dropdownOverlay');
    const isHidden = menu.classList.contains('hidden');

    closeAllDropdowns(); // Close others

    if (isHidden) {
        menu.classList.remove('hidden');
        overlay.classList.remove('hidden');
    }
}

function closeAllDropdowns() {
    document.querySelectorAll('[id^="dropdown-"]').forEach(el => el.classList.add('hidden'));
    document.getElementById('dropdownOverlay').classList.add('hidden');
}

function openEditModal(data) {
    closeAllDropdowns();
    document.getElementById('edit_id').value = data.id;
    document.getElementById('edit_number').value = data.check_number;
    document.getElementById('edit_date').value = data.date;
    document.getElementById('edit_payee').value = data.payee_name;
    document.getElementById('edit_memo').value = data.memo;
    document.getElementById('editCheckModal').classList.remove('hidden');
}
</script>