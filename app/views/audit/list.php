<h2 class="text-2xl font-bold text-gray-800 mb-6">Audit Trail</h2>

<div class="bg-white rounded-lg shadow overflow-hidden">
    <table class="min-w-full text-sm text-left text-gray-500">
        <thead class="text-xs text-gray-700 uppercase bg-gray-50">
            <tr>
                <th class="px-6 py-3">Timestamp</th>
                <th class="px-6 py-3">User</th>
                <th class="px-6 py-3">Event</th>
                <th class="px-6 py-3">Entity</th>
                <th class="px-6 py-3">Details (Changes)</th>
                <th class="px-6 py-3">Hash Check</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $log): ?>
            <tr class="bg-white border-b hover:bg-gray-50">
                <td class="px-6 py-4 font-mono text-xs"><?php echo $log['created_at']; ?></td>
                <td class="px-6 py-4 font-medium text-gray-900"><?php echo htmlspecialchars($log['username']); ?></td>
                <td class="px-6 py-4">
                    <span class="bg-blue-100 text-blue-800 text-xs font-medium px-2.5 py-0.5 rounded">
                        <?php echo strtoupper($log['event_type']); ?>
                    </span>
                </td>
                <td class="px-6 py-4">
                    <?php echo $log['entity_type'] . " #" . $log['entity_id']; ?>
                </td>
                <td class="px-6 py-4 text-xs font-mono text-gray-600 truncate max-w-xs">
                    <?php echo htmlspecialchars(substr($log['after_json'], 0, 50)) . '...'; ?>
                </td>
                <td class="px-6 py-4 text-xs font-mono text-green-600">
                    <i class="fa-solid fa-link"></i> <?php echo substr($log['curr_hash'], 0, 8); ?>...
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>