/**
 * CRM Analytics Module
 * Fetches data from crm_analytics.php and renders charts + metrics.
 */
const CRM = (() => {
    let charts = {};
    let currentRange = 30;
    let refreshTimer = null;

    const COLORS = {
        primary: '#1f5fae',
        blue: '#3b82f6',
        green: '#10b981',
        yellow: '#f59e0b',
        purple: '#8b5cf6',
        cyan: '#06b6d4',
        red: '#ef4444',
        orange: '#f97316',
        pink: '#ec4899',
        indigo: '#6366f1'
    };
    const CHART_PALETTE = ['#3b82f6','#10b981','#f59e0b','#8b5cf6','#ef4444','#06b6d4','#f97316','#ec4899','#6366f1','#14b8a6'];

    function init() {
        const rangeSelect = document.getElementById('rangeSelect');
        if (rangeSelect) {
            rangeSelect.addEventListener('change', (e) => {
                currentRange = parseInt(e.target.value);
                fetchData();
            });
        }
        const exportBtn = document.getElementById('exportBtn');
        if (exportBtn) exportBtn.addEventListener('click', exportCSV);

        showSkeletons();
        fetchData();
        // Auto-refresh every 30 seconds
        refreshTimer = setInterval(fetchData, 30000);
    }

    function showSkeletons() {
        const og = document.getElementById('overviewGrid');
        if (og) og.innerHTML = Array(6).fill('<div class="overview-card skeleton skeleton-card"></div>').join('');
        const cg = document.getElementById('chartsGrid');
        if (cg) cg.innerHTML = Array(4).fill('<div class="chart-card skeleton skeleton-chart"></div>').join('');
        const ig = document.getElementById('insightsGrid');
        if (ig) ig.innerHTML = Array(6).fill('<div class="insight-card skeleton" style="height:100px"></div>').join('');
    }

    async function fetchData() {
        try {
            const res = await fetch(`api/crm_analytics.php?range=${currentRange}`);
            const json = await res.json();
            if (json.status === 'success') {
                render(json.data);
                updateRefreshTime();
            }
            // Fetch task analytics independently to prevent side effects
            fetchTaskCRMData();
        } catch (e) {
            console.error('CRM fetch error:', e);
        }
    }

    function updateRefreshTime() {
        const el = document.getElementById('lastRefresh');
        if (el) el.textContent = 'Last updated: ' + new Date().toLocaleTimeString();
    }

    function render(data) {
        renderOverview(data.overview);
        renderCharts(data);
        renderInsights(data.insights);
        renderRecentTable(data.recent_services);
        renderMonthlyComparison(data.monthly_comparison);
        renderRepeatCustomers(data.repeat_customers);
        renderSLAAlerts(data.overdue_services);
        renderEngineerStats(data.engineer_performance);
        renderWorkAssignments(data.work_assignments);
        renderUnassignedTasks(data.unassigned_requests);
    }

    // ============ MONTHLY COMPARISON ============
    function renderMonthlyComparison(m) {
        const container = document.getElementById('monthlyComparison');
        if (!container) return;
        
        const calcDiff = (now, prev) => {
            if (prev === 0) return '';
            const diff = now - prev;
            const cls = diff >= 0 ? 'diff-up' : 'diff-down';
            return `<span class="diff-tag ${cls}">${diff >= 0 ? '+' : ''}${diff}</span>`;
        };

        container.innerHTML = `
            <table class="comparison-table">
                <thead>
                    <tr><th>Metric</th><th>${m.last_month_name}</th><th>${m.this_month_name}</th></tr>
                </thead>
                <tbody>
                    <tr><td>Received</td><td>${m.last_month.received}</td><td>${m.this_month.received} ${calcDiff(m.this_month.received, m.last_month.received)}</td></tr>
                    <tr><td>Completed</td><td>${m.last_month.completed}</td><td>${m.this_month.completed} ${calcDiff(m.this_month.completed, m.last_month.completed)}</td></tr>
                    <tr><td>Cancelled</td><td>${m.last_month.cancelled}</td><td>${m.this_month.cancelled} ${calcDiff(m.last_month.cancelled, m.this_month.cancelled)}</td></tr>
                </tbody>
            </table>
        `;
    }

    // ============ REPEAT CUSTOMERS ============
    function renderRepeatCustomers(customers) {
        const container = document.getElementById('repeatCustomersList');
        if (!container) return;
        if (customers.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#64748b; padding:20px;">No repeat customers yet.</p>';
            return;
        }
        let html = '';
        customers.forEach(c => {
            html += `
                <div class="repeat-customer-item">
                    <div>
                        <div style="font-weight:600">${c.name}</div>
                        <div style="font-size:0.8rem; color:#64748b">${c.phone}</div>
                    </div>
                    <div class="visit-count">${c.visit_count}</div>
                </div>
            `;
        });
        container.innerHTML = html;
    }

    // ============ SLA ALERTS ============
    function renderSLAAlerts(overdue) {
        const section = document.getElementById('slaAlertsSection');
        const container = document.getElementById('slaTable');
        if (!section || !container) return;
        
        if (overdue.length === 0) {
            section.style.display = 'none';
            return;
        }
        
        section.style.display = 'block';
        let html = `<div class="table-responsive"><table class="comparison-table">
            <thead><tr><th>ID</th><th>Customer</th><th>Device</th><th>Status</th><th>Time Open</th></tr></thead><tbody>`;
        overdue.forEach(s => {
            html += `<tr>
                <td><strong>${s.service_id}</strong></td>
                <td>${s.name}</td>
                <td>${s.device_name}</td>
                <td><span class="${getStatusBadgeClass(s.status)}">${s.status}</span></td>
                <td><span class="days-tag">${s.days_open} Days</span></td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    // ============ ENGINEER STATS ============
    function renderEngineerStats(stats) {
        const container = document.getElementById('engineerStats');
        if (!container || !stats) return;

        let html = `<div class="table-responsive"><table class="comparison-table">
            <thead>
                <tr>
                    <th>Engineer</th>
                    <th>Jobs</th>
                    <th>Done</th>
                    <th>Active</th>
                    <th>Efficiency</th>
                </tr>
            </thead>
            <tbody>`;
        
        for (const [name, s] of Object.entries(stats)) {
            const efficiency = s.total > 0 ? Math.round((s.completed / s.total) * 100) : 0;
            const effClass = efficiency >= 70 ? 'growth-positive' : (efficiency >= 40 ? 'status-diagnosing' : 'growth-negative');
            
            html += `<tr>
                <td><div style="font-weight:700; color:var(--primary-dark);">🔧 ${name}</div></td>
                <td><span class="counter" data-target="${s.total}">${s.total}</span></td>
                <td><span style="color:var(--green); font-weight:700;">${s.completed}</span></td>
                <td><span style="color:var(--orange); font-weight:700;">${s.pending}</span></td>
                <td>
                    <div style="width:100px; height:8px; background:#e2e8f0; border-radius:4px; overflow:hidden; margin-bottom:4px;">
                        <div style="width:${efficiency}%; height:100%; background:var(--primary-color);"></div>
                    </div>
                    <div class="${effClass}" style="display:inline-block; font-size:0.75rem; font-weight:700;">${efficiency}% Rate</div>
                </td>
            </tr>`;
        }
        
        html += `</tbody></table></div>`;
        container.innerHTML = html;
        animateCounters();
    }

    // ============ WORK ASSIGNMENTS MATRIX ============
    function renderWorkAssignments(assignments) {
        const container = document.getElementById('workAssignments');
        if (!container || !assignments) return;

        if (Object.keys(assignments).length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#64748b; padding:20px;">No active work assigned to any engineer.</p>';
            return;
        }

        let html = '<div class="assignment-matrix">';
        for (const [engineer, tasks] of Object.entries(assignments)) {
            html += `
                <div class="engineer-track">
                    <div class="track-header">
                        <strong>👨‍🔧 ${engineer}</strong>
                        <span class="badge completed">${tasks.length} Active</span>
                    </div>
                    <div class="track-items">
                        ${tasks.map(t => `
                            <div class="track-item">
                                <div style="font-weight:600; font-size:0.85rem;">${t.service_id}</div>
                                <div style="font-size:0.75rem; color:#64748b;">${t.device_name}</div>
                                <span class="${getStatusBadgeClass(t.status)}" style="font-size:0.65rem; padding:1px 6px;">${t.status}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }
        html += '</div>';
        container.innerHTML = html;
    }

    // ============ UNASSIGNED TASKS ============
    function renderUnassignedTasks(requests) {
        const container = document.getElementById('unassignedTasks');
        if (!container || !requests) return;

        if (requests.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:var(--success); font-weight:600; padding:20px;">✅ All tasks are currently assigned!</p>';
            return;
        }

        let html = `<div class="table-responsive"><table class="comparison-table">
            <thead>
                <tr>
                    <th>Request ID</th>
                    <th>Customer</th>
                    <th>Device</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>`;

        requests.forEach(r => {
            html += `
                <tr>
                    <td><strong>${r.service_id}</strong></td>
                    <td>${r.name}</td>
                    <td>${r.brand} ${r.model}</td>
                    <td><span class="badge pending">${r.status}</span></td>
                    <td><a href="dashboard.php" class="btn-export" style="background:var(--primary-color); font-size:0.75rem; text-decoration:none; padding:4px 8px;">Assign Now</a></td>
                </tr>
            `;
        });

        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    // ============ OVERVIEW CARDS ============
    function renderOverview(ov) {
        const grid = document.getElementById('overviewGrid');
        if (!grid) return;
        grid.innerHTML = `
            ${overviewCard('total', '📊', ov.total, 'Total Services')}
            ${overviewCard('pending', '⏳', ov.pending, 'Pending')}
            ${overviewCard('progress', '🔧', ov.in_progress, 'In Progress')}
            ${overviewCard('completed', '✅', ov.completed, 'Completed')}
            ${overviewCard('delivered', '🎉', ov.delivered, 'Delivered')}
            ${overviewCard('cancelled', '❌', ov.cancelled, 'Cancelled')}
        `;
    }

    function overviewCard(cls, icon, value, label) {
        return `<div class="overview-card ${cls}">
            <div class="overview-icon">${icon}</div>
            <div class="overview-value">${animateNum(value)}</div>
            <div class="overview-label">${label}</div>
        </div>`;
    }

    function animateNum(val) {
        return `<span class="counter" data-target="${val}">0</span>`;
    }

    // ============ CHARTS ============
    function renderCharts(data) {
        const grid = document.getElementById('chartsGrid');
        if (!grid) return;
        grid.innerHTML = `
            <div class="chart-card"><h3>📈 Service Trend (${currentRange} Days)</h3><div class="chart-wrapper"><canvas id="trendChart"></canvas></div></div>
            <div class="chart-card"><h3>🥧 Status Distribution</h3><div class="chart-wrapper"><canvas id="statusChart"></canvas></div></div>
            <div class="chart-card"><h3>📊 Service Types</h3><div class="chart-wrapper"><canvas id="typeChart"></canvas></div></div>
            <div class="chart-card"><h3>🏠 Service Sources</h3><div class="chart-wrapper"><canvas id="sourceChart"></canvas></div></div>
        `;

        // Destroy old chart instances
        Object.values(charts).forEach(c => { if (c) c.destroy(); });
        charts = {};

        renderTrendChart(data.daily_performance);
        renderStatusPie(data.status_distribution);
        renderTypeChart(data.type_distribution);
        renderSourceChart(data.source_distribution);

        // Animate counters after DOM update
        animateCounters();
    }

    function renderTrendChart(daily) {
        const ctx = document.getElementById('trendChart');
        if (!ctx) return;
        const labels = daily.map(d => {
            const dt = new Date(d.day);
            return dt.toLocaleDateString(undefined, { month: 'short', day: 'numeric' });
        });
        charts.trend = new Chart(ctx, {
            type: 'line',
            data: {
                labels,
                datasets: [
                    {
                        label: 'Received',
                        data: daily.map(d => d.received),
                        borderColor: COLORS.blue,
                        backgroundColor: 'rgba(59,130,246,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        borderWidth: 2
                    },
                    {
                        label: 'Completed',
                        data: daily.map(d => d.completed),
                        borderColor: COLORS.green,
                        backgroundColor: 'rgba(16,185,129,0.1)',
                        fill: true,
                        tension: 0.4,
                        pointRadius: 3,
                        borderWidth: 2
                    }
                ]
            },
            options: chartOpts('Services')
        });
    }

    function renderStatusPie(dist) {
        const ctx = document.getElementById('statusChart');
        if (!ctx || dist.length === 0) return;
        charts.status = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: dist.map(d => d.status),
                datasets: [{
                    data: dist.map(d => d.count),
                    backgroundColor: CHART_PALETTE.slice(0, dist.length),
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12, family: 'Poppins' } } }
                },
                cutout: '55%'
            }
        });
    }

    function renderTypeChart(types) {
        const ctx = document.getElementById('typeChart');
        if (!ctx || types.length === 0) return;
        charts.type = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: types.map(t => t.type),
                datasets: [{
                    label: 'Count',
                    data: types.map(t => t.count),
                    backgroundColor: CHART_PALETTE.slice(0, types.length),
                    borderRadius: 6,
                    borderSkipped: false
                }]
            },
            options: chartOpts('Services')
        });
    }

    function renderSourceChart(sources) {
        const ctx = document.getElementById('sourceChart');
        if (!ctx || sources.length === 0) return;
        charts.source = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: sources.map(s => s.source),
                datasets: [{
                    data: sources.map(s => s.count),
                    backgroundColor: [COLORS.blue, COLORS.purple, COLORS.cyan],
                    borderWidth: 2,
                    borderColor: '#fff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'bottom', labels: { padding: 15, font: { size: 12, family: 'Poppins' } } }
                },
                cutout: '55%'
            }
        });
    }

    function chartOpts(yLabel) {
        return {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { labels: { font: { size: 12, family: 'Poppins' } } }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: { font: { family: 'Poppins' }, stepSize: 1 },
                    grid: { color: 'rgba(0,0,0,0.04)' },
                    title: { display: true, text: yLabel, font: { family: 'Poppins', weight: 600 } }
                },
                x: {
                    ticks: { font: { family: 'Poppins' }, maxRotation: 45 },
                    grid: { display: false }
                }
            }
        };
    }

    // ============ INSIGHTS ============
    function renderInsights(ins) {
        const grid = document.getElementById('insightsGrid');
        if (!grid) return;
        const growthClass = ins.growth_rate >= 0 ? 'growth-positive' : 'growth-negative';
        const growthIcon = ins.growth_rate >= 0 ? '↑' : '↓';
        grid.innerHTML = `
            <div class="insight-card">
                <div class="insight-value">${ins.completed_this_week}</div>
                <div class="insight-label">Completed This Week</div>
            </div>
            <div class="insight-card">
                <div class="insight-value">${ins.completed_this_month}</div>
                <div class="insight-label">Completed This Month</div>
            </div>
            <div class="insight-card">
                <div class="insight-value">${ins.avg_completion_days} days</div>
                <div class="insight-label">Avg Completion Time</div>
            </div>
            <div class="insight-card">
                <div class="insight-value">${ins.completion_rate}%</div>
                <div class="insight-label">Completion Rate</div>
            </div>
            <div class="insight-card">
                <div class="insight-value">${ins.peak_day}</div>
                <div class="insight-label">Peak Service Day</div>
            </div>
            <div class="insight-card">
                <div class="insight-value ${growthClass}">${growthIcon} ${Math.abs(ins.growth_rate)}%</div>
                <div class="insight-label">Week-over-Week Growth</div>
                <div class="insight-sub">${ins.this_week_count} this week vs ${ins.last_week_count} last week</div>
            </div>
            <div class="insight-card">
                <div class="insight-value">${ins.most_common_type}</div>
                <div class="insight-label">Most Common Service</div>
            </div>
            <div class="insight-card">
                <div class="insight-value" style="color: ${ins.pending_backlog > 5 ? '#ef4444' : '#f59e0b'}">${ins.pending_backlog}</div>
                <div class="insight-label">Pending Backlog</div>
            </div>
        `;
    }

    // ============ RECENT TABLE ============
    function renderRecentTable(services) {
        const container = document.getElementById('recentTable');
        if (!container) return;
        if (!services || services.length === 0) {
            container.innerHTML = '<p style="text-align:center; color:#64748b; padding:30px;">No recent services found.</p>';
            return;
        }
        let html = `<div class="table-responsive"><table>
            <thead><tr>
                <th>Service ID</th><th>Customer</th><th>Device</th><th>Type</th><th>Status</th><th>Date</th>
            </tr></thead><tbody>`;
        services.forEach(s => {
            html += `<tr>
                <td><strong style="color:var(--primary-dark)">${s.service_id}</strong></td>
                <td><div style="font-weight:600">${s.name}</div><div style="font-size:0.85rem;color:#64748b">${s.phone}</div></td>
                <td>${s.device_name}</td>
                <td>${s.service_type}</td>
                <td><span class="${getStatusBadgeClass(s.status)}">${s.status}</span></td>
                <td>${formatDate(s.date_received)}</td>
            </tr>`;
        });
        html += '</tbody></table></div>';
        container.innerHTML = html;
    }

    // ============ EXPORT CSV ============
    function exportCSV() {
        const table = document.querySelector('#recentTable table');
        if (!table) { alert('No data to export.'); return; }
        let csv = '';
        table.querySelectorAll('tr').forEach(row => {
            const cols = [];
            row.querySelectorAll('th, td').forEach(cell => {
                cols.push('"' + cell.textContent.replace(/"/g, '""').trim() + '"');
            });
            csv += cols.join(',') + '\n';
        });
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = `crm_report_${new Date().toISOString().slice(0,10)}.csv`;
        a.click();
        URL.revokeObjectURL(url);
    }

    // ============ COUNTER ANIMATION ============
    function animateCounters() {
        document.querySelectorAll('.counter').forEach(el => {
            const target = parseInt(el.dataset.target);
            if (isNaN(target)) { el.textContent = '0'; return; }
            const duration = 800;
            const step = Math.max(1, Math.ceil(target / (duration / 16)));
            let current = 0;
            const timer = setInterval(() => {
                current += step;
                if (current >= target) {
                    el.textContent = target.toLocaleString();
                    clearInterval(timer);
                } else {
                    el.textContent = current.toLocaleString();
                }
            }, 16);
        });
    }

    async function fetchTaskCRMData() {
        try {
            const res = await fetch('api/task_analytics.php');
            const json = await res.json();
            if (json.status === 'success') {
                renderTaskCRM(json.data);
            }
        } catch (e) {
            console.error('CRM task fetch error:', e);
        }
    }

    function renderTaskCRM(data) {
        const escapeHtml = (str) => str ? String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;') : '';
        
        // 1. Render numeric counters
        const c = data.counters;
        const totalPending = c.pending + c.accepted + c.in_progress + c.on_hold;
        
        document.getElementById('crmTaskTotal').innerText = c.total;
        document.getElementById('crmTaskPending').innerText = totalPending;
        document.getElementById('crmTaskCompleted').innerText = c.completed;
        document.getElementById('crmTaskOverdue').innerText = c.overdue;

        // 2. Render insights values
        document.getElementById('crmTaskAvgHours').innerText = data.avg_completion_hours + ' hrs';
        document.getElementById('crmTaskCompRate').innerText = data.completion_rate + '%';
        document.getElementById('crmTaskReassigned').innerText = c.reassigned;
        document.getElementById('crmTaskActiveEng').innerText = data.most_active_engineer;

        // 3. Render delayed tasks list
        const delayedTable = document.getElementById('crmDelayedTasksTable');
        if (delayedTable) {
            const delayed = data.delayed_tasks;
            if (delayed.length === 0) {
                delayedTable.innerHTML = '<p style="text-align:center; color:var(--green); font-weight:600; padding:15px;">✅ No overdue company tasks!</p>';
            } else {
                let html = `<div class="table-responsive"><table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Task ID</th>
                            <th>Title</th>
                            <th>Engineer</th>
                            <th>Due Date</th>
                            <th>Priority</th>
                        </tr>
                    </thead>
                    <tbody>`;
                delayed.forEach(t => {
                    html += `
                        <tr>
                            <td><strong>${t.task_id}</strong></td>
                            <td>${escapeHtml(t.title)}</td>
                            <td>👨‍🔧 ${escapeHtml(t.assigned_to)}</td>
                            <td><span class="days-tag" style="background:#fee2e2; color:#b91c1c;">${formatDate(t.due_date)}</span></td>
                            <td><span class="badge" style="background:#fee2e2; color:#b91c1c; font-weight:700;">${t.priority}</span></td>
                        </tr>
                    `;
                });
                html += '</tbody></table></div>';
                delayedTable.innerHTML = html;
            }
        }

        // 4. Render chart
        const ctx = document.getElementById('crmChartEngineerTasks');
        if (ctx && typeof Chart !== 'undefined') {
            if (charts.crmTasksChart) charts.crmTasksChart.destroy();
            
            const engStats = data.engineer_stats;
            const labels = engStats.map(e => e.engineer);
            const totals = engStats.map(e => e.total);
            const completed = engStats.map(e => e.completed);

            charts.crmTasksChart = new Chart(ctx, {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            label: 'Assigned',
                            data: totals,
                            backgroundColor: 'rgba(31, 95, 174, 0.75)',
                            borderColor: '#1f5fae',
                            borderWidth: 1.5,
                            borderRadius: 4
                        },
                        {
                            label: 'Completed',
                            data: completed,
                            backgroundColor: 'rgba(16, 185, 129, 0.75)',
                            borderColor: '#10b981',
                            borderWidth: 1.5,
                            borderRadius: 4
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f1f5f9' }, ticks: { stepSize: 1 } },
                        x: { grid: { display: false } }
                    },
                    plugins: {
                        legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Poppins' } } }
                    }
                }
            });
        }
    }

    return { init };
})();

document.addEventListener('DOMContentLoaded', CRM.init);
