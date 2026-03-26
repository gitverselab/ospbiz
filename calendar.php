<?php
// calendar.php
require_once "includes/header.php";
?>

<script src='https://cdn.jsdelivr.net/npm/fullcalendar@6.1.14/index.global.min.js'></script>

<style>
    /* Custom Mobile Tweaks for FullCalendar */
    @media (max-width: 768px) {
        .fc-header-toolbar {
            flex-direction: column;
            gap: 10px;
        }
        .fc-toolbar-chunk {
            display: flex;
            justify-content: space-between;
            width: 100%;
        }
        /* Center the title row */
        .fc-toolbar-chunk:nth-child(2) {
            justify-content: center;
            order: -1; /* Move title to top on mobile if desired, or keep as is */
            margin-bottom: 5px;
        }
        .fc-toolbar-title {
            font-size: 1.2rem !important;
        }
        /* Make buttons slightly larger for touch */
        .fc-button {
            padding: 0.4rem 0.8rem !important;
        }
    }

    @media print {
        body * { visibility: hidden; }
        .no-print { display: none !important; }
        #printable-area, #printable-area * { visibility: visible; }
        #printable-area { position: absolute; left: 0; top: 0; width: 100%; padding: 20px; }
        .fc-header-toolbar { display: none !important; }
        .fc-list-event-title a { text-decoration: none; color: black; }
    }
</style>

<div class="bg-white p-4 md:p-6 rounded-lg shadow-md">
    <div class="flex justify-between items-center mb-4 no-print">
        <h2 class="text-xl md:text-2xl font-bold text-gray-800">Calendar</h2>
        <button id="print-button" onclick="window.print()" class="bg-gray-500 hover:bg-gray-600 text-white font-bold py-2 px-4 rounded-lg text-sm hidden">
            Print List
        </button>
    </div>
    
    <div id="printable-area">
        <div id='calendar'></div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const calendarEl = document.getElementById('calendar');
    const printButton = document.getElementById('print-button');
    
    // Detect mobile for initial settings
    const isMobile = window.innerWidth < 768;
    const initialView = isMobile ? 'listWeek' : 'dayGridMonth';
    const headerRight = isMobile ? 'dayGridMonth,listWeek' : 'dayGridMonth,timeGridWeek,listWeek';

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: initialView,
        height: 'auto', // Allows calendar to grow naturally
        contentHeight: 'auto',
        headerToolbar: {
            left: 'prev,next today',
            center: 'title',
            right: headerRight
        },
        
        // Auto-switch view on resize/rotate
        windowResize: function(view) {
            if (window.innerWidth < 768) {
                calendar.changeView('listWeek');
            } else {
                calendar.changeView('dayGridMonth');
            }
        },

        events: 'api/get_calendar_events.php',
        
        eventClick: function(info) {
            info.jsEvent.preventDefault(); 
            if (info.event.url) {
                window.open(info.event.url, "_self");
            }
        },

        viewDidMount: function(arg) {
            // Show print button only in List views
            if (arg.view.type.startsWith('list')) {
                printButton.classList.remove('hidden');
            } else {
                printButton.classList.add('hidden');
            }
        },

        eventContent: function(arg) {
            if (arg.view.type.startsWith('list')) {
                let props = arg.event.extendedProps;
                let detailsHtml = '<div class="text-xs text-gray-600 pl-0 md:pl-4 mt-1">';
                
                // Badges
                let typeBadge = '';
                const badgeClasses = "px-2 py-1 text-xs font-semibold rounded-full mr-2 inline-block";

                if (props.type === 'Purchase') {
                    typeBadge = `<span class="${badgeClasses} bg-orange-100 text-orange-800">Purchase</span>`;
                    if (props.po_number) detailsHtml += `<div><strong>PO #:</strong> ${props.po_number}</div>`;
                    if (props.description) detailsHtml += `<div><strong>Desc:</strong> ${props.description}</div>`;
                    if (props.items) detailsHtml += `<div><strong>Items:</strong> ${props.items}</div>`;
                } 
                else if (props.type === 'Bill') {
                    typeBadge = `<span class="${badgeClasses} bg-red-100 text-red-800">Bill</span>`;
                    if (props.bill_number) detailsHtml += `<div><strong>Bill #:</strong> ${props.bill_number}</div>`;
                    if (props.description) detailsHtml += `<div><strong>Desc:</strong> ${props.description}</div>`;
                }
                else if (props.type === 'Check') {
                    typeBadge = `<span class="${badgeClasses} bg-purple-100 text-purple-800">Check</span>`;
                    if (props.check_number) detailsHtml += `<div><strong>Check #:</strong> ${props.check_number}</div>`;
                    if (props.linked_to) detailsHtml += `<div><strong>For:</strong> ${props.linked_to}</div>`;
                }

                detailsHtml += '</div>';

                let finalHtml = `
                    <div class="fc-list-event-main-frame w-full">
                        <div class="fc-list-event-title">
                            <a href="${arg.event.url}" class="flex flex-col md:flex-row md:items-center">
                                <div class="mb-1 md:mb-0">${typeBadge}</div>
                                <span class="font-medium">${arg.event.title}</span>
                            </a>
                        </div>
                        ${detailsHtml}
                    </div>
                `;
                return { html: finalHtml };
            }
            return true; 
        },
        
        eventTimeFormat: {
            hour: 'numeric',
            minute: '2-digit',
            meridiem: 'short'
        }
    });
    calendar.render();
});
</script>

<?php
require_once "includes/footer.php";
?>