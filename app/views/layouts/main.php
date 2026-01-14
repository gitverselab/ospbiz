<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Enterprise Accounting</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style> .audit-font { font-family: 'Courier New', monospace; } </style>
</head>
<body class="bg-gray-100 flex h-screen">
    <aside class="w-64 bg-slate-800 text-white flex flex-col">
        <div class="p-4 font-bold text-xl border-b border-slate-700">Acme ERP</div>
        <nav class="flex-1 p-4 space-y-2">
            <a href="/dashboard" class="block p-2 hover:bg-slate-700 rounded">Dashboard</a>
            <a href="/journal/create" class="block p-2 hover:bg-slate-700 rounded">New Journal</a>
            <a href="/reports" class="block p-2 hover:bg-slate-700 rounded">Reports</a>
            <a href="/audit/logs" class="block p-2 hover:bg-slate-700 rounded">Audit Trail</a>
        </nav>
        <div class="p-4 border-t border-slate-700">
            <div class="text-sm text-gray-400">Branch: Headquarters</div>
            <a href="/logout" class="text-red-400 text-sm hover:underline">Logout</a>
        </div>
    </aside>

    <main class="flex-1 overflow-y-auto p-8">
        <?php include $childView; ?>
    </main>
</body>
</html>