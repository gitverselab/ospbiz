<form action="/settings/receipt/update" method="POST" class="max-w-4xl mx-auto bg-white p-8 rounded shadow border">
    <h2 class="text-2xl font-bold mb-6 text-gray-800">Receipt / Invoice Compliance Settings</h2>
    
    <div class="grid grid-cols-2 gap-6 mb-4">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">Registered Company Name</label>
            <input type="text" name="company_name" value="<?= htmlspecialchars($settings['company_name'] ?? '') ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">VAT Registered?</label>
            <select name="is_vat_reg" class="w-full border p-2 rounded">
                <option value="1" <?= ($settings['is_vat_reg']??1)==1?'selected':'' ?>>Yes (VAT Registered)</option>
                <option value="0" <?= ($settings['is_vat_reg']??1)==0?'selected':'' ?>>No (Non-VAT)</option>
            </select>
        </div>
    </div>

    <div class="mb-4">
        <label class="block text-xs font-bold text-gray-500 uppercase">Registered Address</label>
        <textarea name="company_address" class="w-full border p-2 rounded" rows="2"><?= htmlspecialchars($settings['company_address'] ?? '') ?></textarea>
    </div>

    <div class="grid grid-cols-3 gap-6 mb-4">
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">TIN</label>
            <input type="text" name="company_tin" value="<?= htmlspecialchars($settings['company_tin'] ?? '') ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">Business Style</label>
            <input type="text" name="business_style" value="<?= htmlspecialchars($settings['business_style'] ?? '') ?>" class="w-full border p-2 rounded">
        </div>
        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase">BIR Permit / ATP No.</label>
            <input type="text" name="bir_permit_no" value="<?= htmlspecialchars($settings['bir_permit_no'] ?? '') ?>" class="w-full border p-2 rounded">
        </div>
    </div>

    <div class="bg-gray-50 p-4 rounded border mb-6">
        <h3 class="font-bold text-sm mb-3">ATP Details</h3>
        <div class="grid grid-cols-4 gap-4">
            <div>
                <label class="block text-xs text-gray-500">Date Issued</label>
                <input type="date" name="date_issued" value="<?= $settings['date_issued'] ?? '' ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Valid Until</label>
                <input type="date" name="valid_until" value="<?= $settings['valid_until'] ?? '' ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Serial Start</label>
                <input type="text" name="serial_begin" value="<?= $settings['serial_begin'] ?? '' ?>" class="w-full border p-2 rounded">
            </div>
            <div>
                <label class="block text-xs text-gray-500">Serial End</label>
                <input type="text" name="serial_end" value="<?= $settings['serial_end'] ?? '' ?>" class="w-full border p-2 rounded">
            </div>
        </div>
    </div>

    <div class="text-right">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded hover:bg-blue-700 font-bold">Save Settings</button>
    </div>
</form>