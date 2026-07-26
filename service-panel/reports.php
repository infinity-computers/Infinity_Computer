<?php 
include __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/config/db.php';

// Check role: Admins/Super Admins only
if (!isAdmin()) {
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>OMS Reports - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .filter-card {
            background: #fff;
            padding: 20px 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            margin-bottom: 30px;
            display: flex;
            gap: 20px;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .filter-card .form-group {
            margin: 0;
            flex: 1;
            min-width: 150px;
        }

        .reports-layout {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 30px;
            margin-bottom: 40px;
        }

        @media (max-width: 992px) {
            .reports-layout {
                grid-template-columns: 1fr;
            }
        }

        .chart-card {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            box-shadow: var(--shadow);
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .chart-container {
            position: relative;
            width: 100%;
            height: 300px;
        }
    </style>
</head>

<body>
    <header>
        <div class="container" style="padding:0;">
            <a href="../index.html" style="display: flex; align-items: center; gap: 0.6rem; text-decoration: none;">
                <img src="../images/logos/infinity_computer_logo.png" alt="Infinity Computer Logo"
                    style="height: 38px; width: auto;">
                <div style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1;">
                    <span class="brand-text">Infinity<span class="text-accent">Computer</span></span>
                    <span
                        style="font-size: 0.65rem; color: #fb2a71; font-weight: 700; text-transform: uppercase;">Service Panel</span>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Track Service</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="crm.php">CRM Analytics</a></li>
                <li><a href="billing.php">Billing</a></li>
                <li><a href="reports.php" class="header-active">Reports</a></li>
                <li><a href="task_management.php">Task Management</a></li>
                <li><a href="engineers_management.php">Manage Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <!-- Date Filters -->
        <div class="filter-card">
            <div class="form-group">
                <label>Start Date</label>
                <input type="date" id="filter-start" class="form-control" onchange="reloadReports()">
            </div>
            <div class="form-group">
                <label>End Date</label>
                <input type="date" id="filter-end" class="form-control" onchange="reloadReports()">
            </div>
            <button class="btn btn-secondary" onclick="clearFilters()" style="background:#6c757d; color:#fff; padding:12px 20px;">Reset</button>
            <button class="btn btn-primary" onclick="exportPerformanceCSV()" style="padding:12px 25px;">Export CSV</button>
        </div>

        <!-- Section 1: Engineer Performance Table -->
        <div class="card mb-4" style="padding: 30px; margin-bottom: 40px;">
            <div class="card-title" style="margin-bottom: 20px; font-size:1.3rem; font-weight:700; color:var(--primary-dark);">Engineer Performance Metrics</div>
            <div class="table-responsive">
                <table id="performance-table">
                    <thead>
                        <tr>
                            <th>Engineer Name</th>
                            <th>Jobs Assigned</th>
                            <th>Jobs Completed</th>
                            <th>Jobs Pending</th>
                            <th>Avg. Resolution Time</th>
                            <th>Avg. First Response</th>
                            <th>Revenue Generated</th>
                        </tr>
                    </thead>
                    <tbody id="performance-tbody">
                        <tr>
                            <td colspan="7" class="text-center" style="padding: 30px; color: #6c757d;">Loading performance statistics...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Section 2: Chart Breakdown & Monthly Trend -->
        <div class="reports-layout">
            <div class="chart-card">
                <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 20px; width: 100%; text-align: left; color: var(--primary-dark);">Service Type Distribution</h3>
                <div class="chart-container">
                    <canvas id="serviceTypeChart"></canvas>
                </div>
            </div>
            <div class="chart-card">
                <h3 style="font-size: 1.15rem; font-weight: 700; margin-bottom: 20px; width: 100%; text-align: left; color: var(--primary-dark);">Monthly Revenue Trend (Last 6 Months)</h3>
                <div class="chart-container">
                    <canvas id="revenueTrendChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Section 3: Parts Inventory Replaced Summary -->
        <div class="card" style="padding: 30px;">
            <div class="card-title" style="margin-bottom: 20px; font-size:1.3rem; font-weight:700; color:var(--primary-dark);">Parts Replaced Inventory Summary</div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Part Name</th>
                            <th>Total Quantity Used</th>
                            <th>Total Cost Price (₹)</th>
                            <th>Total Selling Price (₹)</th>
                            <th>Net Profit (₹)</th>
                        </tr>
                    </thead>
                    <tbody id="inventory-tbody">
                        <tr>
                            <td colspan="5" class="text-center" style="padding: 30px; color: #6c757d;">Loading parts summary...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script>
        let serviceChart = null;
        let revenueChart = null;
        let performanceData = [];

        document.addEventListener('DOMContentLoaded', () => {
            loadPerformance();
            loadServiceBreakdown();
            loadRevenueTrend();
            loadInventorySummary();
        });

        function reloadReports() {
            loadPerformance();
            loadServiceBreakdown();
            // Trend is last 6 months aggregates, usually not dependent on current small range but we keep it updated
            loadRevenueTrend();
        }

        function clearFilters() {
            document.getElementById('filter-start').value = '';
            document.getElementById('filter-end').value = '';
            reloadReports();
        }

        async function loadPerformance() {
            const start = document.getElementById('filter-start').value;
            const end = document.getElementById('filter-end').value;
            const url = `api/engineer_performance.php?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;

            try {
                const res = await fetch(url);
                const json = await res.json();
                const tbody = document.getElementById('performance-tbody');
                tbody.innerHTML = '';
                if (json.status === 'success' && json.data) {
                    performanceData = json.data;
                    if (performanceData.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding: 20px;">No engineer metrics found.</td></tr>';
                        return;
                    }
                    performanceData.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${row.name}</strong></td>
                            <td>${row.assigned}</td>
                            <td>${row.completed}</td>
                            <td>${row.pending}</td>
                            <td><span style="font-weight:600; color:#475569;">${row.avg_resolution}</span></td>
                            <td><span style="font-weight:600; color:#475569;">${row.avg_response}</span></td>
                            <td><strong>₹${row.revenue.toLocaleString('en-IN')}</strong></td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger" style="padding:20px;">Failed to calculate metrics.</td></tr>';
                }
            } catch (e) {
                console.error("Error loading performance reports", e);
            }
        }

        async function loadServiceBreakdown() {
            const start = document.getElementById('filter-start').value;
            const end = document.getElementById('filter-end').value;
            const url = `api/service_type_breakdown.php?start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}`;

            try {
                const res = await fetch(url);
                const json = await res.json();
                if (json.status === 'success' && json.data) {
                    const labels = json.data.map(item => item.service_type);
                    const counts = json.data.map(item => item.count);

                    if (serviceChart) {
                        serviceChart.destroy();
                    }

                    const ctx = document.getElementById('serviceTypeChart').getContext('2d');
                    serviceChart = new Chart(ctx, {
                        type: 'pie',
                        data: {
                            labels: labels,
                            datasets: [{
                                data: counts,
                                backgroundColor: [
                                    '#3b82f6', '#10b981', '#f59e0b', '#ec4899',
                                    '#8b5cf6', '#06b6d4', '#ef4444', '#64748b'
                                ],
                                borderWeight: 1
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: {
                                legend: {
                                    position: 'right',
                                    labels: {
                                        font: {
                                            family: 'Poppins'
                                        }
                                    }
                                }
                            }
                        }
                    });
                }
            } catch (e) {
                console.error("Error loading service types chart", e);
            }
        }

        async function loadRevenueTrend() {
            try {
                const res = await fetch('api/revenue_trend.php');
                const json = await res.json();
                if (json.status === 'success' && json.data) {
                    const labels = json.data.map(item => item.label);
                    const revenues = json.data.map(item => item.revenue);

                    if (revenueChart) {
                        revenueChart.destroy();
                    }

                    const ctx = document.getElementById('revenueTrendChart').getContext('2d');
                    revenueChart = new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: labels,
                            datasets: [{
                                label: 'Revenue (₹)',
                                data: revenues,
                                borderColor: '#1f5fae',
                                backgroundColor: 'rgba(31, 95, 174, 0.1)',
                                borderWidth: 3,
                                fill: true,
                                tension: 0.3,
                                pointBackgroundColor: '#1f5fae',
                                pointRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: {
                                    beginAtZero: true,
                                    ticks: {
                                        font: { family: 'Poppins' }
                                    }
                                },
                                x: {
                                    ticks: {
                                        font: { family: 'Poppins' }
                                    }
                                }
                            },
                            plugins: {
                                legend: {
                                    display: false
                                }
                            }
                        }
                    });
                }
            } catch (e) {
                console.error("Error loading revenue trends chart", e);
            }
        }

        async function loadInventorySummary() {
            try {
                const res = await fetch('api/get_parts_summary.php');
                const json = await res.json();
                const tbody = document.getElementById('inventory-tbody');
                tbody.innerHTML = '';
                if (json.status === 'success' && json.data) {
                    if (json.data.length === 0) {
                        tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding:20px;">No parts records found.</td></tr>';
                        return;
                    }
                    json.data.forEach(item => {
                        const tr = document.createElement('tr');
                        const totalCost = parseFloat(item.total_cost);
                        const totalSelling = parseFloat(item.total_selling);
                        const profit = parseFloat(item.total_profit);
                        tr.innerHTML = `
                            <td><strong>${item.new_part_name}</strong></td>
                            <td>${item.total_qty}</td>
                            <td>₹${totalCost.toLocaleString('en-IN')}</td>
                            <td>₹${totalSelling.toLocaleString('en-IN')}</td>
                            <td><strong style="color:${profit >= 0 ? '#10b981' : '#ef4444'};">₹${profit.toLocaleString('en-IN')}</strong></td>
                        `;
                        tbody.appendChild(tr);
                    });
                } else {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger" style="padding:20px;">Failed to load inventory.</td></tr>';
                }
            } catch (e) {
                console.error("Error loading inventory", e);
            }
        }

        function exportPerformanceCSV() {
            if (performanceData.length === 0) {
                alert('No data to export.');
                return;
            }
            
            let csv = 'Engineer Name,Jobs Assigned,Jobs Completed,Jobs Pending,Avg Resolution Time,Avg First Response,Revenue Generated\n';
            performanceData.forEach(row => {
                csv += `"${row.name}",${row.assigned},${row.completed},${row.pending},"${row.avg_resolution}","${row.avg_response}",${row.revenue}\n`;
            });

            const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
            const link = document.createElement("a");
            const url = URL.createObjectURL(blob);
            
            link.setAttribute("href", url);
            link.setAttribute("download", `engineer_performance_report_${new Date().toISOString().slice(0, 10)}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
    </script>
</body>

</html>
