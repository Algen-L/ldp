/**
 * Admin Dashboard - Interactive Logic
 * Handles dropdowns and Chart.js initializations.
 * Expects 'dashboardData' globally available.
 */

document.addEventListener('DOMContentLoaded', function () {
    // Custom Dropdown Logic
    const dropdown = document.getElementById('filterDropdown');
    const trigger = document.getElementById('dropdownTrigger');
    const menu = document.getElementById('dropdownMenu');
    const input = document.getElementById('filterInput');
    const text = document.getElementById('selectedFilterText');
    const items = document.querySelectorAll('.dropdown-item-custom');
    const customDateInputs = document.getElementById('customDateInputs');
    const filterForm = document.getElementById('filterForm');

    trigger.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('show');
        trigger.classList.toggle('active');
    });

    items.forEach(item => {
        item.addEventListener('click', () => {
            const value = item.getAttribute('data-value');

            // Update value and text
            input.value = value;
            text.textContent = item.textContent.trim();

            // Update active state
            items.forEach(i => i.classList.remove('active'));
            item.classList.add('active');

            // Handle Custom Range
            if (value === 'custom') {
                customDateInputs.style.display = 'flex';
                menu.classList.remove('show');
                trigger.classList.remove('active');
            } else {
                customDateInputs.style.display = 'none';
                // Clear date inputs when switching away from custom
                const dateFromInput = filterForm.querySelector('input[name="date_from"]');
                const dateToInput = filterForm.querySelector('input[name="date_to"]');
                if (dateFromInput) dateFromInput.value = '';
                if (dateToInput) dateToInput.value = '';

                // Submit form automatically
                filterForm.submit();
            }
        });
    });

    // Close when clicking outside
    document.addEventListener('click', (e) => {
        if (!dropdown.contains(e.target)) {
            menu.classList.remove('show');
            trigger.classList.remove('active');
        }
    });

    // --- Charts ---
    // Ensure data exists before initializing
    if (typeof dashboardData === 'undefined') {
        console.error('Dashboard data not found.');
        return;
    }

    // Submission Frequency Chart (Line)
    const freqCtx = document.getElementById('frequencyChart').getContext('2d');
    new Chart(freqCtx, {
        type: 'line',
        data: {
            labels: dashboardData.freqLabels,
            datasets: [{
                label: dashboardData.isHR ? 'New Registrations' : 'Submissions',
                data: dashboardData.freqValues,
                borderColor: '#3282b8',
                background: 'rgba(50, 130, 184, 0.1)',
                backgroundColor: (context) => {
                    const chart = context.chart;
                    const { ctx, chartArea } = chart;
                    if (!chartArea) return null;
                    const gradient = ctx.createLinearGradient(0, chartArea.bottom, 0, chartArea.top);
                    gradient.addColorStop(0, 'rgba(50, 130, 184, 0)');
                    gradient.addColorStop(1, 'rgba(50, 130, 184, 0.15)');
                    return gradient;
                },
                borderWidth: 3,
                fill: true,
                tension: 0.4,
                pointBackgroundColor: 'white',
                pointBorderColor: '#3282b8',
                pointBorderWidth: 2,
                pointRadius: 4,
                pointHoverRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 10,
                    displayColors: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { precision: 0, color: '#64748b' },
                    grid: { color: 'rgba(0,0,0,0.05)' }
                },
                x: {
                    ticks: { color: '#64748b', font: { size: 10 } },
                    grid: { display: false }
                }
            }
        }
    });

    // Office Distribution Chart (Doughnut)
    const officeCtx = document.getElementById('officeChart').getContext('2d');
    new Chart(officeCtx, {
        type: 'doughnut',
        data: {
            labels: ['OSDS', 'CID', 'SGOD'],
            datasets: [{
                data: [
                    dashboardData.osdsCount,
                    dashboardData.cidCount,
                    dashboardData.sgodCount
                ],
                backgroundColor: ['#f97316', '#eab308', '#0ea5e9'],
                borderWidth: 0,
                hoverOffset: 10
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '70%',
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: 'rgba(15, 23, 42, 0.9)',
                    padding: 10,
                    displayColors: true
                }
            }
        }
    });

    // --- API & AJAX Handling ---
    window.refreshDashboardData = function (filters = {}) {
        const queryParams = new URLSearchParams(filters).toString();

        fetch(`../admin/api/dashboard_api.php?${queryParams}`)
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    console.error('API Error:', data.error);
                    return;
                }

                // Update Charts
                const freqChart = Chart.getChart("frequencyChart");
                if (freqChart) {
                    freqChart.data.labels = data.charts.frequency.labels;
                    freqChart.data.datasets[0].data = data.charts.frequency.values;
                    freqChart.update();
                }

                const officeChart = Chart.getChart("officeChart");
                if (officeChart) {
                    officeChart.data.datasets[0].data = [
                        data.charts.office.osds,
                        data.charts.office.cid,
                        data.charts.office.sgod
                    ];
                    officeChart.update();
                }

                // Update Legend Values
                const lOSDS = document.getElementById('legendOSDS');
                const lCID = document.getElementById('legendCID');
                const lSGOD = document.getElementById('legendSGOD');
                if (lOSDS) lOSDS.textContent = (data.charts.office.osds || 0).toLocaleString();
                if (lCID) lCID.textContent = (data.charts.office.cid || 0).toLocaleString();
                if (lSGOD) lSGOD.textContent = (data.charts.office.sgod || 0).toLocaleString();
            })
            .catch(error => console.error('Fetch error:', error));
    };
});
