<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acme ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .nav-link.active { background-color: #374151; border-left: 4px solid #3B82F6; }
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="bg-gray-100 h-screen flex overflow-hidden">

    <aside class="w-64 bg-slate-900 text-white flex flex-col shadow-xl">
        <div class="h-16 flex items-center justify-center border-b border-slate-800">
            <h1 class="text-xl font-bold tracking-wider text-blue-400">
                <i class="fa-solid fa-layer-group mr-2"></i>OSPBIZ
            </h1>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <div class="px-4 mb-2 text-xs text-slate-500 uppercase tracking-wider">Main</div>
            
            <a href="/" class="flex items-center px-6 py-3 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <i class="fa-solid fa-gauge w-6"></i> Dashboard
            </a>

            <div class="px-4 mt-6 mb-2 text-xs text-slate-500 uppercase tracking-wider">Accounting</div>
            
            <a href="/journal/create" class="flex items-center px-6 py-3 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors group">
                <i class="fa-solid fa-pen-to-square w-6 group-hover:text-blue-400"></i> Journal Entry
            </a>
            
            <a href="/journal/list" class="flex items-center px-6 py-3 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <i class="fa-solid fa-list w-6"></i> View Journals
            </a>

            <div class="px-4 mt-6 mb-2 text-xs text-slate-500 uppercase tracking-wider">System</div>
            
            <a href="/audit/logs" class="flex items-center px-6 py-3 text-slate-300 hover:bg-slate-800 hover:text-white transition-colors">
                <i class="fa-solid fa-shield-halved w-6"></i> Audit Trail
            </a>
        </nav>

        <div class="p-4 border-t border-slate-800 bg-slate-900">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-500 flex items-center justify-center text-xs font-bold">
                    AD
                </div>
                <div>
                    <div class="text-sm font-medium">Admin User</div>
                    <div class="text-xs text-slate-500">Headquarters</div>
                </div>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-8 shadow-sm">
            <div class="text-gray-500 text-sm">
                <?php echo date('l, F j, Y'); ?>
            </div>
            <div class="flex gap-4">
                <button class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-bell"></i></button>
                <button class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-gear"></i></button>
            </div>
        </header>

        <div class="flex-1 overflow-y-auto p-8">
            <?php 
            if (isset($childView) && file_exists($childView)) {
                include $childView;
            } else {
                echo "<div class='text-red-500 p-4 bg-red-50 rounded'>Error: View not found.</div>";
            }
            ?>
        </div>
    </main>

</body>
</html>