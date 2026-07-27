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


        .mt-4 {
            margin-top: 1.5rem;
        }
    </style>
<style>
/* Premium Modal Styles */
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
    max-width: 550px;
    width: 100%;
    box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
    position: relative;
    max-height: 90vh;
    overflow-y: auto;
    animation: modalSlideUp 0.3s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes modalSlideUp {
    from {
        transform: translateY(20px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
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
    transition: color 0.2s;
}
.modal-close:hover {
    color: #64748b;
}
.modal-footer {
    display: flex;
    justify-content: flex-end;
    gap: 10px;
    border-top: 1px solid #e2e8f0;
    padding-top: 15px;
    margin-top: 20px;
}
.vertical-timeline {
    position: relative;
    padding-left: 30px;
    margin: 15px 0;
}
.vertical-timeline::before {
    content: '';
    position: absolute;
    top: 5px;
    bottom: 5px;
    left: 9px;
    width: 2px;
    background: #e2e8f0;
}
.timeline-item {
    position: relative;
    margin-bottom: 20px;
}
.timeline-dot {
    position: absolute;
    left: -26px;
    top: 4px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #3b82f6;
    border: 2px solid #fff;
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
}
.timeline-date-str {
    font-size: 0.75rem;
    color: #64748b;
    margin-bottom: 4px;
}
.timeline-title-str {
    font-weight: 600;
    font-size: 0.95rem;
    color: var(--text-dark);
    margin-bottom: 4px;
}
.timeline-desc-str {
    font-size: 0.85rem;
    color: #475569;
}
</style>
</head>

<body>
<?php $activeNav = 'track'; include __DIR__ . '/partials/nav.php'; ?>

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
                            <select name="service_type" id="service_type" class="form-control" required>
                                <option value="">Select Type...</option>
                                <option value="Laptop Repair">Laptop Repair</option>
                                <option value="Mobile Repair">Mobile Repair</option>
                                <option value="PC Assembly">PC Assembly</option>
                                <option value="Printer Service">Printer Service</option>
                                <option value="CCTV Service">CCTV Service</option>
                                <option value="Network Setup">Network Setup</option>
                                <option value="Data Recovery">Data Recovery</option>
                                <option value="Display/Screen Repair">Display/Screen Repair</option>
                                <option value="PC repair">PC repair</option>
                                <option value="HDD repair">HDD repair</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        <div class="form-group" id="other_service_type_container" style="display: none;">
                            <label>Specify Other Service Type <span style="color:var(--danger)">*</span></label>
                            <input type="text" id="other_service_type" name="other_service_type" class="form-control" placeholder="Please specify service type...">
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
                                <?php foreach($engineersList as $eng): ?>
                                <option value="<?= htmlspecialchars($eng) ?>"><?= htmlspecialchars($eng) ?></option>
                                <?php endforeach; ?>
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
    </div>

    <script src="assets/js/image-processor.js?v=2.0"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const STAFF_ROLE = <?php echo json_encode(getStaffRole()); ?>;
        const STAFF_NAME = <?php echo json_encode(getStaffName()); ?>;
        const IS_ADMIN = <?php echo json_encode(isAdmin()); ?>;
        const IS_SUPER_ADMIN = <?php echo json_encode(isSuperAdmin()); ?>;

        document.addEventListener('DOMContentLoaded', () => {
            // Init Multi-Image Processor
            if (typeof ImageProcessor !== 'undefined') {
                window.lastProcessedBlobs = [];
                ImageProcessor.setupMultiPreview('.img-add-btn', '#imagePreview', false);
                ImageProcessor.initCameraVisibility('.camera-btn');
            }

            // Toggle other service type input
            const serviceTypeSelect = document.getElementById('service_type');
            const otherServiceTypeContainer = document.getElementById('other_service_type_container');
            const otherServiceTypeInput = document.getElementById('other_service_type');

            if (serviceTypeSelect) {
                serviceTypeSelect.addEventListener('change', function() {
                    if (this.value === 'Other') {
                        otherServiceTypeContainer.style.display = 'block';
                        otherServiceTypeInput.required = true;
                    } else {
                        otherServiceTypeContainer.style.display = 'none';
                        otherServiceTypeInput.required = false;
                        otherServiceTypeInput.value = '';
                    }
                });
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

            // Overwrite service_type with other_service_type if "Other" is selected
            if (formData.get('service_type') === 'Other') {
                const otherVal = formData.get('other_service_type') ? formData.get('other_service_type').trim() : '';
                if (!otherVal) {
                    const msg = document.getElementById('formMsg');
                    msg.innerHTML = `<span style="color:var(--danger)">Please specify the other service type.</span>`;
                    btn.disabled = false;
                    btn.innerText = 'Submit Request';
                    return;
                }
                formData.set('service_type', otherVal);
            }

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
                    const otherServiceTypeContainer = document.getElementById('other_service_type_container');
                    const otherServiceTypeInput = document.getElementById('other_service_type');
                    if (otherServiceTypeContainer) {
                        otherServiceTypeContainer.style.display = 'none';
                        otherServiceTypeInput.required = false;
                    }
                    document.getElementById('imagePreview').innerHTML = '';
                    if (window.grecaptcha) grecaptcha.reset();
                    document.getElementById('panelSubmitBtn').disabled = true;
                    window.lastProcessedBlobs = [];
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
            if (s === 'repair in progress' || s === 'waiting for parts' || s === 'waiting for customer approval' || s === 'waiting for customer reply' || s === 'customer call not answered' || s === 'waiting for pending estimate') return { currentStep: 2, progress: '50%' };
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
                const imgs = (svc.images && svc.images.length > 0) ? svc.images : (svc.image_path ? [svc.image_path] : []);
                if (imgs.length > 0) { html += `<div class="mt-4"><label style="font-weight:600; color:var(--muted); font-size:0.85rem; text-transform:uppercase;">Device Image${imgs.length > 1 ? 's' : ''}</label><div style="display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;">${imgs.map(p => `<img src="../${p}" class="device-image-preview" style="height:200px; width:auto; max-width:100%; border-radius:10px; box-shadow:0 4px 10px rgba(0,0,0,0.1); object-fit:cover; cursor:pointer;" onclick="window.open('../${p}','_blank')" alt="Device">`).join('')}</div></div>`; }
                if (svc.logs && svc.logs.length > 0) { html += `<div style="margin-top: 30px; padding-top: 10px;"><h4 style="margin-bottom:0; color:var(--muted); font-size:0.9rem; text-transform:uppercase;">Detailed Activity Log</h4><ul class="timeline">`; svc.logs.forEach(log => { html += `<li><div class="timeline-bullet"></div><div class="timeline-content"><div class="timeline-meta"><h5 class="timeline-title">${log.status}</h5><span class="timeline-date">${formatDate(log.updated_at)}</span></div>${log.remarks ? `<p class="timeline-text">${log.remarks}</p>` : ''}</div></li>`; }); html += `</ul></div>`; }
                if (isMulti) html += `</div>`;
                let btnHtml = '';
                if (source !== 'home') {
                    // 1. Log Call, Custody, Parts: visible to Admin, Super Admin, and Assigned Engineer
                    if (IS_ADMIN || svc.assigned_engineer === STAFF_NAME) {
                        btnHtml += `<button class="btn btn-sm btn-primary" onclick="openLogCallModal('${svc.service_id}')">Log Call</button>`;
                        btnHtml += `<button class="btn btn-sm btn-secondary" onclick="openCustodyTransferModal('${svc.service_id}')">Custody Transfer</button>`;
                        btnHtml += `<button class="btn btn-sm btn-info" onclick="openReplacedPartModal('${svc.service_id}')">Record Part Replaced</button>`;
                    }
                    
                    // 2. Submit Work: visible to Assigned Engineer only, when work is not closed/submitted
                    if (STAFF_ROLE === 'Engineer' && svc.assigned_engineer === STAFF_NAME && svc.status !== 'Closed' && svc.engineer_submitted != 1) {
                        btnHtml += `<button class="btn btn-sm btn-success" onclick="openSubmitWorkModal('${svc.service_id}')">Submit Work</button>`;
                    }
                    
                    // 3. Admin Verify: visible to Admin/Super Admin only, when engineer_submitted = 1 and not verified
                    if (IS_ADMIN && svc.engineer_submitted == 1 && !svc.verified_by_admin) {
                        btnHtml += `<button class="btn btn-sm btn-warning" onclick="openAdminVerifyModal('${svc.service_id}')">Admin Verify</button>`;
                    }
                    
                    // 4. Close Ticket: visible to Admin/Super Admin only, when verified_by_admin IS NOT NULL and not closed
                    if (IS_ADMIN && svc.verified_by_admin && svc.status !== 'Closed') {
                        btnHtml += `<button class="btn btn-sm btn-danger" onclick="openCloseTicketModal('${svc.service_id}')">Close Ticket</button>`;
                    }
                }
                
                // 5. View Timeline: visible to everyone
                btnHtml += `<button class="btn btn-sm btn-info" style="background:#0284c7; border-color:#0284c7; color:#fff;" onclick="openTimelineModal('${svc.service_id}')">View Timeline</button>`;

                wrap.innerHTML = html + `<div class="action-buttons" style="margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;">${btnHtml}</div>`;
                if (isMulti) wrap.innerHTML += `</div>`;
                container.appendChild(wrap);
            });
        }
    </script>

    <!-- Modals Section -->
    <!-- 1. Log Call Modal -->
    <div id="modal-log-call" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Log Call Attempt</h3>
                <button class="modal-close" onclick="closeModal('modal-log-call')">&times;</button>
            </div>
            <form id="form-log-call" onsubmit="submitLogCall(event)">
                <input type="hidden" name="service_id" id="log-call-svc-id">
                <div class="form-group">
                    <label>Call Status <span style="color:var(--danger)">*</span></label>
                    <select name="call_status" class="form-control" required>
                        <option value="">Select Call Status...</option>
                        <option value="Answered">Answered</option>
                        <option value="No Answer">No Answer</option>
                        <option value="Busy">Busy</option>
                        <option value="Switched Off">Switched Off</option>
                        <option value="Customer Requested Callback">Customer Requested Callback</option>
                        <option value="Customer Will Visit Office">Customer Will Visit Office</option>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label>Notes</label>
                    <textarea name="notes" class="form-control" rows="3" placeholder="Enter notes here..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-log-call')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Log</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Custody Transfer Modal -->
    <div id="modal-custody-transfer" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Record Custody Transfer</h3>
                <button class="modal-close" onclick="closeModal('modal-custody-transfer')">&times;</button>
            </div>
            <form id="form-custody-transfer" onsubmit="submitCustodyTransfer(event)">
                <input type="hidden" name="service_id" id="custody-svc-id">
                <div class="form-group">
                    <label>Transfer Type <span style="color:var(--danger)">*</span></label>
                    <select name="transfer_type" class="form-control" required>
                        <option value="">Select Transfer Type...</option>
                        <option value="Customer -> Engineer">Customer -> Engineer</option>
                        <option value="Engineer -> Workshop">Engineer -> Workshop</option>
                        <option value="Workshop -> Engineer">Workshop -> Engineer</option>
                        <option value="Engineer -> Customer">Engineer -> Customer</option>
                    </select>
                </div>
                <div class="form-group mt-3">
                    <label>From User</label>
                    <input type="text" name="from_user" class="form-control" id="custody-from-user" readonly>
                </div>
                <div class="form-group mt-3">
                    <label>To User / Recipient <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="to_user" class="form-control" placeholder="Recipient's Name (e.g. Workshop, Engineer, or Customer)" required>
                </div>
                <div class="form-group mt-3">
                    <label>Device Condition</label>
                    <textarea name="device_condition" class="form-control" rows="2" placeholder="Describe condition..."></textarea>
                </div>
                <div class="form-group mt-3">
                    <label>Remarks</label>
                    <textarea name="remarks" class="form-control" rows="2" placeholder="Transfer notes..."></textarea>
                </div>
                <div class="form-group mt-3">
                    <label>Upload Condition Photo <small>(Optional)</small></label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-custody-transfer')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Record Transfer</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 3. Record Part Replaced Modal -->
    <div id="modal-replaced-part" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Record Part Replaced</h3>
                <button class="modal-close" onclick="closeModal('modal-replaced-part')">&times;</button>
            </div>
            <form id="form-replaced-part" onsubmit="submitReplacedPart(event)">
                <input type="hidden" name="service_id" id="parts-svc-id">
                <div class="form-group">
                    <label>New Part Name <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="new_part_name" class="form-control" placeholder="e.g. Crucial 8GB DDR4 RAM" required>
                </div>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label>Old Part Name</label>
                        <input type="text" name="old_part_name" class="form-control" placeholder="e.g. Defective RAM">
                    </div>
                    <div class="form-group">
                        <label>Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="1" min="1">
                    </div>
                </div>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label>New Part Serial</label>
                        <input type="text" name="new_part_serial" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>Old Part Serial</label>
                        <input type="text" name="old_part_serial" class="form-control">
                    </div>
                </div>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label>Cost Price (₹)</label>
                        <input type="number" name="cost_price" class="form-control" step="0.01" value="0.00">
                    </div>
                    <div class="form-group">
                        <label>Selling Price (₹)</label>
                        <input type="number" name="selling_price" class="form-control" step="0.01" value="0.00">
                    </div>
                </div>
                <div class="form-group mt-3">
                    <label>Warranty Period</label>
                    <input type="text" name="warranty_period" class="form-control" placeholder="e.g. 3 Years Warranty">
                </div>
                <div class="form-group mt-3">
                    <label>Upload Part Photo <small>(Optional)</small></label>
                    <input type="file" name="image" class="form-control" accept="image/*">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-replaced-part')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Part</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 4. Submit Work Modal -->
    <div id="modal-submit-work" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Submit Work Details</h3>
                <button class="modal-close" onclick="closeModal('modal-submit-work')">&times;</button>
            </div>
            <form id="form-submit-work" onsubmit="submitWork(event)">
                <input type="hidden" name="service_id" id="submit-work-svc-id">
                <div class="form-group">
                    <label>Work Done Summary <span style="color:var(--danger)">*</span></label>
                    <textarea name="remarks" class="form-control" rows="4" placeholder="Describe resolution..." required></textarea>
                </div>
                <div class="form-group mt-3">
                    <label>Upload Photo Proofs <span style="color:var(--danger)">*</span> <small>(Up to 5 images)</small></label>
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple required>
                </div>
                <div class="form-group mt-3">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" required style="width: 18px; height: 18px; accent-color: var(--primary-color);">
                        <span>I confirm work is complete</span>
                    </label>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-submit-work')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Submit Work</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 5. Admin Verify Modal -->
    <div id="modal-admin-verify" class="modal-overlay">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title">Admin Verification &amp; Approval</h3>
                <button class="modal-close" onclick="closeModal('modal-admin-verify')">&times;</button>
            </div>
            <form id="form-admin-verify" onsubmit="submitAdminVerify(event)">
                <input type="hidden" name="service_id" id="verify-svc-id">
                <div class="form-group">
                    <label>Verification Action <span style="color:var(--danger)">*</span></label>
                    <select name="action" class="form-control" id="verify-action" required onchange="toggleVerifyFields(this.value)">
                        <option value="Approve">Approve &amp; Ready for Pickup</option>
                        <option value="Return">Return to Engineer for Revisions</option>
                        <option value="Close">Verify, Mark Paid &amp; Close Ticket</option>
                    </select>
                </div>
                <div id="verify-billing-fields">
                    <div class="form-grid mt-3">
                        <div class="form-group">
                            <label>Billing Status</label>
                            <select name="billing_status" class="form-control">
                                <option value="Billing Pending">Billing Pending</option>
                                <option value="Invoice Generated">Invoice Generated</option>
                                <option value="Payment Pending">Payment Pending</option>
                                <option value="Payment Received" selected>Payment Received</option>
                                <option value="Cash Collected">Cash Collected</option>
                                <option value="Credit Customer">Credit Customer</option>
                                <option value="AMC">AMC</option>
                                <option value="Warranty">Warranty</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label>Payment Mode</label>
                            <select name="payment_mode" class="form-control">
                                <option value="UPI">UPI</option>
                                <option value="Cash">Cash</option>
                                <option value="Card">Card</option>
                                <option value="Credit">Credit</option>
                                <option value="Cheque">Cheque</option>
                            </select>
                        </div>
                    </div>
                    <div class="form-group mt-3">
                        <label>Invoice Number</label>
                        <input type="text" name="invoice_number" class="form-control">
                    </div>
                    <div class="form-grid mt-3">
                        <div class="form-group">
                            <label>Service Value (₹)</label>
                            <input type="number" name="service_value_rupees" class="form-control" step="0.01" value="0.00">
                        </div>
                        <div class="form-group">
                            <label>Sales Value (₹)</label>
                            <input type="number" name="sales_value_rupees" class="form-control" step="0.01" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="form-group mt-3">
                    <label id="verify-notes-label">Admin Notes / Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Enter remarks..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-admin-verify')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-primary">Process Action</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 6. Close Ticket Modal -->
    <div id="modal-close-ticket" class="modal-overlay">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title">Close Service Ticket</h3>
                <button class="modal-close" onclick="closeModal('modal-close-ticket')">&times;</button>
            </div>
            <form id="form-close-ticket" onsubmit="submitCloseTicket(event)">
                <input type="hidden" name="service_id" id="close-svc-id">
                <p>Are you sure you want to close this service ticket? This will mark the ticket as closed and return the assigned engineer to Active status.</p>
                <div class="form-group mt-3">
                    <label>Closing Remarks / Notes</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Closing remarks..."></textarea>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('modal-close-ticket')" style="background:#6c757d; color:#fff;">Cancel</button>
                    <button type="submit" class="btn btn-danger" style="background:#dc2626; border-color:#dc2626; color:#fff;">Close Ticket</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 7. View Timeline Modal -->
    <div id="modal-view-timeline" class="modal-overlay">
        <div class="modal" style="max-width: 600px;">
            <div class="modal-header">
                <h3 class="modal-title" id="timeline-title-display">Ticket Timeline</h3>
                <button class="modal-close" onclick="closeModal('modal-view-timeline')">&times;</button>
            </div>
            <div id="timeline-content-area" style="max-height: 60vh; overflow-y: auto;">
                <!-- Timeline items will load here -->
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeModal('modal-view-timeline')" style="background:#6c757d; color:#fff;">Close</button>
            </div>
        </div>
    </div>

    <!-- Modals Script Logic -->
    <script>
        function closeModal(id) {
            document.getElementById(id).classList.remove('active');
        }

        function openLogCallModal(serviceId) {
            document.getElementById('log-call-svc-id').value = serviceId;
            document.getElementById('modal-log-call').classList.add('active');
        }

        async function submitLogCall(e) {
            e.preventDefault();
            const form = e.target;
            const data = {
                service_id: form.elements['service_id'].value,
                call_status: form.elements['call_status'].value,
                notes: form.elements['notes'].value
            };
            try {
                const res = await fetch('api/log_call_attempt.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-log-call');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function openCustodyTransferModal(serviceId) {
            document.getElementById('custody-svc-id').value = serviceId;
            document.getElementById('custody-from-user').value = STAFF_NAME;
            document.getElementById('modal-custody-transfer').classList.add('active');
        }

        async function submitCustodyTransfer(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            try {
                const res = await fetch('api/record_custody_transfer.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-custody-transfer');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function openReplacedPartModal(serviceId) {
            document.getElementById('parts-svc-id').value = serviceId;
            document.getElementById('modal-replaced-part').classList.add('active');
        }

        async function submitReplacedPart(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            try {
                const res = await fetch('api/record_replaced_part.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-replaced-part');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function openSubmitWorkModal(serviceId) {
            document.getElementById('submit-work-svc-id').value = serviceId;
            document.getElementById('modal-submit-work').classList.add('active');
        }

        async function submitWork(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            try {
                const res = await fetch('api/engineer_submit_work.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-submit-work');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function openAdminVerifyModal(serviceId) {
            document.getElementById('verify-svc-id').value = serviceId;
            document.getElementById('modal-admin-verify').classList.add('active');
            toggleVerifyFields('Approve');
        }

        function toggleVerifyFields(action) {
            const billingFields = document.getElementById('verify-billing-fields');
            const notesLabel = document.getElementById('verify-notes-label');
            if (action === 'Return') {
                billingFields.style.display = 'none';
                notesLabel.innerHTML = 'Return Reason / Notes <span style="color:var(--danger)">*</span>';
                document.getElementById('form-admin-verify').querySelector('textarea').required = true;
            } else {
                billingFields.style.display = 'block';
                notesLabel.innerHTML = 'Admin Notes / Remarks';
                document.getElementById('form-admin-verify').querySelector('textarea').required = false;
            }
        }

        async function submitAdminVerify(e) {
            e.preventDefault();
            const form = e.target;
            const fd = new FormData(form);
            try {
                const res = await fetch('api/admin_verify_ticket.php', {
                    method: 'POST',
                    body: fd
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-admin-verify');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        function openCloseTicketModal(serviceId) {
            document.getElementById('close-svc-id').value = serviceId;
            document.getElementById('modal-close-ticket').classList.add('active');
        }

        async function submitCloseTicket(e) {
            e.preventDefault();
            const form = e.target;
            const data = {
                service_id: form.elements['service_id'].value,
                remarks: form.elements['remarks'].value
            };
            try {
                const res = await fetch('api/close_ticket.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const json = await res.json();
                if (json.status === 'success') {
                    alert(json.message);
                    closeModal('modal-close-ticket');
                    form.reset();
                    searchService();
                } else {
                    alert('Error: ' + json.message);
                }
            } catch (err) {
                alert('Request failed');
            }
        }

        async function openTimelineModal(serviceId) {
            document.getElementById('timeline-title-display').innerText = `Timeline for ${serviceId}`;
            const container = document.getElementById('timeline-content-area');
            container.innerHTML = '<p class="text-center" style="padding:20px;">Fetching timeline events...</p>';
            document.getElementById('modal-view-timeline').classList.add('active');
            
            try {
                const res = await fetch(`api/get_ticket_timeline.php?id=${encodeURIComponent(serviceId)}`);
                const json = await res.json();
                if (json.status === 'success' && json.data.length > 0) {
                    let html = '<div class="vertical-timeline">';
                    json.data.forEach(item => {
                        let detailHtml = '';
                        if (typeof item.details === 'object' && item.details !== null) {
                            detailHtml = Object.entries(item.details)
                                .map(([k,v]) => `<strong>${k}:</strong> ${v}`)
                                .join('<br>');
                        } else {
                            detailHtml = item.details || '';
                        }
                        if (item.extra) {
                            if (item.extra.to_user) detailHtml += `<br><strong>Recipient:</strong> ${item.extra.to_user}`;
                            if (item.extra.device_condition) detailHtml += `<br><strong>Condition:</strong> ${item.extra.device_condition}`;
                            if (item.extra.photo_path) detailHtml += `<br><a href="../${item.extra.photo_path}" target="_blank" style="color:var(--primary); font-weight:600;">View Attached Photo</a>`;
                        }
                        html += `
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-date-str">${formatDate(item.created_at)} by <strong>${item.performed_by}</strong></div>
                                <div class="timeline-title-str">${item.title} <small style="color:var(--primary); text-transform:uppercase; font-size:0.7rem; font-weight:700;">[${item.type}]</small></div>
                                <div class="timeline-desc-str">${detailHtml}</div>
                            </div>
                        `;
                    });
                    html += '</div>';
                    container.innerHTML = html;
                } else {
                    container.innerHTML = '<p class="text-center" style="padding:20px; color:#64748b;">No timeline records found for this ticket.</p>';
                }
            } catch (e) {
                container.innerHTML = '<p class="text-center text-danger" style="padding:20px;">Failed to load timeline records.</p>';
            }
        }
    </script>
</body>

</html>
