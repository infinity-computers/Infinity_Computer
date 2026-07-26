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
    <title>Billing Register - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
            border-bottom: 2px solid #eee;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }

        .tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            font-size: 1.1rem;
            font-weight: 600;
            color: #6c757d;
            cursor: pointer;
            transition: all 0.3s;
            border-radius: 8px 8px 0 0;
        }

        .tab-btn.active {
            color: var(--primary-color);
            border-bottom: 3px solid var(--primary-color);
            background: rgba(31, 95, 174, 0.05);
        }

        .tab-btn:hover {
            background: #f8f9fa;
        }

        .tab-pane {
            display: none;
            animation: fadeIn 0.3s ease;
        }

        .tab-pane.active {
            display: block;
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(5px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .billing-summary-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .summary-card {
            background: #fff;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.02), var(--shadow);
            border-left: 5px solid var(--primary-color);
        }

        .summary-card .label {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }

        .summary-card .value {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }

        .summary-card .sub-value {
            font-size: 0.85rem;
            color: #475569;
            margin-top: 5px;
        }

        /* Modal styling */
        .modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.6);
            backdrop-filter: blur(4px);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            padding: 20px;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal {
            background: #fff;
            padding: 30px;
            border-radius: 16px;
            max-width: 500px;
            width: 100%;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid #e2e8f0;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .modal-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .modal-close {
            font-size: 1.5rem;
            color: #94a3b8;
            cursor: pointer;
            border: none;
            background: none;
        }
        .modal-footer {
            display: flex;
            justify-content: flex-end;
            gap: 10px;
            border-top: 1px solid #e2e8f0;
            padding-top: 15px;
            margin-top: 20px;
        }

        .filter-row {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            flex-wrap: wrap;
            align-items: flex-end;
        }

        .filter-row .form-group {
            flex: 1;
            min-width: 150px;
            margin: 0;
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
                <li><a href="billing.php" class="header-active">Billing</a></li>
                <li><a href="reports.php">Reports</a></li>
                <li><a href="task_management.php">Task Management</a></li>
                <li><a href="engineers_management.php">Manage Staff</a></li>
                <li><a href="logout.php">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <!-- Billing Summary stats -->
        <div class="billing-summary-grid">
            <div class="summary-card" style="border-left-color: #10b981;">
                <div class="label">Today's Collections</div>
                <div class="value" id="stat-daily-total">₹0</div>
                <div class="sub-value" id="stat-daily-count">0 Tickets</div>
            </div>
            <div class="summary-card" style="border-left-color: #3b82f6;">
                <div class="label">Weekly Collections</div>
                <div class="value" id="stat-weekly-total">₹0</div>
                <div class="sub-value">Current Week</div>
            </div>
            <div class="summary-card" style="border-left-color: #8b5cf6;">
                <div class="label">Monthly Collections</div>
                <div class="value" id="stat-monthly-total">₹0</div>
                <div class="sub-value">Current Month</div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab-btn active" onclick="switchBillingTab('pending')">Pending Billing</button>
            <button class="tab-btn" onclick="switchBillingTab('register')">Invoice Register</button>
            <button class="tab-btn" onclick="switchBillingTab('today')">Today's Collections</button>
        </div>

        <!-- 1. PENDING BILLING TAB -->
        <div id="pending" class="tab-pane active">
            <h2 style="font-size:1.4rem; margin-bottom:20px;">Tickets Awaiting Payment</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Service ID</th>
                            <th>Customer</th>
                            <th>Device</th>
                            <th>Estimated Value</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-pending">
                        <tr>
                            <td colspan="6" class="text-center" style="padding:40px; color:#6c757d;">Loading pending tickets...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. INVOICE REGISTER TAB -->
        <div id="register" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
                <h2 style="font-size:1.4rem; margin:0;">All Invoiced Tickets</h2>
            </div>
            <div class="filter-row">
                <div class="form-group">
                    <label>From Date</label>
                    <input type="date" id="register-start-date" class="form-control" onchange="loadRegister()">
                </div>
                <div class="form-group">
                    <label>To Date</label>
                    <input type="date" id="register-end-date" class="form-control" onchange="loadRegister()">
                </div>
                <div class="form-group" style="flex:2;">
                    <label>Search Invoice / ID / Customer</label>
                    <input type="text" id="register-search" class="form-control" placeholder="Search..." oninput="loadRegister()">
                </div>
                <button class="btn btn-secondary" onclick="clearRegisterFilters()" style="background:#6c757d; color:#fff; padding:12px 20px;">Clear</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Service ID</th>
                            <th>Customer</th>
                            <th>Billing Date</th>
                            <th>Amount Paid</th>
                            <th>Payment Mode</th>
                            <th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-register">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading invoice register...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. TODAY'S COLLECTIONS TAB -->
        <div id="today" class="tab-pane">
            <h2 style="font-size:1.4rem; margin-bottom:20px;">Collections Recorded Today</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Invoice No.</th>
                            <th>Service ID</th>
                            <th>Customer</th>
                            <th>Payment Received At</th>
                            <th>Amount</th>
                            <th>Payment Mode</th>
                            <th>Collected By</th>
                        </tr>
                    </thead>
                    <tbody id="table-body-today">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading today's collections...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Mark Paid Modal -->
    <div id="modal-mark-paid" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Confirm Payment</h3>
                <button class="modal-close" onclick="closeModal()">&times;</button>
            </div>
            <form id="form-mark-paid" onsubmit="submitMarkPaid(event)">
                <input type="hidden" name="service_id" id="paid-svc-id">
                <p>Are you sure you want to mark service <strong id="paid-svc-id-display"></strong> as Paid?</p>
                <div class="form-group mt-3">
                    <label>Select Payment Mode <span style="color:var(--danger)">*</span></label>
                    <select name="payment_mode" class="form-control" required>
                        <option value="UPI">UPI</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Credit">Credit</option>
                        <option value="Cheque">Cheque</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal()" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary" style="background:#10b981; border-color:#10b981;">Mark Paid</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadSummary();
            loadPending();
        });

        async function loadSummary() {
            try {
                const res = await fetch('api/billing_summary.php');
                const json = await res.json();
                if (json.status === 'success') {
                    const stats = json.data;
                    document.getElementById('stat-daily-total').innerText = '₹' + stats.daily_total.toLocaleString('en-IN');
                    document.getElementById('stat-daily-count').innerText = stats.daily_count + ' Ticket' + (stats.daily_count !== 1 ? 's' : '');
                    document.getElementById('stat-weekly-total').innerText = '₹' + stats.weekly_total.toLocaleString('en-IN');
                    document.getElementById('stat-monthly-total').innerText = '₹' + stats.monthly_total.toLocaleString('en-IN');
                }
            } catch (e) {
                console.error("Error loading summary stats", e);
            }
        }

        async function loadPending() {
            try {
                const res = await fetch('api/get_billing_tickets.php?tab=pending');
                const json = await res.json();
                const tbody = document.getElementById('table-body-pending');
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:20px;">No pending billing tickets.</td></tr>';
                    return;
                }
                json.data.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong style="color:var(--primary-dark);">${item.service_id}</strong></td>
                        <td><div style="font-weight:600;">${item.customer_name}</div></td>
                        <td><div>${item.device_name}</div><small style="color:#666;">${item.problem.substring(0, 50)}...</small></td>
                        <td><strong>₹${item.service_value_rupees.toLocaleString('en-IN')}</strong></td>
                        <td><span style="font-weight:600; text-transform:uppercase; font-size:0.75rem; background:#fee2e2; color:#b91c1c; padding:2px 8px; border-radius:4px;">${item.billing_status}</span></td>
                        <td><button class="btn btn-sm" style="background:#10b981; color:#fff; border:0;" onclick="openMarkPaidModal('${item.service_id}')">Mark Paid</button></td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error("Error loading pending tickets", e);
            }
        }

        async function loadRegister() {
            const start = document.getElementById('register-start-date').value;
            const end = document.getElementById('register-end-date').value;
            const search = document.getElementById('register-search').value.trim();

            const url = `api/get_billing_tickets.php?tab=register&start_date=${encodeURIComponent(start)}&end_date=${encodeURIComponent(end)}&q=${encodeURIComponent(search)}`;
            
            try {
                const res = await fetch(url);
                const json = await res.json();
                const tbody = document.getElementById('table-body-register');
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:20px;">No invoice records found matching filters.</td></tr>';
                    return;
                }
                json.data.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.invoice_number}</strong></td>
                        <td><strong style="color:var(--primary-dark);">${item.service_id}</strong></td>
                        <td><div style="font-weight:600;">${item.customer_name}</div></td>
                        <td>${formatDate(item.billing_completed_at)}</td>
                        <td><strong>₹${item.service_value_rupees.toLocaleString('en-IN')}</strong></td>
                        <td><span style="font-weight:600; background:#f1f5f9; padding:2px 8px; border-radius:4px;">${item.payment_mode || 'N/A'}</span></td>
                        <td>${item.billing_verified_by || 'Admin'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error("Error loading register", e);
            }
        }

        async function loadTodayCollections() {
            try {
                const res = await fetch('api/get_billing_tickets.php?tab=today');
                const json = await res.json();
                const tbody = document.getElementById('table-body-today');
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:20px;">No collections recorded today.</td></tr>';
                    return;
                }
                json.data.forEach(item => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong>${item.invoice_number || 'N/A'}</strong></td>
                        <td><strong style="color:var(--primary-dark);">${item.service_id}</strong></td>
                        <td><div style="font-weight:600;">${item.customer_name}</div></td>
                        <td>${formatDate(item.billing_completed_at)}</td>
                        <td><strong>₹${item.service_value_rupees.toLocaleString('en-IN')}</strong></td>
                        <td><span style="font-weight:600; background:#f1f5f9; padding:2px 8px; border-radius:4px;">${item.payment_mode || 'N/A'}</span></td>
                        <td>${item.billing_verified_by || 'Admin'}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error("Error loading today collections", e);
            }
        }

        function switchBillingTab(tabId) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            const btn = document.querySelector(`.tab-btn[onclick="switchBillingTab('${tabId}')"]`);
            if (btn) btn.classList.add('active');
            document.getElementById(tabId).classList.add('active');

            if (tabId === 'pending') loadPending();
            if (tabId === 'register') loadRegister();
            if (tabId === 'today') loadTodayCollections();
        }

        function clearRegisterFilters() {
            document.getElementById('register-start-date').value = '';
            document.getElementById('register-end-date').value = '';
            document.getElementById('register-search').value = '';
            loadRegister();
        }

        function openMarkPaidModal(serviceId) {
            document.getElementById('paid-svc-id').value = serviceId;
            document.getElementById('paid-svc-id-display').innerText = serviceId;
            document.getElementById('modal-mark-paid').classList.add('active');
        }

        function closeModal() {
            document.getElementById('modal-mark-paid').classList.remove('active');
        }

        async function submitMarkPaid(e) {
            e.preventDefault();
            const form = e.target;
            const data = {
                service_id: form.elements['service_id'].value,
                payment_mode: form.elements['payment_mode'].value
            };
            try {
                const res = await fetch('api/mark_payment.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal();
                    form.reset();
                    loadSummary();
                    loadPending();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function formatDate(dtStr) {
            if (!dtStr) return '-';
            const date = new Date(dtStr);
            if (isNaN(date.getTime())) return dtStr;
            return date.toLocaleString('en-IN', {
                day: '2-digit',
                month: 'short',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                hour12: true
            });
        }
    </script>
</body>

</html>
