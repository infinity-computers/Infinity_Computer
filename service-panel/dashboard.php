<?php include __DIR__ . '/auth_guard.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
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

        .action-btn {
            padding: 5px 10px;
            font-size: 0.85rem;
            margin: 2px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 600;
        }

        .btn-accept {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .btn-reject {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        @media (max-width: 768px) {
            .form-grid {
                grid-template-columns: 1fr;
            }
        }

        .details-layout {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 30px;
            align-items: start;
        }

        @media (max-width: 992px) {
            .details-layout {
                grid-template-columns: 1fr;
            }
        }

        .hidden-tab {
            display: none !important;
        }

        .visible-tab {
            display: inline-block !important;
        }

        /* Sync Header Underline */
        .nav-links a.header-active {
            color: var(--primary-color) !important;
        }

        .nav-links a.header-active::after {
            width: 60% !important;
            left: 20% !important;
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
                        style="font-size: 0.65rem; color: #fb2a71; font-weight: 700; text-transform: uppercase;">Service
                        Panel</span>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Track Service</a></li>
                <li><a href="javascript:void(0)" id="headerNewService" onclick="switchTab('new-service-tab')">Add New
                        Service</a></li>
                <li><a href="javascript:void(0)" id="headerDashboard" onclick="switchTab('main-workflow')"
                        class="header-active">Dashboard</a></li>
                <?php if (in_array($_SESSION['staff_email'] ?? '', ['suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in'])): ?>
                <li><a href="crm.php">CRM Analytics</a></li>
                <?php endif; ?>
                <li><a href="logout.php" style="color: #dc3545; font-weight: 600; border: 1px solid #dc3545; border-radius: 5px; padding: 5px 12px; margin-left: 10px; text-decoration: none;">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('main-workflow')">Active Jobs</button>
            <button class="tab-btn" onclick="switchTab('user-requests')">User Requests</button>
            <button class="tab-btn" onclick="switchTab('home-services')">Home Services</button>

            <button class="tab-btn hidden-tab" id="detailsTabBtn" onclick="switchTab('details-tab')">Manage
                Service</button>
        </div>

        <!-- 1. ACTIVE JOBS TAB -->
        <div id="main-workflow" class="tab-pane active">
            <div
                style="display:flex; justify-content:space-between; align-items:center; margin-bottom:25px; flex-wrap: wrap; gap: 20px;">
                <h2 style="font-size:1.5rem; margin:0;">Internal Service Workflow</h2>
                <div class="search-bar" style="max-width:350px; margin:0;">
                    <input type="text" id="adminSearchInput" class="form-control" placeholder="Search ID or Phone..."
                        style="border-radius:10px;" onkeypress="if(event.key === 'Enter') searchAdmin()">
                    <button class="btn btn-primary" onclick="searchAdmin()">Search</button>
                </div>
            </div>

            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Service ID</th>
                            <th>Date Received</th>
                            <th>Customer</th>
                            <th>Device & Type</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="serviceTableBody">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading requests...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. USER REQUESTS TAB -->
        <div id="user-requests" class="tab-pane">
            <h2 style="font-size:1.5rem; margin-bottom:20px;">Public User Requests (Pending Approval)</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Service ID</th>
                            <th>Submitted</th>
                            <th>Customer</th>
                            <th>Device & Problem</th>
                            <th>Engineer</th>
                            <th>Status</th>
                            <th>Action & Received</th>
                        </tr>
                    </thead>
                    <tbody id="userRequestsTableBody">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading requests...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 3. HOME SERVICES TAB -->
        <div id="home-services" class="tab-pane">
            <h2 style="font-size:1.5rem; margin-bottom:20px;">Home Service Bookings</h2>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Date</th>
                            <th>Customer</th>
                            <th>Address &amp; Slot</th>
                            <th>Service &amp; Booked Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="homeServicesTableBody">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading bookings...
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 4. NEW SERVICE TAB -->
        <div id="new-service-tab" class="tab-pane">
            <div class="card" style="max-width: 900px; margin: 0 auto; padding: 40px;">
                <h2 class="card-title">Register New Service Request</h2>
                <form id="addServiceForm">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Customer Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name" class="form-control" required placeholder="Full Name">
                        </div>
                        <div class="form-group">
                            <label>Phone Number <span style="color:var(--danger)">*</span></label>
                            <input type="tel" name="phone" class="form-control" required placeholder="e.g. 9876543210">
                        </div>
                        <div class="form-group">
                            <label>Email Address <span style="color:var(--danger)">*</span></label>
                            <input type="email" name="email" class="form-control" required placeholder="e.g. user@example.com">
                        </div>
                        <div class="form-group">
                            <label>Service Type <span style="color:var(--danger)">*</span></label>
                            <select name="service_type" class="form-control" required>
                                <option value="">Select Type...</option>
                                <option value="Laptop Repair">Laptop Repair</option>
                                <option value="Mobile Repair">Mobile Repair</option>
                                <option value="PC Assembly">PC Assembly</option>
                                <option value="Printer Service">Printer Service</option>
                                <option value="Network Setup">Network Setup</option>
                                <option value="Data Recovery">Data Recovery</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Device Name / Model <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="device_name" class="form-control" placeholder="e.g. Dell XPS 15"
                                required>
                        </div>
                        <div class="form-group">
                            <label>Company Name <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="company" class="form-control" required placeholder="e.g. Acme Corp">
                        </div>
                        <div class="form-group">
                            <label>Assign Engineer</label>
                            <select name="assigned_engineer" id="add_service_engineer_select" class="form-control">
                                <option value="">Select Engineer...</option>
                                <option value="Suraj">Suraj</option>
                                <option value="Akshar">Akshar</option>
                                <option value="Karan">Karan</option>
                                <option value="Rahul">Rahul</option>
                                <option value="Paresh">Paresh</option>
                            </select>
                        </div>
                    </div>

                    <div class="form-group mt-4">
                        <label style="display: flex; align-items: center; gap: 10px; cursor: pointer;">
                            <input type="checkbox" name="device_received" value="1" checked
                                style="width: 20px; height: 20px; accent-color: var(--primary-color);">
                            <span style="font-weight: 500;">Device Received at Station</span>
                        </label>
                        <small style="color: #6c757d; display: block; margin-top: 5px;">Uncheck this if the device has
                            not been dropped off yet. The request will go to User Requests instead of Active
                            Jobs.</small>
                    </div>

                    <div class="form-group mt-4">
                        <label>Problem Description <span style="color:var(--danger)">*</span></label>
                        <textarea name="problem" class="form-control" rows="4" required
                            placeholder="Describe the issue in detail..."></textarea>
                    </div>

                    <div class="form-group mt-4">
                        <label>Upload Device Image <span style="color:var(--danger)">*</span></label>
                        <div class="image-upload-wrapper">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                                <label
                                    style="flex: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; background: #6c757d; color: white; padding: 10px; border-radius: 8px; font-size: 0.9rem; transition: background 0.3s;"
                                    onmouseover="this.style.background='#5a6268'"
                                    onmouseout="this.style.background='#6c757d'">
                                    <span>From Gallery</span>
                                    <input type="file" accept="image/*" class="image-input" style="display: none;">
                                </label>
                                <label class="camera-btn"
                                    style="flex: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--primary-color); color: white; padding: 10px; border-radius: 8px; font-size: 0.9rem; transition: background 0.3s;">
                                    <span>Take Photo</span>
                                    <input type="file" accept="image/*" capture="environment" class="image-input"
                                        style="display: none;">
                                </label>
                            </div>
                            <div id="imagePreview"></div>
                        </div>
                    </div>

                    <div class="text-center mt-4" style="display: flex; flex-direction: column; align-items: center; gap: 15px;">
                        <div class="recaptcha-wrapper">
                            <div class="g-recaptcha" data-sitekey="6LcadY0sAAAAAJZIH1jS5M3spZQ9qRn05lF0oB6d"
                                data-callback="onPanelRecaptchaSuccess" data-expired-callback="onPanelRecaptchaExpired"></div>
                        </div>
                        <button type="submit" class="btn btn-primary" id="panelSubmitBtn" disabled
                            style="width:100%; max-width:300px; padding:15px; font-size:1.1rem;">Submit Request</button>
                    </div>
                </form>
                <div id="formMsg" class="mt-4 text-center" style="font-weight:600; font-size:1.1rem;"></div>
            </div>
        </div>

        <!-- 5. DETAILS TAB -->
        <div id="details-tab" class="tab-pane">
            <div class="details-layout" id="detailsMainContainer">
                <div class="card" style="padding:35px;">
                    <div class="card-title" style="display:flex; justify-content:space-between; align-items:center;">
                        <span>Service Details</span>
                        <span id="svcIdDisplay" style="font-size:1.2rem; font-weight:700;"></span>
                    </div>
                    <div id="detailsArea">Select a record to view details...</div>
                </div>

                <div class="card"
                    style="padding:35px; background:var(--bg-light); border:0; box-shadow:inset 0 2px 4px rgba(0,0,0,0.02), var(--shadow);">
                    <div class="card-title" style="border-bottom-color:#cbd5e1;">Update Status</div>
                    <form id="updateStatusForm">
                        <input type="hidden" id="internalId" name="id">
                        <div class="form-group">
                            <label>Current Status</label>
                            <select name="status" id="statusSelect" class="form-control" required
                                style="font-weight:600; border:2px solid var(--border-color);">
                                <option value="Pending">Pending</option>
                                <option value="Accepted">Accepted</option>
                                <option value="Diagnosing">Diagnosing</option>
                                <option value="Repair in Progress">Repair in Progress</option>
                                <option value="Waiting for Parts">Waiting for Parts</option>
                                <option value="Completed">Completed</option>
                                <option value="Ready for Pickup">Ready for Pickup</option>
                                <option value="Delivered">Delivered</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Assigned Engineer</label>
                            <select name="assigned_engineer" id="engineerSelect" class="form-control" style="font-weight:600;">
                                <option value="">Not Assigned</option>
                                <option value="Suraj">Suraj</option>
                                <option value="Akshar">Akshar</option>
                                <option value="Karan">Karan</option>
                                <option value="Rahul">Rahul</option>
                                <option value="Paresh">Paresh</option>
                            </select>
                            <small id="engLockMsg" style="color:var(--danger); display:none; margin-top:5px;">Assignment cannot be changed for completed jobs.</small>
                        </div>
                        <div class="form-group">
                            <label>Remarks / Notes (Internal & Public)</label>
                            <textarea name="remarks" class="form-control" rows="3"
                                placeholder="Add an update note..."></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary"
                            style="width:100%; padding:14px; font-size:1.05rem;">Save Update</button>
                    </form>
                    <div id="updateMsg" class="mt-4" style="font-weight:600; text-align:center;"></div>
                    <button class="btn btn-secondary mt-3" style="width:100%; background:#6c757d; color:#fff;"
                        onclick="switchTab('main-workflow')">Back to List</button>
                </div>
            </div>
        </div>

    </div>

    <script src="assets/js/image-processor.js?v=1.4"></script>
    <script src="assets/js/main.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            loadRecentServices();
            loadUserRequests();
            loadHomeServices();

            // Init Image Processor
            ImageProcessor.setupPreview('.image-input', '#imagePreview', false);
            ImageProcessor.initCameraVisibility('.camera-btn');

            // Load Dynamic Engineers
            loadEngineersIntoSelect('add_service_engineer_select');
            loadEngineersIntoSelect('engineerSelect');
        });

        function switchTab(id) {
            document.querySelectorAll('.tab-btn').forEach(btn => btn.classList.remove('active'));
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));

            const btn = document.querySelector(`.tab-btn[onclick="switchTab('${id}')"]`);
            if (btn) btn.classList.add('active');
            document.getElementById(id).classList.add('active');

            if (id !== 'details-tab') {
                document.getElementById('detailsTabBtn').classList.add('hidden-tab');
            }

            // Sync Header Links UI
            const headerNewService = document.getElementById('headerNewService');
            const headerDashboard = document.getElementById('headerDashboard');
            const tabContainer = document.querySelector('.tabs');

            if (headerNewService && headerDashboard) {
                headerNewService.classList.remove('header-active');
                headerDashboard.classList.remove('header-active');

                if (id === 'new-service-tab') {
                    headerNewService.classList.add('header-active');
                    if (tabContainer) tabContainer.style.display = 'none';
                } else {
                    if (tabContainer) tabContainer.style.display = 'flex';

                    if (['main-workflow', 'user-requests', 'home-services', 'details-tab'].includes(id)) {
                        headerDashboard.classList.add('header-active');
                    }
                }
            }

            // Auto-refresh lists when switching back
            if (id === 'main-workflow') loadRecentServices();
            if (id === 'user-requests') loadUserRequests();
            if (id === 'home-services') loadHomeServices();
        }

        // ====== MAIN WORKFLOW ======
        async function loadRecentServices() {
            try {
                const res = await fetch('api/list_services.php');
                const json = await res.json();
                renderTable(json.data || []);
            } catch (e) {
                document.getElementById('serviceTableBody').innerHTML = '<tr><td colspan="6" class="text-center text-danger">Database error.</td></tr>';
            }
        }

        async function searchAdmin() {
            const q = document.getElementById('adminSearchInput').value.trim();
            if (!q) return loadRecentServices();
            try {
                document.getElementById('serviceTableBody').innerHTML = '<tr><td colspan="6" class="text-center">Searching...</td></tr>';
                const res = await fetch(`api/search_service.php?q=${encodeURIComponent(q)}`);
                const json = await res.json();
                renderTable(json.data || []);
            } catch (e) { }
        }

        function renderTable(services) {
            const tbody = document.getElementById('serviceTableBody');
            tbody.innerHTML = '';
            if (services.length === 0) {
                tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:40px; color:#6c757d;">No records found.</td></tr>';
                return;
            }
            services.forEach(svc => {
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><strong style="color:var(--primary-dark); font-size:1.05rem;">${svc.service_id}</strong></td>
                    <td>${formatDate(svc.date_received)}</td>
                    <td><div style="font-weight:600;">${svc.name}</div><div class="text-muted" style="font-size:0.9rem;">${svc.phone}</div></td>
                    <td><div style="font-weight:600;">${svc.device_name}</div><div class="text-muted" style="font-size:0.9rem;">${svc.service_type}</div></td>
                    <td><span class="${getStatusBadgeClass(svc.status)}">${svc.status}</span><br><small style="color:#666;">${svc.assigned_engineer ? '🔧 ' + svc.assigned_engineer : '<i>Unassigned</i>'}</small></td>
                    <td><button onclick="viewDetails('${svc.service_id}')" class="btn btn-primary" style="padding: 5px 15px; font-size: 0.85rem;">Manage</button></td>
                `;
                tbody.appendChild(tr);
            });
        }

        // ====== DETAILS LOGIC ======
        async function viewDetails(svcId) {
            const btn = document.getElementById('detailsTabBtn');
            btn.classList.remove('hidden-tab');
            btn.classList.add('visible-tab');
            switchTab('details-tab');

            document.getElementById('detailsArea').innerHTML = 'Loading...';
            document.getElementById('svcIdDisplay').innerHTML = '';

            try {
                const res = await fetch(`api/get_service.php?id=${encodeURIComponent(svcId)}`);
                const json = await res.json();

                if (json.status === 'success' && json.data) {
                    const svc = json.data;
                    document.getElementById('svcIdDisplay').innerHTML = `${svc.service_id} <span class="${getStatusBadgeClass(svc.status)}">${svc.status}</span>`;
                    document.getElementById('internalId').value = svc.id;
                    document.getElementById('statusSelect').value = svc.status;
                    document.getElementById('engineerSelect').value = svc.assigned_engineer || '';

                    // Disable engineer selection if Completed
                    const engSelect = document.getElementById('engineerSelect');
                    const lockMsg = document.getElementById('engLockMsg');
                    if (svc.status === 'Completed') {
                        engSelect.disabled = true;
                        lockMsg.style.display = 'block';
                    } else {
                        engSelect.disabled = false;
                        lockMsg.style.display = 'none';
                    }

                    let html = `
                        <div class="info-grid">
                            <div class="info-item"><label>Customer Name</label><div style="font-weight:600; font-size:1.1rem;">${svc.name}</div></div>
                            <div class="info-item"><label>Phone</label><div class="text-muted">${svc.phone}</div></div>
                            <div class="info-item"><label>Service Type</label><div>${svc.service_type}</div></div>
                            <div class="info-item"><label>Device Model</label><div style="font-weight:600;">${svc.device_name}</div></div>
                            <div class="info-item"><label>Date Received</label><div>${formatDate(svc.date_received)}</div></div>
                            <div class="info-item"><label>Assigned Engineer</label><div style="font-weight:700; color:var(--primary); font-size:1.1rem;">🔧 ${svc.assigned_engineer || 'Not Assigned'}</div></div>
                        </div>
                        <div class="mt-4 info-item" style="background:#fff; border:1px solid var(--border-color);">
                            <label>Problem Description</label>
                            <p style="margin:0; font-size:1.05rem;">${svc.problem}</p>
                        </div>
                    `;

                    if (svc.image_path) {
                        html += `<div class="mt-4"><label style="font-weight:600; color:var(--muted); font-size:0.85rem; text-transform:uppercase;">Device Image:</label><br><img src="../${svc.image_path}" style="max-height:200px; border-radius:8px; margin-top:5px; box-shadow:var(--shadow);"></div>`;
                    }

                    if (svc.logs && svc.logs.length > 0) {
                        html += `<h3 class="mt-4" style="color:var(--primary-dark); font-size:1.1rem; margin-top:30px !important;">Activity History</h3>
                        <div class="table-responsive"><table style="margin-top:10px; font-size:0.9rem;">
                            <tr><th>Time</th><th>Status</th><th>Note</th></tr>`;
                        svc.logs.forEach(log => {
                            html += `<tr>
                                <td style="white-space:nowrap;">${formatDate(log.updated_at)}</td>
                                <td><span class="${getStatusBadgeClass(log.status)}">${log.status}</span></td>
                                <td>${log.remarks || '-'}</td>
                            </tr>`;
                        });
                        html += `</table></div>`;
                    }

                    document.getElementById('detailsArea').innerHTML = html;
                } else {
                    document.getElementById('detailsArea').innerHTML = `<h3 class="text-danger">${json.message || 'Record not found.'}</h3>`;
                }
            } catch (e) {
                document.getElementById('detailsArea').innerHTML = '<h3 class="text-danger">Error loading record.</h3>';
            }
        }

        document.getElementById('updateStatusForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData.entries());

            try {
                const res = await fetch('api/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                const msg = document.getElementById('updateMsg');
                if (json.status === 'success') {
                    msg.innerHTML = `<span style="color:var(--success)">${json.message}</span>`;
                    e.target.elements['remarks'].value = '';
                    // Refresh details view
                    const svcId = document.getElementById('svcIdDisplay').innerText.split(' ')[0];
                    viewDetails(svcId);
                    setTimeout(() => msg.innerHTML = '', 3000);
                } else {
                    msg.innerHTML = `<span style="color:var(--danger)">Error: ${json.message}</span>`;
                }
            } catch (err) {
                alert('Update failed');
            }
            btn.disabled = false;
            btn.innerText = 'Save Update';
        });

        // ====== NEW SERVICE FORM LOGIC ======
        function onPanelRecaptchaSuccess() {
            document.getElementById('panelSubmitBtn').disabled = false;
        }
        function onPanelRecaptchaExpired() {
            document.getElementById('panelSubmitBtn').disabled = true;
        }

        document.getElementById('addServiceForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const btn = e.target.querySelector('button');
            btn.disabled = true;
            btn.innerText = 'Processing...';

            const formData = new FormData(e.target);
            if (window.lastProcessedBlob) {
                formData.set('image', window.lastProcessedBlob, 'processed_image.jpg');
            }

            try {
                const res = await fetch('api/add_service.php', { method: 'POST', body: formData });
                const json = await res.json();
                const msg = document.getElementById('formMsg');
                if (json.status === 'success') {
                    msg.innerHTML = `<span style="color:var(--success)">${json.message}.<br>Service ID: <strong style="font-size:1.4rem;">${json.service_id}</strong></span>`;
                    e.target.reset();
                    document.getElementById('imagePreview').innerHTML = '';
                    if (window.grecaptcha) grecaptcha.reset();
                    document.getElementById('panelSubmitBtn').disabled = true;
                    window.lastProcessedBlob = null;
                    setTimeout(() => { msg.innerHTML = ''; switchTab('main-workflow'); }, 3000);
                } else {
                    msg.innerHTML = `<span style="color:var(--danger)">Error: ${json.message}</span>`;
                    if (window.grecaptcha) grecaptcha.reset();
                    document.getElementById('panelSubmitBtn').disabled = true;
                }
            } catch (err) {
                alert('Request failed.');
                if (window.grecaptcha) grecaptcha.reset();
                document.getElementById('panelSubmitBtn').disabled = true;
            }
            btn.disabled = false;
            btn.innerText = 'Submit Request';
        });

        // ====== USER REQUESTS ======
        async function loadUserRequests() {
            try {
                const res = await fetch('api/get_user_requests.php');
                const json = await res.json();
                const tbody = document.getElementById('userRequestsTableBody');
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="6" class="text-center" style="padding:40px;">No user requests.</td></tr>';
                    return;
                }
                json.data.forEach(req => {
                    const tr = document.createElement('tr');

                    let actionHtml = '';
                    if (req.status === 'Pending Approval') {
                        actionHtml += `<button class="action-btn btn-accept" onclick="updateUserReq(${req.id}, 'Approved', -1, '')">Approve</button>`;
                        actionHtml += `<button class="action-btn btn-reject" onclick="updateUserReq(${req.id}, 'Rejected', -1, '')">Reject</button>`;
                    } else if (req.status === 'Approved' && req.device_received == 0) {
                        actionHtml += `<div style="font-size:0.8rem; color:#155724;">Approved. Awaiting Device.</div>`;
                    } else if (req.status === 'Approved' && req.device_received == 1) {
                        actionHtml += `<div style="font-size:0.8rem; color:var(--primary);">Migrated to active workflow</div>`;
                    }

                    actionHtml += `<button class="action-btn btn-reject" style="background:#fff5f5; color:#c53030; border-color:#feb2b2; margin-left:10px;" onclick="deleteUserReq(${req.id})">Delete</button>`;

                    let drCheck = `<div style="margin-top:5px; font-size:0.85rem;"><label><input type="checkbox" ${req.device_received == 1 ? 'checked disabled' : ''} onchange="updateUserReq(${req.id}, '', this.checked ? 1 : 0, '')"> Device Received</label></div>`;
                    if (req.status === 'Rejected') drCheck = '';

                    tr.innerHTML = `
                        <td><strong>${req.service_id}</strong><div style="font-size:0.8rem;"><a href="../${req.image_path}" target="_blank">View Image</a></div></td>
                        <td>${formatDate(req.created_at)}</td>
                        <td><div style="font-weight:600;">${req.name}</div><div style="font-size:0.85rem;">${req.phone}</div></td>
                        <td><div style="font-weight:600; font-size:0.9rem;">${req.device_type} - ${req.brand} ${req.model}</div><div style="font-size:0.85rem; color:#555;">${req.problem}</div></td>
                        <td>
                            <select onchange="updateUserReq(${req.id}, '', -1, this.value)" class="form-control" style="font-size:0.85rem; padding:4px;">
                                <option value="">Select...</option>
                                ${window.allEngineers.map(eng => `<option value="${eng.name}" ${req.assigned_engineer === eng.name ? 'selected' : ''}>${eng.name}</option>`).join('')}
                            </select>
                        </td>
                        <td><span class="${getStatusBadgeClass(req.status)}">${req.status}</span></td>
                        <td>${actionHtml} ${drCheck}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) { }
        }

        async function updateUserReq(id, status, deviceReceived, engineer) {
            try {
                const fd = new FormData();
                fd.append('id', id);
                if (status) fd.append('status', status);
                if (deviceReceived !== -1) fd.append('device_received', deviceReceived);
                if (engineer) fd.append('assigned_engineer', engineer);
                await fetch('api/update_user_request_status.php', { method: 'POST', body: fd });
                loadUserRequests();
                loadRecentServices();
            } catch (e) { alert('Update failed'); }
        }

        async function deleteUserReq(id) {
            if (!confirm('Delete this request?')) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                const res = await fetch('api/delete_user_request.php', { method: 'POST', body: fd });
                const json = await res.json();
                if (json.status === 'success') loadUserRequests();
            } catch (e) { }
        }

        // ====== HOME SERVICES ======
        async function loadHomeServices() {
            try {
                const res = await fetch('api/get_home_service_requests.php');
                const json = await res.json();
                const tbody = document.getElementById('homeServicesTableBody');
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:40px;">No bookings.</td></tr>';
                    return;
                }
                json.data.forEach(req => {
                    const tr = document.createElement('tr');
                    let actionHtml = '';
                    if (req.status === 'Pending') {
                        actionHtml = `
                            <button class="action-btn btn-accept" onclick="updateHomeServiceStatus(${req.id}, 'Accepted')">Accept</button>
                            <button class="action-btn btn-reject" onclick="updateHomeServiceStatus(${req.id}, 'Rejected')">Reject</button>
                        `;
                    }
                    actionHtml += `<button class="action-btn" style="background:#fff5f5; color:#c53030; border:1px solid #feb2b2; margin-left:10px;" onclick="deleteHomeService(${req.id})">Delete</button>`;

                    tr.innerHTML = `
                        <td><strong>${req.service_id}</strong></td>
                        <td>${formatDate(req.created_at)}</td>
                        <td><div style="font-weight:600;">${req.name}</div><div style="font-size:0.85rem;">${req.phone}</div></td>
                        <td><div style="font-size:0.85rem;">${req.address}</div><div style="font-weight:600; color:var(--primary); font-size:0.85rem; margin-top:2px;">Slot: ${req.time_slot}</div></td>
                        <td><div style="font-weight:600;">${req.service_type}</div><div style="color:var(--accent); font-size:0.85rem;">${req.booking_date}</div></td>
                        <td><span class="${getStatusBadgeClass(req.status)}">${req.status}</span></td>
                        <td>${actionHtml}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) { }
        }

        async function updateHomeServiceStatus(id, status) {
            try {
                const fd = new FormData();
                fd.append('id', id);
                fd.append('status', status);
                await fetch('api/update_home_service_status.php', { method: 'POST', body: fd });
                loadHomeServices();
            } catch (e) { }
        }

        async function deleteHomeService(id) {
            if (!confirm('Delete this booking?')) return;
            try {
                const fd = new FormData();
                fd.append('id', id);
                await fetch('api/delete_home_service.php', { method: 'POST', body: fd });
                loadHomeServices();
            } catch (e) { }
        }
    </script>
</body>

</html>
