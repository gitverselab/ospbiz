<div class="flex justify-between items-center mb-6">
    <h2 class="text-2xl font-bold text-gray-800">Journal Entries</h2>
    <a href="/journal/create" class="px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 shadow-sm">
        <i class="fa-solid fa-plus mr-2"></i> New Entry
    </a>
</div>

<div class="bg-white rounded-lg shadow border border-gray-200 overflow-hidden">
    <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
            <tr>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Ref #</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Description</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created By</th>
                <th class="px-6 py-3 text-right text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
            </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
            <?php if (empty($journals)): ?>
                <tr><td colspan="6" class="px-6 py-4 text-center text-gray-500">No journals found.</td></tr>
            <?php else: ?>
                <?php foreach ($journals as $j): ?>
                <tr class="hover:bg-gray-50 transition-colors">
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-900"><?php echo htmlspecialchars($j['date']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm font-mono text-gray-600"><?php echo htmlspecialchars($j['reference_no']); ?></td>
                    <td class="px-6 py-4 text-sm text-gray-700"><?php echo htmlspecialchars($j['description']); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap">
                        <?php 
                        $colors = [
                            'draft' => 'bg-gray-100 text-gray-800',
                            'submitted' => 'bg-blue-100 text-blue-800',
                            'approved' => 'bg-green-100 text-green-800',
                            'posted' => 'bg-purple-100 text-purple-800',
                            'rejected' => 'bg-red-100 text-red-800'
                        ];
                        $badge = $colors[$j['status']] ?? 'bg-gray-100 text-gray-800';
                        ?>
                        <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full <?php echo $badge; ?>">
                            <?php echo ucfirst($j['status']); ?>
                        </span>
                    </td>
                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($j['username'] ?? 'System'); ?></td>
                    <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                        <a href="/journal/view?id=<?php echo $j['id']; ?>" class="text-blue-600 hover:text-blue-900">View</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>