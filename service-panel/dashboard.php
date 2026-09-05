<?php 
include __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/config/db.php';
$engQuery = "SELECT name FROM engineers ORDER BY name ASC";
$engResult = $conn->query($engQuery);
$engineersList = [];
if ($engResult) {
    while ($row = $engResult->fetch_assoc()) {
        $engineersList[] = $row['name'];
    }
} else {
    $engineersList = ['Suraj', 'Akshar', 'Karan', 'Rahul', 'Paresh'];
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Staff Panel</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css?v=2.1">
    <link rel="stylesheet" href="assets/css/amc.css?v=1.0">
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


        /* Live Operations Dashboard Counters */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stat-card {
            background: #fff;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.01), var(--shadow);
            cursor: pointer;
            transition: all 0.25s ease;
            border-left: 5px solid var(--primary-color);
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.06), var(--shadow);
        }
        .stat-card.active-filter {
            border-left-width: 8px;
            background: rgba(31, 95, 174, 0.04);
            box-shadow: inset 0 0 0 1px var(--primary-color), var(--shadow);
        }
        .stat-card .stat-label {
            font-size: 0.8rem;
            color: #64748b;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 5px;
        }
        .stat-card .stat-val {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--text-dark);
            margin: 0;
        }
        .warning-badge {
            background: #ef4444;
            color: #fff;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 700;
            display: inline-block;
            margin-top: 5px;
        }
        .status-select {
            padding: 4px 8px;
            border-radius: 6px;
            font-family: inherit;
            font-size: 0.85rem;
            font-weight: 600;
            border: 1px solid var(--border-color);
            background: #fff;
            cursor: pointer;
        }
    </style>
</head>

<body>
<?php $activeNav = 'dashboard'; include __DIR__ . '/partials/nav.php'; ?>
<?php
// Error log display (temporary debug)
$logDir = __DIR__ . '/../logs';
function getLogContents(string $filePath): string {
    if (is_file($filePath) && filesize($filePath) > 0) {
        return nl2br(htmlspecialchars(file_get_contents($filePath)));
    }
    return '';
}
$updateStatusLog = getLogContents($logDir . '/update_status_error.log');
$updateTaskLog = getLogContents($logDir . '/update_task_status_error.log');
if ($updateStatusLog !== '' || $updateTaskLog !== '') {
    echo '<div style="
        background:#fff5f5;
        border:2px solid #ef4444;
        border-radius:8px;
        color:#b91c1c;
        padding:15px;
        margin:20px auto;
        max-width:900px;
        font-family:var(--font-family,Arial,sans-serif);
        line-height:1.4;">
    <h3 style="margin-top:0; font-size:1.2rem;">&#128165; Server-side error log</h3>';
    if ($updateStatusLog !== '') {
        echo '<strong>update_status.php:</strong><br>' . $updateStatusLog . '<hr style="border-color:#ef4444;margin:12px 0;">';
    }
    if ($updateTaskLog !== '') {
        echo '<strong>update_task_status.php:</strong><br>' . $updateTaskLog;
    }
    echo '</div>';
}
?>

    <div class="container">
        <div class="tabs">
            <button class="tab-btn active" onclick="switchTab('main-workflow')">Active Jobs</button>
            <button class="tab-btn" onclick="switchTab('amc-assignments-tab'); loadMyAmcAssignments();">My AMC Assignments</button>
            <button class="tab-btn" onclick="switchTab('user-requests')">User Requests</button>
            <button class="tab-btn" onclick="switchTab('home-services')">Home Services</button>
            <?php if (isSuperAdmin()): ?>
            <button class="tab-btn" onclick="switchTab('activity-log-tab')">Activity Log</button>
            <?php endif; ?>

            <button class="tab-btn hidden-tab" id="detailsTabBtn" onclick="switchTab('details-tab')">Manage
                Service</button>
        </div>

        <!-- 1. ACTIVE JOBS TAB -->
        <div id="main-workflow" class="tab-pane active">
            <!-- Stats Grid -->
            <div class="stats-grid" id="statsGrid">
                <div class="stat-card" data-filter="new_calls" style="border-left-color: #f59e0b;">
                    <div class="stat-label">New Calls</div>
                    <div class="stat-val" id="count-new-calls">0</div>
                </div>
                <div class="stat-card" data-filter="assigned" style="border-left-color: #3b82f6;">
                    <div class="stat-label">Assigned</div>
                    <div class="stat-val" id="count-assigned">0</div>
                </div>
                <div class="stat-card" data-filter="inprogress" style="border-left-color: #06b6d4;">
                    <div class="stat-label">In Progress</div>
                    <div class="stat-val" id="count-inprogress">0</div>
                </div>
                <div class="stat-card" data-filter="pending_approval" style="border-left-color: #8b5cf6;">
                    <div class="stat-label">Pending Approval</div>
                    <div class="stat-val" id="count-approval">0</div>
                </div>
                <div class="stat-card" data-filter="pending_billing" style="border-left-color: #ec4899;">
                    <div class="stat-label">Pending Billing</div>
                    <div class="stat-val" id="count-billing">0</div>
                </div>
                <div class="stat-card" data-filter="closed_today" style="border-left-color: #10b981;">
                    <div class="stat-label">Closed Today</div>
                    <div class="stat-val" id="count-closed-today">0</div>
                </div>
                <div class="stat-card" style="border-left-color: #059669; cursor: default;">
                    <div class="stat-label">Revenue Today</div>
                    <div class="stat-val" id="sum-revenue">₹0</div>
                </div>
            </div>

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

            <div style="height: 40px;"></div>

            <!-- Engineer Status Board Section -->
            <div class="card" style="padding:30px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-radius:12px; background:#fff;">
                <div class="card-title" style="margin-bottom: 20px; font-size:1.3rem; font-weight:700; color:var(--primary-dark);">Engineer Status Board</div>
                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Status</th>
                                <th>Current Ticket</th>
                                <th>Last Activity</th>
                            </tr>
                        </thead>
                        <tbody id="engineerStatusTableBody">
                            <tr>
                                <td colspan="4" class="text-center" style="padding:20px; color:#6c757d;">Loading status board...</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- AMC ASSIGNMENTS TAB -->
        <div id="amc-assignments-tab" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <h2 style="font-size:1.5rem; margin:0;">My AMC Visit Assignments</h2>
                <button class="btn btn-secondary" onclick="loadMyAmcAssignments()" style="padding:8px 16px;">🔄 Refresh AMC Visits</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>AMC #</th>
                            <th>Visit #</th>
                            <th>Customer &amp; Location</th>
                            <th>Products Covered</th>
                            <th>Scheduled Date</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody id="myAmcVisitsTableBody">
                        <tr>
                            <td colspan="7" class="text-center" style="padding:40px; color:#6c757d;">Loading assigned AMC visits...</td>
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
                                <option value="CCTV Service">CCTV Service</option>
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
                            <select name="assigned_engineer" class="form-control">
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
                        <label>Upload Device Images <span style="color:var(--danger)">*</span> <small>(Up to 5 photos)</small></label>
                        <div class="image-upload-wrapper">
                            <div style="display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 10px;">
                                <label class="img-add-btn"
                                    style="flex: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; background: #6c757d; color: white; padding: 10px; border-radius: 8px; font-size: 0.9rem; transition: background 0.3s;"
                                    onmouseover="this.style.background='#5a6268'"
                                    onmouseout="this.style.background='#6c757d'">
                                    <span>From Gallery</span>
                                    <input type="file" accept="image/*" multiple style="display: none;">
                                </label>
                                <label class="img-add-btn camera-btn"
                                    style="flex: 1; cursor: pointer; display: flex; align-items: center; justify-content: center; gap: 8px; background: var(--primary-color); color: white; padding: 10px; border-radius: 8px; font-size: 0.9rem; transition: background 0.3s;">
                                    <span>Take Photo</span>
                                    <input type="file" accept="image/*" capture="environment" style="display: none;">
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
                                <option value="Waiting for Customer Approval">Waiting for Customer Approval</option>
                                <option value="Waiting for Customer Reply">Waiting for Customer Reply</option>
                                <option value="Customer Call Not Answered">Customer Call Not Answered</option>
                                <option value="Waiting for Pending Estimate">Waiting for Pending Estimate</option>
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
                                <?php foreach($engineersList as $eng): ?>
                                <option value="<?= htmlspecialchars($eng) ?>"><?= htmlspecialchars($eng) ?></option>
                                <?php endforeach; ?>
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

        <!-- 6. ACTIVITY LOG TAB (SUPER ADMIN ONLY) -->
        <?php if (isSuperAdmin()): ?>
        <div id="activity-log-tab" class="tab-pane">
            <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:20px;">
                <h2 style="font-size:1.5rem; margin:0;">Accountability Log &amp; Audit Trail</h2>
                <button class="btn btn-secondary" onclick="loadActivityLog()" style="padding: 5px 15px; font-size: 0.85rem;">Refresh</button>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                        <tr>
                            <th>Time</th>
                            <th>Performed By</th>
                            <th>Event / Status</th>
                            <th>Service ID</th>
                            <th>Details</th>
                        </tr>
                    </thead>
                    <tbody id="activityLogTableBody">
                        <tr>
                            <td colspan="5" class="text-center" style="padding:40px; color:#6c757d;">Loading audit trail...</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="assets/js/image-processor.js?v=2.0"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const STAFF_ROLE = <?php echo json_encode(getStaffRole()); ?>;
        const STAFF_NAME = <?php echo json_encode(getStaffName()); ?>;
        const IS_ADMIN = <?php echo json_encode(isAdmin()); ?>;
        const IS_SUPER_ADMIN = <?php echo json_encode(isSuperAdmin()); ?>;
        let currentFilter = '';

        document.addEventListener('DOMContentLoaded', () => {
            fetchDashboardStats();
            loadRecentServices();
            loadUserRequests();
            loadHomeServices();
            loadEngineerStatus();
            if (document.getElementById('activityLogTableBody')) {
                loadActivityLog();
            }

            // Wire up card filter clicks
            document.querySelectorAll('.stat-card').forEach(card => {
                const filterType = card.getAttribute('data-filter');
                if (filterType) {
                    card.addEventListener('click', () => {
                        const isAlreadyActive = card.classList.contains('active-filter');
                        document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active-filter'));
                        if (isAlreadyActive) {
                            currentFilter = '';
                        } else {
                            card.classList.add('active-filter');
                            currentFilter = filterType;
                        }
                        loadRecentServices();
                    });
                }
            });

            // Init Multi-Image Processor
            window.lastProcessedBlobs = [];
            if (typeof ImageProcessor !== 'undefined') {
                ImageProcessor.setupMultiPreview('.img-add-btn', '#imagePreview', false);
                ImageProcessor.initCameraVisibility('.camera-btn');
            }

            // Setup Auto-refresh (60 seconds)
            setInterval(() => {
                fetchDashboardStats();
                loadRecentServices();
                loadUserRequests();
                loadHomeServices();
                loadEngineerStatus();
            }, 60000);

            // Setup Audit Log Auto-refresh (120 seconds)
            if (document.getElementById('activityLogTableBody')) {
                setInterval(() => {
                    loadActivityLog();
                }, 120000);
            }
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
            if (id === 'activity-log-tab') loadActivityLog();
        }

        // ====== MAIN WORKFLOW ======
        async function loadRecentServices() {
            try {
                const res = await fetch(`api/list_services.php?filter=${encodeURIComponent(currentFilter)}`);
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
                const warningHtml = svc.stuck_warning ? `<br><span class="warning-badge">${svc.stuck_warning}</span>` : '';
                tr.innerHTML = `
                    <td><strong style="color:var(--primary-dark); font-size:1.05rem;">${svc.service_id}</strong></td>
                    <td>${formatDate(svc.created_at || svc.date_received)}</td>
                    <td><div style="font-weight:600;">${svc.name}</div><div class="text-muted" style="font-size:0.9rem;">${svc.phone}</div></td>
                    <td><div style="font-weight:600;">${svc.device_name}</div><div class="text-muted" style="font-size:0.9rem;">${svc.service_type}</div></td>
                    <td><span class="${getStatusBadgeClass(svc.status)}">${svc.status}</span><br><small style="color:#666;">${svc.assigned_engineer ? '🔧 ' + svc.assigned_engineer : '<i>Unassigned</i>'}</small>${warningHtml}</td>
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

                    // Manage permissions for status & engineer updates
                    const statusSelect = document.getElementById('statusSelect');
                    const engSelect = document.getElementById('engineerSelect');
                    const lockMsg = document.getElementById('engLockMsg');
                    const submitBtn = document.querySelector('#updateStatusForm button[type="submit"]');

                    const assignedEng = (svc.assigned_engineer || '').toLowerCase().trim();
                    const currentStaff = (STAFF_NAME || '').toLowerCase().trim();
                    const isAssignedToCurrent = assignedEng === currentStaff;
                    const canManageStatus = IS_ADMIN || isAssignedToCurrent;

                    if (!canManageStatus) {
                        statusSelect.disabled = true;
                        engSelect.disabled = true;
                        if (submitBtn) submitBtn.disabled = true;
                        lockMsg.innerText = 'Only the assigned engineer (' + (svc.assigned_engineer || 'Unassigned') + ') or Admin can update this ticket.';
                        lockMsg.style.display = 'block';
                    } else {
                        statusSelect.disabled = false;
                        if (submitBtn) submitBtn.disabled = false;
                        if (svc.status === 'Completed' && !IS_ADMIN) {
                            engSelect.disabled = true;
                            lockMsg.innerText = 'Assignment cannot be changed for completed jobs.';
                            lockMsg.style.display = 'block';
                        } else {
                            engSelect.disabled = !IS_ADMIN;
                            lockMsg.style.display = 'none';
                        }
                    }

                    let html = `
                        <div class="info-grid">
                            <div class="info-item"><label>Customer Name</label><div style="font-weight:600; font-size:1.1rem;">${svc.name}</div></div>
                            <div class="info-item"><label>Phone</label><div class="text-muted">${svc.phone}</div></div>
                            <div class="info-item"><label>Service Type</label><div>${svc.service_type}</div></div>
                            <div class="info-item"><label>Device Model</label><div style="font-weight:600;">${svc.device_name}</div></div>
                            <div class="info-item"><label>Date Received</label><div>${formatDate(svc.created_at || svc.date_received)}</div></div>
                            <div class="info-item"><label>Assigned Engineer</label><div style="font-weight:700; color:var(--primary); font-size:1.1rem;">🔧 ${svc.assigned_engineer || 'Not Assigned'}</div></div>
                        </div>
                        <div class="mt-4 info-item" style="background:#fff; border:1px solid var(--border-color);">
                            <label>Problem Description</label>
                            <p style="margin:0; font-size:1.05rem;">${svc.problem}</p>
                        </div>
                    `;

                    if (svc.images && svc.images.length > 0) {
                        html += `<div class="mt-4"><label style="font-weight:600; color:var(--muted); font-size:0.85rem; text-transform:uppercase;">Device Image${svc.images.length > 1 ? 's' : ''}:</label><div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">`;
                        svc.images.forEach(imgPath => {
                            html += `<img src="../${imgPath}" style="height:180px; width:auto; max-width:100%; border-radius:8px; box-shadow:var(--shadow); object-fit:cover; cursor:pointer;" onclick="window.open('../${imgPath}','_blank')" title="Click to open full size">`;
                        });
                        html += `</div></div>`;
                    } else if (svc.image_path) {
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
            const msg = document.getElementById('updateMsg');

            try {
                const res = await fetch('api/update_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                // Read raw text first so we can debug if it's not JSON
                const rawText = await res.text();
                let json;
                try {
                    json = JSON.parse(rawText);
                } catch (parseErr) {
                    // Server returned non-JSON (PHP error/warning) — show it raw
                    msg.innerHTML = `<div style="color:var(--danger); background:#fff5f5; border:1px solid #ef4444; padding:10px; border-radius:6px; font-size:0.85rem; max-height:200px; overflow:auto; text-align:left;">
                        <strong>Server returned invalid JSON (HTTP ${res.status}):</strong><br>
                        <pre style="white-space:pre-wrap; margin:5px 0 0;">${rawText.substring(0, 2000)}</pre>
                    </div>`;
                    btn.disabled = false;
                    btn.innerText = 'Save Update';
                    return;
                }
                if (json.status === 'success') {
                    msg.innerHTML = `<span style="color:var(--success)">${json.message}</span>`;
                    e.target.elements['remarks'].value = '';
                    const svcId = document.getElementById('svcIdDisplay').innerText.split(' ')[0];
                    viewDetails(svcId);
                    setTimeout(() => msg.innerHTML = '', 3000);
                } else {
                    msg.innerHTML = `<span style="color:var(--danger)">Error: ${json.message}</span>`;
                }
            } catch (err) {
                msg.innerHTML = `<span style="color:var(--danger)">Network Error: ${err.message}</span>`;
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
            if (window.lastProcessedBlobs && window.lastProcessedBlobs.length > 0) {
                window.lastProcessedBlobs.forEach((blob, i) => {
                    formData.append('images[]', blob, `device_image_${i + 1}.jpg`);
                });
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
                    window.lastProcessedBlobs = [];
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

                    const reqImgs = (req.images && req.images.length > 0) ? req.images : (req.image_path ? [req.image_path] : []);
                    const reqImgHtml = reqImgs.length > 0 ? `<div style="display:flex; gap:4px; flex-wrap:wrap; margin-top:4px;">${reqImgs.map((p,i) => `<a href="../${p}" target="_blank" style="font-size:0.78rem;">Photo ${i+1}</a>`).join(' | ')}</div>` : '';
                    tr.innerHTML = `
                        <td><strong>${req.service_id}</strong>${reqImgHtml}</td>
                        <td>${formatDate(req.created_at)}</td>
                        <td><div style="font-weight:600;">${req.name}</div><div style="font-size:0.85rem;">${req.phone}</div></td>
                        <td><div style="font-weight:600; font-size:0.9rem;">${req.device_type} - ${req.brand} ${req.model}</div><div style="font-size:0.85rem; color:#555;">${req.problem}</div></td>
                        <td>
                            <select onchange="updateUserReq(${req.id}, '', -1, this.value)" class="form-control" style="font-size:0.85rem; padding:4px;">
                                <option value="Suraj" ${req.assigned_engineer === 'Suraj' ? 'selected' : ''}>Suraj</option>
                                <option value="Akshar" ${req.assigned_engineer === 'Akshar' ? 'selected' : ''}>Akshar</option>
                                <option value="Karan" ${req.assigned_engineer === 'Karan' ? 'selected' : ''}>Karan</option>
                                <option value="Rahul" ${req.assigned_engineer === 'Rahul' ? 'selected' : ''}>Rahul</option>
                                <option value="Paresh" ${req.assigned_engineer === 'Paresh' ? 'selected' : ''}>Paresh</option>
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
            } catch (err) {
    document.getElementById('updateMsg').innerHTML = '<span style="color:var(--danger)">Error: Update failed. Please try again.</span>';
}
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

        async function fetchDashboardStats() {
            try {
                const res = await fetch('api/dashboard_stats.php');
                const json = await res.json();
                if (json.status === 'success') {
                    const stats = json.data;
                    document.getElementById('count-new-calls').innerText = stats.new_calls;
                    document.getElementById('count-assigned').innerText = stats.assigned;
                    document.getElementById('count-inprogress').innerText = stats.inprogress;
                    document.getElementById('count-approval').innerText = stats.pending_approval;
                    document.getElementById('count-billing').innerText = stats.pending_billing;
                    document.getElementById('count-closed-today').innerText = stats.closed_today;
                    document.getElementById('sum-revenue').innerText = '₹' + stats.revenue_today.toLocaleString('en-IN');
                }
            } catch (e) {
                console.error("Error fetching stats:", e);
            }
        }

        async function loadEngineerStatus() {
            try {
                const res = await fetch('api/get_engineers_status.php');
                const json = await res.json();
                const tbody = document.getElementById('engineerStatusTableBody');
                if (!tbody) return;
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center">No engineer statuses available.</td></tr>';
                    return;
                }
                
                json.data.forEach(eng => {
                    const tr = document.createElement('tr');
                    
                    let statusHtml = '';
                    if (IS_ADMIN) {
                        statusHtml = `<select class="status-select" onchange="updateEngineerStatus('${eng.name}', this.value)">
                            <option value="Active" ${eng.status === 'Active' ? 'selected' : ''}>Active</option>
                            <option value="On Call" ${eng.status === 'On Call' ? 'selected' : ''}>On Call</option>
                            <option value="In Transit" ${eng.status === 'In Transit' ? 'selected' : ''}>In Transit</option>
                            <option value="On Job" ${eng.status === 'On Job' ? 'selected' : ''}>On Job</option>
                            <option value="On Hold" ${eng.status === 'On Hold' ? 'selected' : ''}>On Hold</option>
                            <option value="Off Duty" ${eng.status === 'Off Duty' ? 'selected' : ''}>Off Duty</option>
                        </select>`;
                    } else {
                        statusHtml = `<span class="${getStatusBadgeClass(eng.status)}">${eng.status}</span>`;
                    }

                    tr.innerHTML = `
                        <td><strong>${eng.name}</strong></td>
                        <td>${statusHtml}</td>
                        <td>${eng.current_ticket ? `<strong style="color:var(--primary-dark);">${eng.current_ticket}</strong>` : '<i style="color:#94a3b8;">None</i>'}</td>
                        <td>${formatDate(eng.last_activity_at)}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                console.error("Error loading engineer status board:", e);
            }
        }

        async function updateEngineerStatus(name, newStatus) {
            try {
                const res = await fetch('api/update_engineer_status.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ name: name, status: newStatus })
                });
                const json = await res.json();
                if (json.status === 'success') {
                    loadEngineerStatus();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (e) {
                alert('Network error updating status.');
            }
        }

        async function loadActivityLog() {
            const tbody = document.getElementById('activityLogTableBody');
            if (!tbody) return;
            try {
                const res = await fetch('api/get_activity_log.php');
                const json = await res.json();
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="5" class="text-center">No activity log entries found.</td></tr>';
                    return;
                }
                json.data.forEach(log => {
                    const tr = document.createElement('tr');
                    
                    let detailsStr = '';
                    if (typeof log.event_data === 'object' && log.event_data !== null) {
                        detailsStr = Object.entries(log.event_data)
                            .map(([k, v]) => `<strong>${k}:</strong> ${typeof v === 'object' ? JSON.stringify(v) : v}`)
                            .join(' | ');
                    } else {
                        detailsStr = log.event_data || '';
                    }

                    tr.innerHTML = `
                        <td style="font-family:monospace; font-size:0.85rem; white-space:nowrap;">${formatDate(log.created_at)}</td>
                        <td><strong>${log.performed_by}</strong></td>
                        <td><span style="font-weight:600; text-transform:uppercase; font-size:0.8rem; background:#f1f5f9; padding:2px 6px; border-radius:4px;">${log.event_type}</span></td>
                        <td><strong style="color:var(--primary-dark);">${log.service_id}</strong></td>
                        <td style="font-size:0.9rem; color:#475569;">${detailsStr}</td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger">Failed to load activity logs.</td></tr>';
            }
        }

        async function loadMyAmcAssignments() {
            const tbody = document.getElementById('myAmcVisitsTableBody');
            if (!tbody) return;
            try {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:20px; color:#6c757d;">Loading your AMC visits...</td></tr>';
                const res = await fetch('api/amc_visits_api.php?action=list&mine_only=1');
                const json = await res.json();
                tbody.innerHTML = '';
                if (!json.data || json.data.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="7" class="text-center" style="padding:30px; color:#6c757d;">No AMC visits currently assigned to you.</td></tr>';
                    return;
                }
                json.data.forEach(v => {
                    const tr = document.createElement('tr');
                    const statusBadge = window.AMC ? window.AMC.getStatusBadgeClass(v.status) : 'badge';
                    tr.innerHTML = `
                        <td><strong style="color:var(--primary-dark);">${v.amc_number}</strong></td>
                        <td><strong>Visit #${v.visit_number}</strong></td>
                        <td>
                            <div style="font-weight:700;">${v.customer_name} ${v.company_name ? '(' + v.company_name + ')' : ''}</div>
                            <div style="font-size:0.85rem; color:#64748b;">📍 ${v.customer_address}</div>
                            <div style="font-size:0.85rem; color:#3b82f6;">📞 ${v.customer_phone}</div>
                        </td>
                        <td style="font-size:0.9rem;">${v.products_covered || 'N/A'}</td>
                        <td><strong>${v.scheduled_date}</strong></td>
                        <td><span class="${statusBadge}">${v.status}</span></td>
                        <td>
                            <button class="btn btn-sm btn-primary" onclick="window.AMC.openVisitModal(${v.id})" style="padding:6px 14px; font-weight:600;">Start / Process Visit</button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            } catch (e) {
                tbody.innerHTML = '<tr><td colspan="7" class="text-center text-danger">Failed to load AMC assignments.</td></tr>';
            }
        }
    </script>

    <!-- AMC VISIT WORKFLOW MODAL -->
    <div id="amc-visit-modal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; right:0; bottom:0; background:rgba(15,23,42,0.7); z-index:99999; justify-content:center; align-items:center; overflow-y:auto; padding:20px;">
        <div style="background:#fff; width:100%; max-width:900px; max-height:90vh; border-radius:16px; box-shadow:0 20px 25px -5px rgba(0,0,0,0.2); overflow-y:auto; padding:30px; position:relative; margin:auto;">
            <button onclick="window.AMC.closeVisitModal()" style="position:absolute; top:15px; right:20px; background:none; border:none; font-size:2rem; font-weight:700; cursor:pointer; color:#64748b; line-height:1;">&times;</button>
            <div id="amc-visit-modal-content"></div>
        </div>
    </div>

    <!-- AMC Workflow Engine JS -->
    <script src="assets/js/amc-workflow.js?v=1.0"></script>
</body>

</html>
