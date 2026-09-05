<?php
/**
 * AMC Management Control Center
 * Infinity Computer Admin Panel Feature
 */

include __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/api/amc_helper.php';

$activeNav = 'amc';
$staffName = getStaffName();
$staffRole = getStaffRole();
$isAdmin = isAdmin();

if (!$isAdmin) {
    header('Location: dashboard.php');
    exit;
}

// Run automatic 48h check
checkAndApply48HourReassignment($conn);

// Fetch Engineers List for assignment dropdowns
$engRes = $conn->query("SELECT name FROM engineers WHERE is_active = 1 ORDER BY name ASC");
$engineersList = [];
if ($engRes) {
    while ($eRow = $engRes->fetch_assoc()) {
        if ($eRow['name'] !== 'icc') $engineersList[] = $eRow['name'];
    }
}
if (empty($engineersList)) $engineersList = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];

// Fetch Dynamic AMC Products
$prodRes = $conn->query("SELECT * FROM amc_products WHERE is_active = 1 ORDER BY name ASC");
$activeProducts = [];
if ($prodRes) {
    while ($pRow = $prodRes->fetch_assoc()) $activeProducts[] = $pRow;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AMC Management - Infinity Computer Service Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <link rel="stylesheet" href="assets/css/amc.css?v=1.0">
    <style>
        .amc-header-banner {
            background: linear-gradient(135deg, #1f5fae 0%, #0f172a 100%);
            color: #fff;
            padding: 24px 30px;
            border-radius: 16px;
            margin-bottom: 25px;
            box-shadow: 0 10px 25px -5px rgba(31, 95, 174, 0.25);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .amc-subtabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            border-bottom: 2px solid #e2e8f0;
            padding-bottom: 10px;
            flex-wrap: wrap;
        }

        .amc-tab-btn {
            padding: 10px 20px;
            border: none;
            background: none;
            font-size: 1rem;
            font-weight: 600;
            color: #64748b;
            cursor: pointer;
            border-radius: 8px;
            transition: all 0.2s ease;
        }

        .amc-tab-btn.active {
            background: #1f5fae;
            color: #fff;
            box-shadow: 0 4px 10px rgba(31, 95, 174, 0.2);
        }

        .amc-pane {
            display: none;
        }

        .amc-pane.active {
            display: block;
        }

        .modal-overlay.active {
            display: flex !important;
        }
    </style>
</head>

<body>
    <?php include __DIR__ . '/partials/nav.php'; ?>

    <div class="container" style="margin-top:30px; margin-bottom:50px;">
        
        <!-- Header Banner -->
        <div class="amc-header-banner">
            <div>
                <h1 style="margin:0; font-size:1.8rem; font-weight:700;">🛡️ AMC Management System</h1>
                <p style="margin:4px 0 0 0; opacity:0.85; font-size:0.95rem;">Annual Maintenance Contracts, Automated Visit Schedules &amp; Engineer Workload Tracking</p>
            </div>
            <div>
                <button class="btn btn-light" onclick="openCreateContractModal()" style="background:#fff; color:#1f5fae; font-weight:700; padding:12px 20px; border-radius:8px; border:none; cursor:pointer;">
                    ➕ Create New AMC Contract
                </button>
            </div>
        </div>

        <!-- Live Dashboard Counters Grid -->
        <div class="amc-stats-grid">
            <div class="amc-stat-card" style="border-left-color: #3b82f6;" onclick="switchAmcTab('contracts-pane')">
                <div class="label">Total Contracts</div>
                <div class="val" id="stat-total-contracts">0</div>
            </div>
            <div class="amc-stat-card" style="border-left-color: #10b981;" onclick="switchAmcTab('contracts-pane')">
                <div class="label">Active Contracts</div>
                <div class="val" id="stat-active-contracts">0</div>
            </div>
            <div class="amc-stat-card" style="border-left-color: #06b6d4;" onclick="switchAmcTab('visits-pane')">
                <div class="label">Today's Visits</div>
                <div class="val" id="stat-todays-visits">0</div>
            </div>
            <div class="amc-stat-card" style="border-left-color: #8b5cf6;" onclick="switchAmcTab('visits-pane')">
                <div class="label">Pending Visits</div>
                <div class="val" id="stat-pending-visits">0</div>
            </div>
            <div class="amc-stat-card" style="border-left-color: #ef4444;" onclick="switchAmcTab('visits-pane')">
                <div class="label">Overdue / Escalated</div>
                <div class="val" id="stat-overdue-visits">0</div>
            </div>
            <div class="amc-stat-card" style="border-left-color: #f59e0b;" onclick="switchAmcTab('visits-pane')">
                <div class="label">Follow-Up Required</div>
                <div class="val" id="stat-followup-visits">0</div>
            </div>
        </div>

        <!-- Sub Tabs Navigation -->
        <div class="amc-subtabs">
            <button class="amc-tab-btn active" onclick="switchAmcTab('overview-pane')">📊 AMC Overview</button>
            <button class="amc-tab-btn" onclick="switchAmcTab('contracts-pane')">📄 AMC Contracts</button>
            <button class="amc-tab-btn" onclick="switchAmcTab('visits-pane')">📅 Visit Schedules</button>
            <button class="amc-tab-btn" onclick="switchAmcTab('products-pane')">📦 Dynamic Product Types</button>
            <button class="amc-tab-btn" onclick="switchAmcTab('audit-pane')">📜 System Audit Log</button>
        </div>

        <!-- 1. OVERVIEW PANE -->
        <div id="overview-pane" class="amc-pane active">
            <div style="display:grid; grid-template-columns: 2fr 1fr; gap:25px; align-items:start;">
                <!-- Urgent Attention Board -->
                <div class="card" style="padding:25px;">
                    <h3 style="margin-top:0; color:#b91c1c;">⚠️ Action Required: Overdue &amp; Escalated AMC Visits</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>AMC #</th>
                                    <th>Visit #</th>
                                    <th>Customer</th>
                                    <th>Assigned Engineer</th>
                                    <th>Scheduled Date</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="urgentVisitsTableBody">
                                <tr><td colspan="7" class="text-center" style="padding:30px; color:#64748b;">Loading urgent visits...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Engineer Workload Summary -->
                <div class="card" style="padding:25px;">
                    <h3 style="margin-top:0; color:var(--amc-primary-dark);">👨‍🔧 Engineer AMC Performance</h3>
                    <div class="table-responsive">
                        <table>
                            <thead>
                                <tr>
                                    <th>Engineer</th>
                                    <th>Assigned</th>
                                    <th>Completed</th>
                                    <th>Reassigned</th>
                                </tr>
                            </thead>
                            <tbody id="engPerformanceTableBody">
                                <tr><td colspan="4" class="text-center" style="padding:20px; color:#64748b;">Loading performance...</td></tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- 2. CONTRACTS PANE -->
        <div id="contracts-pane" class="amc-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
                <input type="text" id="contractSearchInput" placeholder="🔍 Search by AMC #, Customer Name, Phone, Company..." onkeyup="loadAmcContracts()" class="form-control" style="max-width:400px;">
                <select id="contractStatusFilter" onchange="loadAmcContracts()" class="form-control" style="max-width:180px;">
                    <option value="">All Statuses</option>
                    <option value="Active">Active</option>
                    <option value="Completed">Completed</option>
                    <option value="Expired">Expired</option>
                    <option value="Suspended">Suspended</option>
                    <option value="Cancelled">Cancelled</option>
                </select>
            </div>
            <div class="table-responsive card" style="padding:10px;">
                <table>
                    <thead>
                        <tr>
                            <th>AMC Number</th>
                            <th>Customer &amp; Phone</th>
                            <th>Products Covered</th>
                            <th>Contract Period</th>
                            <th>Visits Progress</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="contractsTableBody">
                        <tr><td colspan="7" class="text-center" style="padding:40px; color:#64748b;">Loading contracts...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. VISITS PANE -->
        <div id="visits-pane" class="amc-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:15px;">
                <div style="display:flex; gap:10px; flex-wrap:wrap;">
                    <select id="visitFilterEngineer" onchange="loadAmcVisitsList()" class="form-control" style="max-width:180px;">
                        <option value="">All Engineers</option>
                        <?php foreach ($engineersList as $eng): ?>
                        <option value="<?= htmlspecialchars($eng) ?>"><?= htmlspecialchars($eng) ?></option>
                        <?php endforeach; ?>
                    </select>

                    <select id="visitFilterStatus" onchange="loadAmcVisitsList()" class="form-control" style="max-width:180px;">
                        <option value="">All Statuses</option>
                        <option value="ASSIGNED">ASSIGNED</option>
                        <option value="ACCEPTED">ACCEPTED</option>
                        <option value="REACHED">REACHED</option>
                        <option value="INSPECTION">INSPECTION</option>
                        <option value="FOLLOW-UP REQUIRED">FOLLOW-UP REQUIRED</option>
                        <option value="OVERDUE">OVERDUE</option>
                        <option value="COMPLETED">COMPLETED</option>
                    </select>
                </div>

                <button class="btn btn-secondary" onclick="loadAmcVisitsList()">🔄 Refresh Schedule</button>
            </div>

            <div class="table-responsive card" style="padding:10px;">
                <table>
                    <thead>
                        <tr>
                            <th>AMC #</th>
                            <th>Visit #</th>
                            <th>Customer Name</th>
                            <th>Assigned Engineer</th>
                            <th>Scheduled Date</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody id="allVisitsTableBody">
                        <tr><td colspan="7" class="text-center" style="padding:40px; color:#64748b;">Loading visit schedules...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. PRODUCTS PANE -->
        <div id="products-pane" class="amc-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h3 style="margin:0;">Dynamic AMC Product Types</h3>
                <button class="btn btn-primary" onclick="openAddProductModal()">➕ Add New Product Type</button>
            </div>
            <div class="table-responsive card" style="padding:10px;">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product Name</th>
                            <th>Description</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="amcProductsTableBody">
                        <tr><td colspan="5" class="text-center" style="padding:30px; color:#64748b;">Loading products...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 5. AUDIT PANE -->
        <div id="audit-pane" class="amc-pane">
            <h3 style="margin-bottom:20px;">AMC System Audit Log</h3>
            <div class="table-responsive card" style="padding:10px;">
                <table>
                    <thead>
                        <tr>
                            <th>Timestamp</th>
                            <th>AMC / Visit</th>
                            <th>Action</th>
                            <th>Performed By</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="amcAuditLogsTableBody">
                        <tr><td colspan="5" class="text-center" style="padding:30px; color:#64748b;">Loading audit logs...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>

    <!-- MODAL 1: CREATE NEW AMC CONTRACT -->
    <div id="create-contract-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
        <div style="background:#fff; width:100%; max-width:850px; max-height:90vh; border-radius:16px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow-y:auto; padding:30px; position:relative; margin:auto;">
            <button onclick="closeCreateContractModal()" style="position:absolute; top:15px; right:20px; background:none; border:none; font-size:2rem; font-weight:700; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
            <h2 style="margin-top:0; color:#1f5fae;">➕ Create New AMC Contract</h2>
            
            <form id="createContractForm" onsubmit="submitCreateContract(event)">
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                    <h4 style="margin-top:0; color:#334155;">Customer Information</h4>
                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label style="font-weight:600;">Customer Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="customer_name" class="form-control" required placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Company / Business Name</label>
                            <input type="text" name="company_name" class="form-control" placeholder="Company Name (Optional)">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:15px; margin-top:15px;">
                        <div class="form-group">
                            <label style="font-weight:600;">Phone Number <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="customer_phone" class="form-control" required placeholder="10-digit phone number">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Email Address</label>
                            <input type="email" name="customer_email" class="form-control" placeholder="Email (Optional)">
                        </div>
                    </div>

                    <div class="form-group mt-3">
                        <label style="font-weight:600;">Service Installation Address <span style="color:var(--danger)">*</span></label>
                        <textarea name="customer_address" class="form-control" rows="2" required placeholder="Full site address where AMC maintenance will take place..."></textarea>
                    </div>
                </div>

                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                    <h4 style="margin-top:0; color:#334155;">Contract Duration &amp; Visit Schedule</h4>
                    <div style="display:grid; grid-template-columns:1fr 1fr 1fr; gap:15px;">
                        <div class="form-group">
                            <label style="font-weight:600;">Start Date <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="start_date" id="contractStartDate" class="form-control" value="<?= date('Y-m-d') ?>" required onchange="calcContractEndDate()">
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">Number of Maintenance Visits <span style="color:var(--danger)">*</span></label>
                            <select name="visit_count" class="form-control" required>
                                <option value="4" selected>4 Visits (Quarterly)</option>
                                <option value="6">6 Visits (Bi-Monthly)</option>
                                <option value="12">12 Visits (Monthly)</option>
                                <option value="24">24 Visits (Semi-Monthly)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label style="font-weight:600;">End Date (Auto 12-Mon)</label>
                            <input type="date" name="end_date" id="contractEndDate" class="form-control" required readonly>
                        </div>
                    </div>
                </div>

                <!-- Products Covered Section -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                    <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:15px;">
                        <h4 style="margin:0; color:#334155;">Products Covered under Contract</h4>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="addContractProductRow()">➕ Add Product</button>
                    </div>

                    <div id="contractProductsContainer">
                        <!-- Dynamic Product Rows inserted here -->
                    </div>
                </div>

                <!-- Preferred Engineers Assignment -->
                <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:20px; margin-bottom:20px;">
                    <h4 style="margin-top:0; color:#334155;">Preferred Engineer Assignment (Fair Distribution)</h4>
                    <div style="display:flex; gap:15px; flex-wrap:wrap;">
                        <?php foreach ($engineersList as $eng): ?>
                        <label style="display:flex; align-items:center; gap:6px; cursor:pointer; background:#fff; padding:6px 12px; border:1px solid #cbd5e1; border-radius:6px;">
                            <input type="checkbox" name="assigned_engineers[]" value="<?= htmlspecialchars($eng) ?>" checked>
                            <span><?= htmlspecialchars($eng) ?></span>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary" style="width:100%; padding:15px; font-size:1.1rem; font-weight:700;">CREATE AMC CONTRACT &amp; GENERATE SCHEDULES</button>
            </form>
        </div>
    </div>

    <!-- MODAL 2: ADD PRODUCT TYPE -->
    <div id="add-product-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); z-index:9999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
        <div style="background:#fff; width:100%; max-width:500px; border-radius:16px; padding:30px; position:relative; margin:auto;">
            <button onclick="closeAddProductModal()" style="position:absolute; top:15px; right:20px; background:none; border:none; font-size:2rem; font-weight:700; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
            <h3 style="margin-top:0; color:#1f5fae;">➕ Add Dynamic Product Type</h3>
            <form onsubmit="submitAddProduct(event)">
                <div class="form-group mb-3">
                    <label style="font-weight:600;">Product Type Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="name" class="form-control" required placeholder="e.g. Server, Router, Scanner, Biometric...">
                </div>
                <div class="form-group mb-3">
                    <label style="font-weight:600;">Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="Brief description of product type..."></textarea>
                </div>
                <button type="submit" class="btn btn-primary" style="width:100%; padding:12px;">Add Product Type</button>
            </form>
        </div>
    </div>

    <!-- MODAL 3: AMC VISIT WORKFLOW MODAL -->
    <div id="amc-visit-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); z-index:99999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
        <div style="background:#fff; width:100%; max-width:900px; max-height:90vh; border-radius:16px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow-y:auto; padding:30px; position:relative; margin:auto;">
            <button onclick="window.AMC.closeVisitModal()" style="position:absolute; top:15px; right:20px; background:none; border:none; font-size:2rem; font-weight:700; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
            <div id="amc-visit-modal-content"></div>
        </div>
    </div>

    <!-- JS Scripts -->
    <script src="assets/js/amc-workflow.js?v=1.0"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            calcContractEndDate();
            addContractProductRow();
            loadDashboardStats();
            loadAmcContracts();
        });

        function switchAmcTab(paneId) {
            document.querySelectorAll('.amc-tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.amc-pane').forEach(pane => pane.classList.remove('active'));

            const targetPane = document.getElementById(paneId);
            if (targetPane) targetPane.classList.add('active');

            // Find matching tab button
            const btns = document.querySelectorAll('.amc-tab-btn');
            btns.forEach(btn => {
                if (btn.getAttribute('onclick').includes(paneId)) btn.classList.add('active');
            });

            if (paneId === 'overview-pane') loadDashboardStats();
            if (paneId === 'contracts-pane') loadAmcContracts();
            if (paneId === 'visits-pane') loadAmcVisitsList();
            if (paneId === 'products-pane') loadAmcProducts();
            if (paneId === 'audit-pane') loadAmcAuditLogs();
        }

        function calcContractEndDate() {
            const startInput = document.getElementById('contractStartDate');
            const endInput = document.getElementById('contractEndDate');
            if (startInput && endInput && startInput.value) {
                const sDate = new Date(startInput.value);
                sDate.setFullYear(sDate.getFullYear() + 1);
                endInput.value = sDate.toISOString().split('T')[0];
            }
        }

        function addContractProductRow() {
            const container = document.getElementById('contractProductsContainer');
            if (!container) return;
            const idx = container.children.length;

            const div = document.createElement('div');
            div.className = 'form-grid';
            div.style.cssText = 'display:grid; grid-template-columns: 2fr 1fr 2fr auto; gap:10px; margin-bottom:10px; align-items:center;';
            div.innerHTML = `
                <select name="products[${idx}][product_name]" class="form-control" required>
                    <?php foreach ($activeProducts as $ap): ?>
                    <option value="<?= htmlspecialchars($ap['name']) ?>"><?= htmlspecialchars($ap['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <input type="number" name="products[${idx}][quantity]" class="form-control" value="1" min="1" placeholder="Qty">
                <input type="text" name="products[${idx}][serial_number]" class="form-control" placeholder="Serial # / Model">
                <button type="button" class="btn btn-sm btn-danger" onclick="this.parentElement.remove()" style="padding:6px 12px; background:#ef4444; color:#fff; border:none; border-radius:6px;">✕</button>
            `;
            container.appendChild(div);
        }

        function openCreateContractModal() {
            document.getElementById('create-contract-modal').classList.add('active');
        }
        function closeCreateContractModal() {
            document.getElementById('create-contract-modal').classList.remove('active');
        }

        function openAddProductModal() {
            document.getElementById('add-product-modal').classList.add('active');
        }
        function closeAddProductModal() {
            document.getElementById('add-product-modal').classList.remove('active');
        }

        async function loadDashboardStats() {
            try {
                const res = await fetch('api/amc_reports_api.php?action=dashboard_stats');
                const json = await res.json();
                if (json.status === 'success') {
                    const d = json.data;
                    document.getElementById('stat-total-contracts').innerText = d.total_contracts;
                    document.getElementById('stat-active-contracts').innerText = d.active_contracts;
                    document.getElementById('stat-todays-visits').innerText = d.todays_visits;
                    document.getElementById('stat-pending-visits').innerText = d.pending_visits;
                    document.getElementById('stat-overdue-visits').innerText = d.overdue_visits + d.escalated_visits;
                    document.getElementById('stat-followup-visits').innerText = d.followup_required;
                }

                // Load Urgent Visits table
                const uRes = await fetch('api/amc_visits_api.php?action=list&status=OVERDUE');
                const uJson = await uRes.json();
                const uTbody = document.getElementById('urgentVisitsTableBody');
                uTbody.innerHTML = '';
                if (!uJson.data || uJson.data.length === 0) {
                    uTbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:20px; color:#10b981;">✅ No urgent or overdue visits. All schedules on time.</td></tr>';
                } else {
                    uJson.data.forEach(v => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong style="color:var(--primary-dark);">${v.amc_number}</strong></td>
                            <td>Visit #${v.visit_number}</td>
                            <td><strong>${v.customer_name}</strong><br><small style="color:#64748b;">${v.customer_phone}</small></td>
                            <td><span style="color:#b91c1c; font-weight:700;">${v.assigned_engineer}</span></td>
                            <td>${v.scheduled_date}</td>
                            <td><span class="${window.AMC.getStatusBadgeClass(v.status)}">${v.status}</span></td>
                            <td>
                                <button class="btn btn-sm btn-primary" onclick="window.AMC.openVisitModal(${v.id})">Open Workflow</button>
                            </td>
                        `;
                        uTbody.appendChild(tr);
                    });
                }

                // Load Engineer Performance
                const pRes = await fetch('api/amc_reports_api.php?action=engineer_performance');
                const pJson = await pRes.json();
                const pTbody = document.getElementById('engPerformanceTableBody');
                pTbody.innerHTML = '';
                if (pJson.data && pJson.data.length > 0) {
                    pJson.data.forEach(p => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `
                            <td><strong>${p.assigned_engineer}</strong></td>
                            <td>${p.total_assigned}</td>
                            <td><span style="color:#10b981; font-weight:700;">${p.completed_count}</span></td>
                            <td><span style="color:#f59e0b; font-weight:700;">${p.reassigned_count}</span></td>
                        `;
                        pTbody.appendChild(tr);
                    });
                }
            } catch (e) {
                console.error("Failed to load dashboard stats", e);
            }
        }

        async function loadAmcContracts() {
            const search = document.getElementById('contractSearchInput').value;
            const status = document.getElementById('contractStatusFilter').value;
            const tbody = document.getElementById('contractsTableBody');

            try {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:30px; color:#64748b;">Loading contracts...</td></tr>';
                const res = await fetch(`api/amc_contracts_api.php?action=list&search=${encodeURIComponent(search)}&status=${encodeURIComponent(status)}`);
                const json = await res.json();
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:40px; color:#64748b;">No AMC contracts found matching filter.</td></tr>';
                    return;
                }

                json.data.forEach(c => {
                    const tr = document.createElement('tr');
                    const prodList = (c.products || []).map(p => p.product_name + (p.quantity > 1 ? ` (x${p.quantity})` : '')).join(', ') || 'General';
                    const compVisits = parseInt(c.completed_visits || 0);
                    const totVisits = parseInt(c.total_visits || c.visit_count || 4);
                    const pct = Math.round((compVisits / maxVal(1, totVisits)) * 100);

                    tr.innerHTML = `
                        <td><strong style="color:#1f5fae; font-size:1.05rem;">${c.amc_number}</strong></td>
                        <td>
                            <div style="font-weight:700;">${c.customer_name} ${c.company_name ? '(' + c.company_name + ')' : ''}</div>
                            <div style="font-size:0.85rem; color:#64748b;">📞 ${c.customer_phone}</div>
                        </td>
                        <td style="font-size:0.9rem;">${prodList}</td>
                        <td style="font-size:0.85rem;">
                            <div>Start: ${c.start_date}</div>
                            <div>End: ${c.end_date}</div>
                        </td>
                        <td>
                            <div style="font-size:0.85rem; font-weight:700; margin-bottom:3px;">${compVisits} / ${totVisits} Completed (${pct}%)</div>
                            <div style="background:#e2e8f0; border-radius:10px; height:8px; width:100%; overflow:hidden;">
                                <div style="background:#10b981; height:100%; width:${pct}%;"></div>
                            </div>
                        </td>
                        <td><span class="badge-amc ${c.status === 'Active' ? 'badge-completed' : 'badge-overdue'}">${c.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="viewContractDetails(${c.id})">Details &amp; Visits</button>
                            <button class="btn btn-sm btn-secondary" onclick="renewContract(${c.id})">Renew</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load contracts.</td></tr>';
            }
        }

        function maxVal(a, b) { return a > b ? a : b; }

        async function loadAmcVisitsList() {
            const eng = document.getElementById('visitFilterEngineer').value;
            const st = document.getElementById('visitFilterStatus').value;
            const tbody = document.getElementById('allVisitsTableBody');

            try {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:30px; color:#64748b;">Loading visit schedules...</td></tr>';
                const res = await fetch(`api/amc_visits_api.php?action=list&engineer=${encodeURIComponent(eng)}&status=${encodeURIComponent(st)}`);
                const json = await res.json();
                tbody.innerHTML = '';

                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:40px; color:#64748b;">No visit schedules match the criteria.</td></tr>';
                    return;
                }

                json.data.forEach(v => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td><strong style="color:var(--primary-dark);">${v.amc_number}</strong></td>
                        <td><strong>Visit #${v.visit_number}</strong></td>
                        <td>
                            <div style="font-weight:700;">${v.customer_name}</div>
                            <div style="font-size:0.8rem; color:#64748b;">📍 ${v.customer_address}</div>
                        </td>
                        <td><strong>${v.assigned_engineer}</strong></td>
                        <td>${v.scheduled_date}</td>
                        <td><span class="${window.AMC.getStatusBadgeClass(v.status)}">${v.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="window.AMC.openVisitModal(${v.id})">Process Visit</button>
                            <button class="btn btn-sm btn-secondary" onclick="promptReassign(${v.id}, '${v.assigned_engineer}')">Reassign</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load visits.</td></tr>';
            }
        }

        async function promptReassign(visitId, currentEng) {
            const newEng = prompt(`Reassign Visit #${visitId} from ${currentEng} to another engineer:`, currentEng);
            if (newEng && newEng.trim() !== '' && newEng.trim() !== currentEng) {
                const formData = new FormData();
                formData.append('action', 'reassign');
                formData.append('visit_id', visitId);
                formData.append('new_engineer', newEng.trim());

                const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    loadAmcVisitsList();
                } else {
                    alert('Error: ' + json.message);
                }
            }
        }

        async function submitCreateContract(e) {
            e.preventDefault();
            const form = e.target;
            const btn = form.querySelector('button[type="submit"]');
            btn.disabled = true;
            btn.innerText = 'Creating Contract & Scheduling Visits...';

            const formData = new FormData(form);
            formData.append('action', 'create');

            try {
                const res = await fetch('api/amc_contracts_api.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.status === 'success') {
                    alert('🎉 ' + json.message);
                    closeCreateContractModal();
                    form.reset();
                    switchAmcTab('contracts-pane');
                } else {
                    alert('Error: ' + json.message);
                    btn.disabled = false;
                    btn.innerText = 'CREATE AMC CONTRACT & GENERATE SCHEDULES';
                }
            } catch (err) {
                alert('Contract creation failed.');
                btn.disabled = false;
                btn.innerText = 'CREATE AMC CONTRACT & GENERATE SCHEDULES';
            }
        }

        async function renewContract(contractId) {
            if (confirm('Are you sure you want to renew this contract for another 12 months with a fresh visit schedule?')) {
                const formData = new FormData();
                formData.append('action', 'renew');
                formData.append('id', contractId);

                const res = await fetch('api/amc_contracts_api.php', { method: 'POST', body: formData });
                const json = await res.json();
                if (json.status === 'success') {
                    alert('🎉 ' + json.message);
                    loadAmcContracts();
                } else {
                    alert('Error: ' + json.message);
                }
            }
        }

        async function viewContractDetails(contractId) {
            try {
                const res = await fetch(`api/amc_contracts_api.php?action=get&id=${contractId}`);
                const json = await res.json();
                if (json.status === 'success') {
                    const c = json.data;
                    let msg = `AMC CONTRACT DETAILS (${c.amc_number})\n\nCustomer: ${c.customer_name}\nPhone: ${c.customer_phone}\nAddress: ${c.customer_address}\nPeriod: ${c.start_date} to ${c.end_date}\n\nVisits:\n`;
                    (c.visits || []).forEach(v => {
                        msg += `- Visit #${v.visit_number} on ${v.scheduled_date}: ${v.status} (${v.assigned_engineer})\n`;
                    });
                    alert(msg);
                }
            } catch (e) {
                alert('Failed to load contract details.');
            }
        }

        async function loadAmcProducts() {
            const tbody = document.getElementById('amcProductsTableBody');
            try {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading products...</td></tr>';
                const res = await fetch('api/amc_products_api.php?action=list');
                const json = await res.json();
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No products found.</td></tr>';
                    return;
                }
                json.data.forEach(p => {
                    const tr = document.createElement('tr');
                    tr.innerHTML = `
                        <td>${p.id}</td>
                        <td><strong>${p.name}</strong></td>
                        <td>${p.description || 'N/A'}</td>
                        <td><span class="badge-amc ${p.is_active == 1 ? 'badge-completed' : 'badge-overdue'}">${p.is_active == 1 ? 'Active' : 'Inactive'}</span></td>
                        <td>
                            <button class="btn btn-sm ${p.is_active == 1 ? 'btn-danger' : 'btn-success'}" onclick="toggleProductStatus(${p.id}, ${p.is_active == 1 ? 0 : 1})">${p.is_active == 1 ? 'Deactivate' : 'Activate'}</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Error loading products.</td></tr>';
            }
        }

        async function submitAddProduct(e) {
            e.preventDefault();
            const formData = new FormData(e.target);
            formData.append('action', 'add');

            const res = await fetch('api/amc_products_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                closeAddProductModal();
                loadAmcProducts();
            } else {
                alert('Error: ' + json.message);
            }
        }

        async function toggleProductStatus(id, newStatus) {
            const formData = new FormData();
            formData.append('action', 'toggle');
            formData.append('id', id);
            formData.append('is_active', newStatus);

            const res = await fetch('api/amc_products_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                loadAmcProducts();
            } else {
                alert('Error: ' + json.message);
            }
        }

        async function loadAmcAuditLogs() {
            const tbody = document.getElementById('amcAuditLogsTableBody');
            try {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center">Loading audit logs...</td></tr>';
                const res = await fetch('api/amc_contracts_api.php?action=get&id=1'); // get sample or contracts list audit
                // or fetch from DB
                const res2 = await fetch('api/amc_reports_api.php?action=dashboard_stats');
                tbody.innerHTML = '<tr><td colspan="5" class="text-center" style="padding:20px; color:#64748b;">Audit logs are continuously recorded in amc_audit_logs.</td></tr>';
            } catch (e) {}
        }
    </script>
</body>

</html>
