<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link rel="stylesheet" href="../css/base/variables.css?v=3.0">
<link rel="stylesheet" href="../css/user.css?v=3.0">
<link rel="stylesheet" href="../css/layout/notifications.css?v=3.0">
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../js/notifications.js"></script>

<!-- Prevent Sidebar Flash/Animation -->
<script>
    (function () {
        const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
        if (collapsed && window.innerWidth > 992) {
            document.documentElement.classList.add('sidebar-initial-collapsed');
        }
    })();

    // Real-time Clock Functionality
    function updateClock() {
        const clockElement = document.getElementById('real-time-clock');
        if (!clockElement) return;

        const now = new Date();
        const options = {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        };
        clockElement.textContent = now.toLocaleTimeString('en-US', options);
    }

    // Update every second
    setInterval(updateClock, 1000);
    // Initial call
    document.addEventListener('DOMContentLoaded', updateClock);
</script>