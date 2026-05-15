<?php include __DIR__ . '/auth_guard.php'; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Your Service - Infinity Computer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- Google reCAPTCHA -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <style>
        /* Modern Package Stepper */
        .track-stepper {
            display: flex;
            justify-content: space-between;
            position: relative;
            margin: 40px 0 50px;
        }

        .track-stepper::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 10%;
            width: 80%;
            height: 4px;
            background: #e2e8f0;
            z-index: 1;
        }

        .track-stepper-progress {
            position: absolute;
            top: 25px;
            left: 10%;
            height: 4px;
            background: var(--primary-color);
            z-index: 1;
            transition: width 0.5s ease;
        }

        .step {
            position: relative;
            z-index: 2;
            text-align: center;
            flex: 1;
        }

        .step-icon {
            width: 50px;
            height: 50px;
            margin: 0 auto 10px;
            background: #fff;
            border: 4px solid #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            transition: all 0.3s ease;
            color: #94a3b8;
        }

        .step p {
            font-size: 0.85rem;
            font-weight: 600;
            color: #64748b;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .step.done .step-icon {
            border-color: var(--primary-color);
            background: var(--primary-color);
            color: #fff;
        }

        .step.active .step-icon {
            border-color: var(--primary-color);
            background: #fff;
            color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(31, 95, 174, 0.2);
        }

        .step.done p,
        .step.active p {
            color: var(--text-dark);
            font-weight: 700;
        }

        .step.cancelled-step .step-icon {
            border-color: var(--danger);
            background: var(--danger);
            color: #fff;
        }

        .step.cancelled-step p {
            color: var(--danger);
        }

        .timeline {
            list-style: none;
            padding: 0;
            position: relative;
            margin-top: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            top: 0;
            bottom: 0;
            left: 24px;
            width: 2px;
            background: #e2e8f0;
        }

        .timeline li {
            position: relative;
            margin-bottom: 20px;
            padding-left: 60px;
        }

        .timeline-bullet {
            position: absolute;
            left: 16px;
            top: 4px;
            width: 18px;
            height: 18px;
            border-radius: 50%;
            background: #e2e8f0;
            border: 3px solid #fff;
            box-shadow: 0 0 0 1px #cbd5e1;
            z-index: 2;
        }

        .timeline li:first-child .timeline-bullet {
            background: var(--primary-color);
            box-shadow: 0 0 0 2px rgba(31, 95, 174, 0.3);
            width: 20px;
            height: 20px;
            left: 15px;
            top: 2px;
        }

        .timeline-content {
            background: var(--bg-light);
            border-radius: 12px;
            padding: 15px 20px;
            border: 1px solid var(--border-color);
        }

        .timeline-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 5px;
            flex-wrap: wrap;
            gap: 10px;
        }

        .timeline-title {
            font-weight: 600;
            font-size: 1.05rem;
            margin: 0;
            color: var(--text-dark);
        }

        .timeline-date {
            font-size: 0.85rem;
            color: #64748b;
            font-weight: 500;
        }

        .timeline-text {
            margin: 0;
            font-size: 0.95rem;
            color: #475569;
        }

        .service-accordion {
            background: #fff;
            border: 1px solid var(--border-color);
            border-radius: 12px;
            margin-bottom: 15px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.02);
        }

        .service-header {
            padding: 20px;
            cursor: pointer;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #fafbfc;
            border-bottom: 1px solid transparent;
            transition: background 0.3s;
        }

        .service-header:hover {
            background: #f1f5f9;
        }

        .service-header.open {
            border-bottom-color: var(--border-color);
            background: #fff;
        }

        .service-body {
            padding: 30px 20px;
            display: none;
        }

        .service-body.open {
            display: block;
        }

        @media (max-width: 768px) {
            .track-stepper {
                flex-direction: column;
                align-items: flex-start;
                gap: 30px;
                margin-left: 20px;
            }

            .track-stepper::before {
                top: 0;
                bottom: 0;
                left: 25px;
                width: 2px;
                height: auto;
            }

            .track-stepper-progress {
                top: 0;
                left: 25px;
                width: 2px;
                height: var(--prog-height, 0%);
            }

            .step {
                display: flex;
                align-items: center;
                gap: 20px;
                text-align: left;
                width: 100%;
            }

            .step-icon {
                margin: 0;
            }
        }

        /* Tab System Styles */
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

        .mt-4 {
            margin-top: 1.5rem;
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
                        style="font-size: 0.65rem; color: #fb2a71; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Service
                        Panel</span>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="javascript:void(0)" id="headerTrack" onclick="switchTab('track-service-tab')"
                        class="header-active">Track Service</a></li>
                <li><a href="javascript:void(0)" id="headerNewService" onclick="switchTab('new-service-tab')">Add New Service</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if (in_array($_SESSION['staff_email'] ?? '', ['suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in'])): ?>
                <li><a href="crm.php">CRM Analytics</a></li>
                <?php endif; ?>
                <li><a href="logout.php" style="color: #dc3545; font-weight: 600; border: 1px solid #dc3545; border-radius: 5px; padding: 5px 12px; margin-left: 10px; text-decoration: none;">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <!-- 1. TRACK SERVICE TAB -->
        <div id="track-service-tab" class="tab-pane active">
            <div class="search-section">
                <h2>Track Your Service Status</h2>
                <p>Enter your Service ID or Mobile Number to check all your device repairs.</p>
                <div class="search-bar mt-4"
                    style="max-width: 600px; margin: 0 auto; box-shadow: 0 4px 20px rgba(0,0,0,0.05); border-radius: 50px;">
                    <input type="text" id="searchInput" class="form-control"
                        placeholder="e.g. INF-2026-001 or 9876543210"
                        onkeypress="if(event.key === 'Enter') searchService()"
                        style="border-radius:50px 0 0 50px; padding:18px 25px; border-right:0;">
                    <button class="btn btn-primary" onclick="searchService()"
                        style="border-radius:0 50px 50px 0; padding: 18px 35px;">Track Orders</button>
                </div>
                <div id="loading" class="mt-4 hidden" style="font-weight:600; color:var(--primary-color);">Checking
                    systems...</div>
                <div id="error" class="mt-4 hidden"
                    style="color: var(--danger); font-weight:600; background: #fee2e2; padding: 15px; border-radius: 8px; max-width: 600px; margin: 20px auto 0;">
                </div>
            </div>

            <div id="resultsArea" class="hidden" style="max-width: 850px; margin: 0 auto;"></div>
        </div>

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
            const headerTrack = document.getElementById('headerTrack');
            const headerNewService = document.getElementById('headerNewService');

            if (headerTrack && headerNewService) {
                headerTrack.classList.remove('header-active');
                headerNewService.classList.remove('header-active');

                if (id === 'track-service-tab') {
                    headerTrack.classList.add('header-active');
                } else if (id === 'new-service-tab') {
                    headerNewService.classList.add('header-active');
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
                    setTimeout(() => { msg.innerHTML = ''; switchTab('track-service-tab'); }, 5000);
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

        async function searchService() {
            const query = document.getElementById('searchInput').value.trim();
            if (!query) return;
            document.getElementById('loading').classList.remove('hidden');
            document.getElementById('error').classList.add('hidden');
            document.getElementById('resultsArea').classList.add('hidden');
            try {
                const res = await fetch(`api/search_service.php?q=${encodeURIComponent(query)}`);
                const json = await res.json();
                document.getElementById('loading').classList.add('hidden');
                if (json.status === 'success' && json.data.length > 0) { renderResults(json.data); }
                else { document.getElementById('error').innerText = 'No service records found. Please check your ID or Phone Number.'; document.getElementById('error').classList.remove('hidden'); }
            } catch (e) { document.getElementById('loading').classList.add('hidden'); document.getElementById('error').innerText = 'An error occurred while communicating with the server.'; document.getElementById('error').classList.remove('hidden'); }
        }

        function getStepData(status) {
            const s = status.toLowerCase();
            if (s === 'cancelled') return { currentStep: -1 };
            if (s === 'pending' || s === 'accepted') return { currentStep: 0, progress: '0%' };
            if (s === 'diagnosing') return { currentStep: 1, progress: '25%' };
            if (s === 'repair in progress' || s === 'waiting for parts') return { currentStep: 2, progress: '50%' };
            if (s === 'completed' || s === 'ready for pickup') return { currentStep: 3, progress: '75%' };
            if (s === 'delivered') return { currentStep: 4, progress: '100%' };
            return { currentStep: 0, progress: '0%' };
        }

        function toggleAccordion(id) {
            const body = document.getElementById('body-' + id);
            const header = document.getElementById('header-' + id);
            if (body.classList.contains('open')) { body.classList.remove('open'); header.classList.remove('open'); }
            else { body.classList.add('open'); header.classList.add('open'); }
        }

        function renderResults(services) {
            const container = document.getElementById('resultsArea');
            container.innerHTML = '';
            container.classList.remove('hidden');
            const isMulti = services.length > 1;
            if (isMulti) { const h = document.createElement('h3'); h.style.cssText = 'margin-bottom:25px; text-align:center; color:var(--primary-dark);'; h.innerText = `Service History (${services.length} records found)`; container.appendChild(h); }

            services.forEach((svc, index) => {
                const wrap = document.createElement('div');
                wrap.className = isMulti ? 'service-accordion' : 'card';
                if (!isMulti) wrap.style.marginBottom = '40px';
                const { currentStep, progress } = getStepData(svc.status);
                const isCancelled = currentStep === -1;
                const isOpen = !isMulti || index === 0;
                const source = svc.source_type || 'engineering';
                let typeLabel = source === 'engineering' ? 'Shop Service' : (source === 'web_request' ? 'Web Inquiry' : 'Home Service');
                let deviceDisplayName = source === 'web_request' ? `${svc.brand} ${svc.model} (${svc.device_type})` : (source === 'home' ? (svc.service_type || 'Home Visit') : (svc.device_name || ''));
                const sourceBadge = `<span style="font-size: 0.7rem; background: #e2e8f0; color: #475569; padding: 2px 8px; border-radius: 4px; font-weight: 700; text-transform: uppercase; margin-right: 10px;">${typeLabel}</span>`;

                let html = isMulti ? `<div class="service-header ${isOpen ? 'open' : ''}" id="header-${svc.id || svc.service_id}" onclick="toggleAccordion('${svc.id || svc.service_id}')"><div style="flex:1;"><div style="display:flex; align-items:center; gap:15px; margin-bottom:5px; flex-wrap: wrap;">${sourceBadge}<strong style="font-size:1.1rem; color:var(--primary-dark);">${svc.service_id}</strong><span class="${getStatusBadgeClass(svc.status)}">${svc.status}</span></div><div style="color:var(--muted); font-size:0.95rem;">${deviceDisplayName} - ${formatDate(svc.date_received || svc.created_at)}</div></div><div style="color:var(--primary-color); font-weight:600; font-size:1.2rem;">▼</div></div><div class="service-body ${isOpen ? 'open' : ''}" id="body-${svc.id || svc.service_id}">` : `<div class="card-title" style="display:flex; justify-content:space-between; align-items:center; flex-wrap: wrap; gap: 10px;"><span>${sourceBadge} Ticket: <strong style="color:var(--text-dark);">${svc.service_id}</strong></span><span class="${getStatusBadgeClass(svc.status)}">${svc.status}</span></div>`;

                if (source === 'engineering') {
                    if (isCancelled) { html += `<div class="track-stepper" style="justify-content:center;"><div class="step cancelled-step"><div class="step-icon">❌</div><p>Service Cancelled</p></div></div>`; }
                    else { html += `<div class="track-stepper" style="--prog-height: ${progress};"><div class="track-stepper-progress" style="width: ${window.innerWidth > 768 ? progress : '2px'};"></div><div class="step ${currentStep >= 0 ? 'done' : ''} ${currentStep === 0 ? 'active' : ''}"><div class="step-icon">📦</div><p>Received</p></div><div class="step ${currentStep >= 1 ? 'done' : ''} ${currentStep === 1 ? 'active' : ''}"><div class="step-icon">🔍</div><p>Diagnosing</p></div><div class="step ${currentStep >= 2 ? 'done' : ''} ${currentStep === 2 ? 'active' : ''}"><div class="step-icon">🔧</div><p>Repairing</p></div><div class="step ${currentStep >= 3 ? 'done' : ''} ${currentStep === 3 ? 'active' : ''}"><div class="step-icon">✅</div><p>Ready</p></div><div class="step ${currentStep >= 4 ? 'done' : ''} ${currentStep === 4 ? 'active' : ''}"><div class="step-icon">🎉</div><p>Delivered</p></div></div>`; }
                }

                html += `<div style="border-top: 1px solid var(--border-color); padding-top: 25px; margin-top: 10px;"><h4 style="margin-bottom:15px; color:var(--muted); font-size:0.9rem; text-transform:uppercase;">Details & Information</h4><div class="info-grid"><div class="info-item"><label>Customer Details</label><div style="font-weight:600; font-size:1.1rem; color:var(--text-dark);">${svc.name}</div><div class="text-muted" style="font-size:0.9rem;">${svc.phone} ${svc.email ? ' | ' + svc.email : ''}</div></div><div class="info-item"><label>Device / Service</label><div style="font-weight:600; color:var(--text-dark);">${deviceDisplayName}</div><div class="text-muted" style="font-size:0.85rem;">${svc.service_type || (source === 'home' ? 'Home Service' : 'Standard')}</div></div></div>`;
                if (svc.assigned_engineer) { html += `<div style="margin-top:15px; background:#f0f9ff; padding:10px 15px; border-radius:8px; border:1px solid #bae6fd;"><label style="color:#0369a1; font-weight:700; font-size:0.75rem; text-transform:uppercase;">Assigned Engineer</label><div style="font-weight:700; color:#0c4a6e; font-size:1.1rem;">🔧 ${svc.assigned_engineer}</div></div>`; }
                if (source === 'home') { html += `<div class="info-grid" style="margin-top:15px;"><div class="info-item"><label>Schedule</label><div style="font-weight:600; color:var(--text-dark);">${svc.booking_date} at ${svc.time_slot}</div></div><div class="info-item"><label>Address</label><div style="font-weight:600; color:var(--text-dark);">${svc.address}</div></div></div>`; }
                html += `<div class="info-item" style="margin-top: 15px; background: #fff; border: 1px solid var(--border-color);"><label>Reported Problem / Inquiry</label><div style="color:var(--text-dark);">${svc.problem || 'General Service Inquiry'}</div></div></div>`;
                if (svc.image_path) { html += `<div class="mt-4"><label style="font-weight:600; color:var(--muted); font-size:0.85rem; text-transform:uppercase;">Attached Image</label><br><img src="../${svc.image_path}" class="device-image-preview" style="max-height:250px; border-radius:10px; margin-top:5px; box-shadow: 0 4px 10px rgba(0,0,0,0.1);" alt="Device"></div>`; }
                if (svc.logs && svc.logs.length > 0) { html += `<div style="margin-top: 30px; padding-top: 10px;"><h4 style="margin-bottom:0; color:var(--muted); font-size:0.9rem; text-transform:uppercase;">Detailed Activity Log</h4><ul class="timeline">`; svc.logs.forEach(log => { html += `<li><div class="timeline-bullet"></div><div class="timeline-content"><div class="timeline-meta"><h5 class="timeline-title">${log.status}</h5><span class="timeline-date">${formatDate(log.updated_at)}</span></div>${log.remarks ? `<p class="timeline-text">${log.remarks}</p>` : ''}</div></li>`; }); html += `</ul></div>`; }
                if (isMulti) html += `</div>`;
                wrap.innerHTML = html;
                container.appendChild(wrap);
            });
        }
    </script>
</body>

</html>