<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="bg-white rounded-lg p-5 shadow-sm border border-gray-200">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Cash on Hand</div>
        <div class="text-2xl font-bold text-green-600">₱78,819.48</div>
    </div>
    <div class="bg-white rounded-lg p-5 shadow-sm border border-gray-200">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Bank Balance</div>
        <div class="text-2xl font-bold text-blue-600">₱3,458,413.40</div>
    </div>
    <div class="bg-white rounded-lg p-5 shadow-sm border border-gray-200">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Accounts Payable</div>
        <div class="text-2xl font-bold text-red-500">₱21,219,727.58</div>
    </div>
    <div class="bg-white rounded-lg p-5 shadow-sm border border-gray-200">
        <div class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Accounts Receivable</div>
        <div class="text-2xl font-bold text-orange-500">₱0.00</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    <div class="lg:col-span-2 bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Cash Flow (Jan 2026)</h3>
        <canvas id="cashFlowChart" height="150"></canvas>
    </div>
    
    <div class="bg-white p-6 rounded-lg shadow-sm border border-gray-200">
        <h3 class="text-sm font-bold text-gray-700 mb-4">Expense Breakdown</h3>
        <div class="relative h-48">
            <canvas id="expenseChart"></canvas>
        </div>
        <div class="mt-4 space-y-2">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center"><span class="w-3 h-3 bg-red-500 rounded-full mr-2"></span>Raw Materials</div>
                <span class="font-bold">45%</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center"><span class="w-3 h-3 bg-blue-500 rounded-full mr-2"></span>Operations</div>
                <span class="font-bold">30%</span>
            </div>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200">
        <h3 class="text-lg font-bold text-gray-800 mb-4">Quick Actions</h3>
        <div class="grid grid-cols-1 gap-3">
            <a href="/journal/create" class="block w-full text-center px-4 py-3 bg-blue-600 text-white rounded hover:bg-blue-700 font-medium text-sm transition">
                <i class="fa-solid fa-plus mr-2"></i> New Journal Entry
            </a>
            <button class="block w-full px-4 py-3 bg-white border border-gray-300 text-gray-700 rounded hover:bg-gray-50 font-medium text-sm transition">
                <i class="fa-solid fa-file-export mr-2"></i> Generate Balance Sheet
            </button>
        </div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200 relative overflow-hidden">
        <div class="absolute right-0 top-0 p-4 opacity-10"><i class="fa-solid fa-file-pen text-6xl text-blue-600"></i></div>
        <h3 class="text-gray-500 text-sm font-medium mb-1">Draft Journals</h3>
        <div class="text-4xl font-bold text-gray-800">12</div>
        <div class="text-xs text-blue-600 mt-2 font-medium">Waiting for submission</div>
    </div>

    <div class="bg-white rounded-lg shadow-sm p-6 border border-gray-200 relative overflow-hidden">
        <div class="absolute right-0 top-0 p-4 opacity-10"><i class="fa-solid fa-clock text-6xl text-yellow-600"></i></div>
        <h3 class="text-gray-500 text-sm font-medium mb-1">Pending Approval</h3>
        <div class="text-4xl font-bold text-gray-800">5</div>
        <div class="text-xs text-yellow-600 mt-2 font-medium">Requires Manager Review</div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 font-bold text-sm text-gray-700">Upcoming Purchases</div>
        <table class="min-w-full text-sm text-left">
            <tbody class="divide-y divide-gray-100">
                <tr class="hover:bg-gray-50"><td class="px-6 py-3 text-blue-600 font-medium">NEW EVERGOOD</td><td class="px-6 py-3 text-right">₱577,500.00</td></tr>
                <tr class="hover:bg-gray-50"><td class="px-6 py-3 text-blue-600 font-medium">MARIVIC ASPREC</td><td class="px-6 py-3 text-right">₱91.75</td></tr>
            </tbody>
        </table>
    </div>

    <div class="bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden">
        <div class="bg-gray-50 px-6 py-4 border-b border-gray-200 font-bold text-sm text-gray-700">Upcoming Bills</div>
        <table class="min-w-full text-sm text-left">
            <tbody class="divide-y divide-gray-100">
                <tr class="hover:bg-gray-50"><td class="px-6 py-3 text-blue-600 font-medium">INTERNET BILL</td><td class="px-6 py-3 text-right">₱3,500.00</td></tr>
                <tr class="hover:bg-gray-50"><td class="px-6 py-3 text-blue-600 font-medium">OFFICE RENT</td><td class="px-6 py-3 text-right">₱25,000.00</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>
    // Expense Doughnut Chart
    const ctxExpense = document.getElementById('expenseChart').getContext('2d');
    new Chart(ctxExpense, {
        type: 'doughnut',
        data: {
            labels: ['Raw Materials', 'Operations', 'Rent', 'Salaries'],
            datasets: [{
                data: [45, 30, 15, 10],
                backgroundColor: ['#ef4444', '#3b82f6', '#eab308', '#a855f7'],
                borderWidth: 0
            }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } } }
    });

    // Cash Flow Bar Chart
    const ctxFlow = document.getElementById('cashFlowChart').getContext('2d');
    new Chart(ctxFlow, {
        type: 'bar',
        data: {
            labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
            datasets: [
                { label: 'Income', data: [50000, 75000, 60000, 90000], backgroundColor: '#4ade80' },
                { label: 'Expense', data: [40000, 55000, 45000, 60000], backgroundColor: '#f87171' }
            ]
        },
        options: { responsive: true, scales: { y: { beginAtZero: true } } }
    });
</script>