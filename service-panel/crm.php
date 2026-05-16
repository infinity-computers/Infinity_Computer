<?php include __DIR__ . '/auth_guard.php'; ?>
<?php
$allowed_crm_emails = ['suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in'];
if (!in_array($_SESSION['staff_email'] ?? '', $allowed_crm_emails)) {
    header('Location: dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CRM Analytics - Infinity Computer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/crm.css">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
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
                <img src="../images/logos/infinity_computer_logo.png" alt="Infinity Computer Logo" style="height: 38px; width: auto;">
                <div style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1;">
                    <span class="brand-text">Infinity<span class="text-accent">Computer</span></span>
                    <span style="font-size: 0.65rem; color: #fb2a71; font-weight: 700; text-transform: uppercase;">Service Panel</span>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Track Service</a></li>
                <li><a href="javascript:void(0)" id="headerNewService" onclick="switchTab('new-service-tab')">Add New Service</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if (in_array($_SESSION['staff_email'] ?? '', ['suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in'])): ?>
                <li><a href="javascript:void(0)" id="headerCrm" onclick="switchTab('crm-analytics-tab')" class="header-active">CRM Analytics</a></li>
                <?php endif; ?>
                <li><a href="logout.php" style="color: #dc3545; font-weight: 600; border: 1px solid #dc3545; border-radius: 5px; padding: 5px 12px; margin-left: 10px; text-decoration: none;">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <!-- 1. CRM ANALYTICS TAB -->
        <div id="crm-analytics-tab" class="tab-pane active">
        <!-- Header with controls -->
        <div class="crm-header">
            <div>
                <h2>📊 CRM Analytics Dashboard</h2>
                <span class="refresh-badge" id="lastRefresh">Loading...</span>
            </div>
            <div class="crm-controls">
                <select id="rangeSelect">
                    <option value="7">Last 7 Days</option>
                    <option value="30" selected>Last 30 Days</option>
                    <option value="90">Last 90 Days</option>
                    <option value="365">Last 1 Year</option>
                </select>
                <button class="btn-export" style="background:#6366f1" onclick="window.print()">🖨 Print Report</button>
                <button class="btn-export" id="exportBtn">⬇ Export CSV</button>
            </div>
        </div>

        <!-- 1. Overview Cards -->
        <div class="overview-grid" id="overviewGrid">
            <div class="overview-card skeleton skeleton-card"></div>
            <div class="overview-card skeleton skeleton-card"></div>
            <div class="overview-card skeleton skeleton-card"></div>
            <div class="overview-card skeleton skeleton-card"></div>
            <div class="overview-card skeleton skeleton-card"></div>
            <div class="overview-card skeleton skeleton-card"></div>
        </div>

        <!-- 2. Charts -->
        <div class="charts-grid" id="chartsGrid">
            <div class="chart-card skeleton skeleton-chart"></div>
            <div class="chart-card skeleton skeleton-chart"></div>
            <div class="chart-card skeleton skeleton-chart"></div>
            <div class="chart-card skeleton skeleton-chart"></div>
        </div>

        <!-- 3. Advanced Insights Grid -->
        <div class="charts-grid" style="margin-top: 30px;">
            <!-- Engineer Performance -->
            <div class="chart-card" style="grid-column: span 2;">
                <h3>👨‍🔧 Engineer Performance Overview</h3>
                <div id="engineerStats">
                    <p style="text-align:center; color:#64748b; padding:20px;">Loading engineer stats...</p>
                </div>
            </div>

            <!-- Work Assignment Matrix -->
            <div class="chart-card" style="grid-column: span 2;">
                <h3>📋 Current Work Assignment Matrix</h3>
                <div id="workAssignments">
                    <p style="text-align:center; color:#64748b; padding:20px;">Loading assignments...</p>
                </div>
            </div>

            <!-- Unassigned Tasks -->
            <div class="chart-card" style="grid-column: span 2; border: 2px dashed #f87171; background: #fffafb;">
                <h3 style="color:#ef4444">⚠️ Unassigned Tasks (Action Required)</h3>
                <div id="unassignedTasks">
                    <p style="text-align:center; color:#64748b; padding:20px;">Checking for unassigned work...</p>
                </div>
            </div>

            <!-- Monthly Comparison -->
            <div class="chart-card">
                <h3>📅 Monthly Comparison</h3>
                <div id="monthlyComparison">
                    <p style="text-align:center; color:#64748b; padding:20px;">Loading comparison...</p>
                </div>
            </div>
            <!-- Repeat Customers -->
            <div class="chart-card">
                <h3>🔁 Top Repeat Customers</h3>
                <div id="repeatCustomersList">
                    <p style="text-align:center; color:#64748b; padding:20px;">Loading customers...</p>
                </div>
            </div>
        </div>

        <!-- 4. SLA Alerts -->
        <div class="recent-section" id="slaAlertsSection" style="display:none;">
            <h3 style="color:#ef4444">⚠️ SLA Alerts (Stuck > 3 Days)</h3>
            <div id="slaTable"></div>
        </div>

        <!-- 5. Advanced Insights -->
        <h3 style="font-size:1.15rem; font-weight:700; color:var(--primary-dark); margin-bottom:15px;">🎯 Performance Metrics</h3>
        <div class="insights-grid" id="insightsGrid">
            <div class="insight-card skeleton" style="height:100px"></div>
            <div class="insight-card skeleton" style="height:100px"></div>
            <div class="insight-card skeleton" style="height:100px"></div>
            <div class="insight-card skeleton" style="height:100px"></div>
        </div>

        <!-- 6. Recent Table -->
        <div class="recent-section">
            <h3>📋 Recent Service Requests</h3>
            <div id="recentTable">
                <p style="text-align:center; color:#64748b; padding:30px;">Loading recent services...</p>
            </div>
        </div>
        </div> <!-- End of crm-analytics-tab -->

        <!-- 2. NEW SERVICE TAB -->
        <div id="new-service-tab" class="tab-pane">
            <div class="card" style="max-width: 900px; margin: 40px auto; padding: 40px;">
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
    </div>

    <script src="assets/js/image-processor.js?v=1.4"></script>
    <script src="assets/js/main.js"></script>
    <script src="assets/js/crm.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            // Init Image Processor
            if (typeof ImageProcessor !== 'undefined') {
                ImageProcessor.setupPreview('.image-input', '#imagePreview', false);
                ImageProcessor.initCameraVisibility('.camera-btn');
            }
        });

        function switchTab(id) {
            document.querySelectorAll('.tab-pane').forEach(pane => pane.classList.remove('active'));
            document.getElementById(id).classList.add('active');

            // Sync Header Links UI
            const headerNewService = document.getElementById('headerNewService');
            const headerCrm = document.getElementById('headerCrm');

            if (headerNewService && headerCrm) {
                headerNewService.classList.remove('header-active');
                headerCrm.classList.remove('header-active');

                if (id === 'new-service-tab') {
                    headerNewService.classList.add('header-active');
                } else if (id === 'crm-analytics-tab') {
                    headerCrm.classList.add('header-active');
                }
            }
        }

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
                    setTimeout(() => { msg.innerHTML = ''; switchTab('crm-analytics-tab'); }, 5000);
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
    </script>
</body>
</html>
