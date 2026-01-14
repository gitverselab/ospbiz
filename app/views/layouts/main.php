<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Acme ERP</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* THEME CONFIGURATION */
        :root {
            --sidebar-bg: #0f172a;      /* slate-900 */
            --sidebar-hover: #1e293b;   /* slate-800 */
            --accent-color: #3b82f6;    /* blue-500 */
            --bg-body: #f3f4f6;         /* gray-100 */
        }
        
        .theme-sidebar { background-color: var(--sidebar-bg); }
        .theme-hover:hover { background-color: var(--sidebar-hover); }
        .theme-text-accent { color: var(--accent-color); }
        
        body { font-family: 'Inter', sans-serif; background-color: var(--bg-body); }
    </style>
</head>
<body class="h-screen flex overflow-hidden">

    <aside class="w-64 theme-sidebar text-white flex flex-col shadow-xl">
        <div class="h-16 flex items-center justify-center border-b border-gray-700">
            <h1 class="text-xl font-bold tracking-wider theme-text-accent">
                <i class="fa-solid fa-layer-group mr-2"></i>OSPBIZ
            </h1>
        </div>

        <nav class="flex-1 overflow-y-auto py-4">
            <div class="px-4 mb-2 text-xs text-gray-500 uppercase tracking-wider">Main</div>
            <a href="/" class="flex items-center px-6 py-3 text-gray-300 theme-hover transition-colors">
                <i class="fa-solid fa-gauge w-6"></i> Dashboard
            </a>

            <div class="px-4 mt-6 mb-2 text-xs text-gray-500 uppercase tracking-wider">Accounting</div>
            <a href="/journal/create" class="flex items-center px-6 py-3 text-gray-300 theme-hover transition-colors">
                <i class="fa-solid fa-pen-to-square w-6"></i> Journal Entry
            </a>
            <a href="/journal/list" class="flex items-center px-6 py-3 text-gray-300 theme-hover transition-colors">
                <i class="fa-solid fa-list w-6"></i> View Journals
            </a>

            <div class="px-4 mt-6 mb-2 text-xs text-gray-500 uppercase tracking-wider">System</div>
            <a href="/audit/logs" class="flex items-center px-6 py-3 text-gray-300 theme-hover transition-colors">
                <i class="fa-solid fa-shield-halved w-6"></i> Audit Trail
            </a>
        </nav>
        
        <div class="p-4 border-t border-gray-700">
            <div class="flex items-center gap-3">
                <div class="w-8 h-8 rounded-full bg-blue-600 flex items-center justify-center text-xs font-bold">AD</div>
                <div><div class="text-sm font-medium">Admin</div><div class="text-xs text-gray-400">HQ</div></div>
            </div>
        </div>
    </aside>

    <main class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="bg-white border-b border-gray-200 h-16 flex items-center justify-between px-8 shadow-sm">
            <div class="text-gray-500 text-sm"><?php echo date('l, F j, Y'); ?></div>
            <div class="flex gap-4">
                <button class="text-gray-400 hover:text-gray-600"><i class="fa-solid fa-bell"></i></button>
            </div>
        </header>
        <div class="flex-1 overflow-y-auto p-8">
            <?php 
            if (isset($childView) && file_exists($childView)) {
                include $childView;
            } else {
                echo "<div class='p-4 bg-red-100 text-red-700 rounded'>Error: View file not found: $childView</div>";
            }
            ?>
        </div>
    </main>
</body>
</html>