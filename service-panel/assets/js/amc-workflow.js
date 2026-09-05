/**
 * Infinity Computer - AMC Workflow & Management Engine
 * Step-by-step wizard navigation, direct camera photo capture, watermarking & completion
 */

window.AMC = {
    currentVisit: null,
    activeStep: 1,
    gpsCoords: { lat: '', lng: '' },
    capturedBlobs: {},

    init: function () {
        this.fetchGps();
    },

    fetchGps: function (callback) {
        if ("geolocation" in navigator) {
            navigator.geolocation.getCurrentPosition(
                function (pos) {
                    window.AMC.gpsCoords = {
                        lat: pos.coords.latitude.toFixed(6),
                        lng: pos.coords.longitude.toFixed(6)
                    };
                    if (callback) callback(window.AMC.gpsCoords);
                },
                function (err) {
                    window.AMC.gpsCoords = { lat: '', lng: '' };
                    if (callback) callback(window.AMC.gpsCoords);
                },
                { enableHighAccuracy: true, timeout: 10000, maximumAge: 0 }
            );
        } else {
            window.AMC.gpsCoords = { lat: '', lng: '' };
            if (callback) callback(window.AMC.gpsCoords);
        }
    },

    openVisitModal: async function (visitId) {
        const modal = document.getElementById('amc-visit-modal');
        if (!modal) return;

        modal.classList.add('active');
        const contentDiv = document.getElementById('amc-visit-modal-content');
        contentDiv.innerHTML = '<div style="padding:40px; text-align:center; font-weight:600; color:var(--amc-primary);">Loading AMC Visit details...</div>';

        this.fetchGps();

        try {
            const res = await fetch(`api/amc_visits_api.php?action=get_details&visit_id=${visitId}`);
            const json = await res.json();

            if (json.status !== 'success' || !json.data) {
                contentDiv.innerHTML = `<div class="alert alert-danger" style="padding:20px; color:#b91c1c; background:#fee2e2; border-radius:8px;">${json.message || 'Failed to load visit details.'}</div>`;
                return;
            }

            this.currentVisit = json.data;
            this.capturedBlobs = {};
            this.initDefaultStep(json.data.status);
            this.renderVisitWorkflow(json.data);
        } catch (e) {
            contentDiv.innerHTML = `<div class="alert alert-danger" style="padding:20px; color:#b91c1c; background:#fee2e2; border-radius:8px;">Network or database communication error.</div>`;
        }
    },

    closeVisitModal: function () {
        const modal = document.getElementById('amc-visit-modal');
        if (modal) modal.classList.remove('active');
        this.currentVisit = null;
        this.capturedBlobs = {};
    },

    initDefaultStep: function (status) {
        if (status === 'ASSIGNED') this.activeStep = 1;
        else if (status === 'ACCEPTED') this.activeStep = 2;
        else if (status === 'REACHED') this.activeStep = 3;
        else if (status === 'INSPECTION') this.activeStep = 3;
        else if (status === 'FOLLOW-UP REQUIRED') this.activeStep = 4;
        else if (status === 'COMPLETED') this.activeStep = 5;
        else this.activeStep = 3;
    },

    setWizardStep: function (stepNum) {
        if (!this.currentVisit) return;
        this.activeStep = stepNum;
        this.renderVisitWorkflow(this.currentVisit);
    },

    triggerCamera: function (inputId) {
        const fileInput = document.getElementById(inputId);
        if (!fileInput) return;

        const isMobile = /iPhone|iPad|iPod|Android/i.test(navigator.userAgent);
        if (!isMobile && typeof ImageProcessor !== 'undefined' && ImageProcessor.openCameraModal) {
            ImageProcessor.openCameraModal((blob) => {
                window.AMC.capturedBlobs[inputId] = blob;
                window.AMC.previewBlob(blob, inputId + '_preview');
            });
        } else {
            fileInput.click();
        }
    },

    handlePhotoSelect: async function (input, previewId) {
        if (!input.files || input.files.length === 0) return;
        const file = input.files[0];
        if (typeof ImageProcessor !== 'undefined' && ImageProcessor.process) {
            try {
                const blob = await ImageProcessor.process(file, "Infinity Computer");
                window.AMC.capturedBlobs[input.id] = blob;
                window.AMC.previewBlob(blob, previewId);
            } catch (e) {
                window.AMC.previewBlob(file, previewId);
            }
        } else {
            window.AMC.previewBlob(file, previewId);
        }
    },

    previewBlob: function (blobOrFile, previewId) {
        const container = document.getElementById(previewId);
        if (!container) return;
        const url = URL.createObjectURL(blobOrFile);
        const sizeKB = (blobOrFile.size / 1024).toFixed(1);
        container.innerHTML = `
            <div style="margin-top:10px; padding:12px; border:2px dashed #1f5fae; border-radius:10px; background:#f0f9ff; text-align:center;">
                <div style="font-size:0.8rem; font-weight:700; color:#1f5fae; margin-bottom:8px;">✓ Photo Ready for Upload (${sizeKB} KB)</div>
                <img src="${url}" style="max-height:160px; max-width:100%; border-radius:8px; box-shadow:0 4px 10px rgba(0,0,0,0.1); object-fit:cover;">
            </div>
        `;
    },

    renderVisitWorkflow: function (visit) {
        const contentDiv = document.getElementById('amc-visit-modal-content');
        const isCompleted = visit.status === 'COMPLETED';
        const status = visit.status;

        // Step completion statuses
        const step1Done = ['ACCEPTED', 'REACHED', 'INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step2Done = ['REACHED', 'INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step3Done = ['INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step4Done = ['COMPLETED'].includes(status);

        let html = `
            <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #e2e8f0; padding-bottom:15px; margin-bottom:20px; flex-wrap:wrap; gap:10px;">
                <div>
                    <h3 style="margin:0; font-size:1.3rem; color:var(--amc-primary-dark);">${visit.amc_number} - Visit #${visit.visit_number}</h3>
                    <div style="font-size:0.9rem; color:#64748b;">Scheduled: <strong>${visit.scheduled_date}</strong> | Engineer: <strong>${visit.assigned_engineer}</strong></div>
                </div>
                <div>
                    <span class="${this.getStatusBadgeClass(visit.status)}">${visit.status}</span>
                </div>
            </div>

            <!-- Customer & Location Header -->
            <div style="background:#f8fafc; border:1px solid #e2e8f0; border-radius:12px; padding:15px; margin-bottom:20px;">
                <div class="info-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">Customer Details</label>
                        <div style="font-weight:700; color:#0f172a; font-size:1.05rem;">${visit.customer_name} ${visit.company_name ? ' (' + visit.company_name + ')' : ''}</div>
                        <div style="font-size:0.9rem; color:#475569;">📞 <a href="tel:${visit.customer_phone}">${visit.customer_phone}</a> ${visit.customer_email ? ' | ' + visit.customer_email : ''}</div>
                    </div>
                    <div>
                        <label style="font-size:0.75rem; font-weight:700; color:#64748b; text-transform:uppercase;">Service Location</label>
                        <div style="font-size:0.95rem; color:#334155;">📍 ${visit.customer_address}</div>
                    </div>
                </div>
            </div>
        `;

        // ===== PREVIOUS MAINTENANCE HISTORY SECTION =====
        if (visit.previous_maintenance_history && visit.previous_maintenance_history.length > 0) {
            html += `
                <div class="prev-maint-card">
                    <div class="prev-maint-header" onclick="document.getElementById('prevMaintBody').classList.toggle('hidden')">
                        <span>📜 Previous Maintenance History (${visit.previous_maintenance_history.length} completed visit${visit.previous_maintenance_history.length > 1 ? 's' : ''})</span>
                        <span style="color:var(--amc-primary);">View Past Records ▼</span>
                    </div>
                    <div id="prevMaintBody" class="prev-maint-content hidden">
            `;

            visit.previous_maintenance_history.forEach(pVisit => {
                html += `
                    <div style="background:#fff; border:1px solid #cbd5e1; border-radius:8px; padding:12px; margin-bottom:12px;">
                        <div style="display:flex; justify-content:space-between; font-size:0.85rem; font-weight:700; color:#475569; margin-bottom:8px;">
                            <span>Visit #${pVisit.visit_number} - Completed on ${pVisit.completion_timestamp ? pVisit.completion_timestamp.split(' ')[0] : pVisit.scheduled_date}</span>
                            <span>Engineer: 🔧 ${pVisit.assigned_engineer}</span>
                        </div>
                        <div style="font-size:0.9rem; margin-bottom:6px;"><strong>Condition:</strong> ${pVisit.product_condition || 'Normal'} | <strong>Inspection:</strong> ${pVisit.inspection_result || 'N/A'}</div>
                        <div style="font-size:0.9rem; margin-bottom:6px;"><strong>Service Performed:</strong> ${pVisit.service_performed || 'Routine check'}</div>
                        ${pVisit.final_remark ? `<div style="font-size:0.85rem; color:#64748b;"><em>Remark: ${pVisit.final_remark}</em></div>` : ''}
                `;

                if (pVisit.issues && pVisit.issues.length > 0) {
                    html += `<div style="margin-top:8px; font-size:0.85rem; background:#fff7ed; border:1px solid #ffedd5; padding:8px; border-radius:6px;"><strong>Recorded Issues:</strong><ul>`;
                    pVisit.issues.forEach(iss => {
                        html += `<li><strong>${iss.product_name}</strong> - ${iss.issue_title}: ${iss.description} (${iss.status})</li>`;
                    });
                    html += `</ul></div>`;
                }

                if (pVisit.photos && pVisit.photos.length > 0) {
                    html += `<div style="display:flex; gap:8px; flex-wrap:wrap; margin-top:8px;">`;
                    pVisit.photos.forEach(ph => {
                        html += `<img src="../${ph.file_path}" style="height:70px; width:70px; border-radius:6px; object-fit:cover; cursor:pointer;" onclick="window.open('../${ph.file_path}','_blank')" title="${ph.photo_type}">`;
                    });
                    html += `</div>`;
                }

                html += `</div>`;
            });

            html += `</div></div>`;
        }

        // ===== STEP-BY-STEP WORKFLOW STEPPER =====
        html += `
            <div class="amc-stepper">
                <div class="amc-step-item ${step1Done ? 'done' : ''} ${this.activeStep === 1 ? 'active' : ''}" onclick="window.AMC.setWizardStep(1)" style="cursor:pointer;" title="Step 1: Accept">
                    <div class="amc-step-circle">1</div>
                    <div class="amc-step-label">Accept</div>
                </div>
                <div class="amc-step-item ${step2Done ? 'done' : ''} ${this.activeStep === 2 ? 'active' : ''}" onclick="window.AMC.setWizardStep(2)" style="cursor:pointer;" title="Step 2: Reached">
                    <div class="amc-step-circle">2</div>
                    <div class="amc-step-label">Reached</div>
                </div>
                <div class="amc-step-item ${step3Done ? 'done' : ''} ${this.activeStep === 3 ? 'active' : ''}" onclick="window.AMC.setWizardStep(3)" style="cursor:pointer;" title="Step 3: Inspection">
                    <div class="amc-step-circle">3</div>
                    <div class="amc-step-label">Inspection</div>
                </div>
                <div class="amc-step-item ${step4Done ? 'done' : ''} ${this.activeStep === 4 ? 'active' : ''}" onclick="window.AMC.setWizardStep(4)" style="cursor:pointer;" title="Step 4: Maintenance (Optional)">
                    <div class="amc-step-circle">4</div>
                    <div class="amc-step-label">Maintenance *</div>
                </div>
                <div class="amc-step-item ${isCompleted ? 'done' : ''} ${this.activeStep === 5 ? 'active' : ''}" onclick="window.AMC.setWizardStep(5)" style="cursor:pointer;" title="Step 5: Completion">
                    <div class="amc-step-circle">5</div>
                    <div class="amc-step-label">Complete</div>
                </div>
            </div>

            <!-- GPS Live Coordinates Banner -->
            <div class="gps-box" style="margin-bottom:20px;">
                <span>🌐 GPS Coordinates:</span>
                <span id="gpsDisplay">${this.gpsCoords.lat ? `${this.gpsCoords.lat}, ${this.gpsCoords.lng}` : 'Location unavailable'}</span>
                <button type="button" class="btn btn-sm btn-secondary" onclick="window.AMC.fetchGps(function(g){ document.getElementById('gpsDisplay').innerText = g.lat ? (g.lat + ', ' + g.lng) : 'Location unavailable'; })" style="padding:2px 8px; font-size:0.75rem; margin-left:auto;">Refresh GPS</button>
            </div>
        `;

        // ===== WIZARD STEP 1: ACCEPT ASSIGNMENT =====
        if (this.activeStep === 1) {
            html += `
                <div style="text-align:center; padding:30px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px;">
                    <h4 style="color:#0369a1; margin-bottom:10px;">Step 1 of 5: Accept AMC Assignment</h4>
                    <p style="color:#0c4a6e; font-size:0.95rem; margin-bottom:20px;">Please confirm acceptance of this AMC visit assignment to begin customer visit workflow.</p>
                    ${status === 'ASSIGNED' ? `
                        <button class="btn btn-primary" onclick="window.AMC.submitAccept(${visit.id})" style="padding:12px 30px; font-size:1.1rem;">Accept Assignment &amp; Proceed to Step 2 ➔</button>
                    ` : `
                        <div style="color:#0284c7; font-weight:700; margin-bottom:15px;">✓ Assignment Accepted</div>
                        <button type="button" class="btn btn-secondary" onclick="window.AMC.setWizardStep(2)">Proceed to Step 2 (Reach Location) ➔</button>
                    `}
                </div>
            `;
        }

        // ===== WIZARD STEP 2: REACH CUSTOMER LOCATION =====
        else if (this.activeStep === 2) {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:25px;">
                    <h4 style="color:var(--amc-primary-dark); margin-bottom:15px;">Step 2 of 5: Reach Customer Location</h4>
                    <form id="amcReachForm" onsubmit="window.AMC.submitReach(event, ${visit.id})">
                        <input type="hidden" name="latitude" value="${this.gpsCoords.lat}">
                        <input type="hidden" name="longitude" value="${this.gpsCoords.lng}">
                        
                        <div class="form-group mb-3">
                            <label style="font-weight:600; display:block; margin-bottom:8px;">Arrival Photograph <span style="color:var(--danger)">*</span></label>
                            
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                                <button type="button" class="btn btn-primary" onclick="window.AMC.triggerCamera('arrival_photo_input')" style="display:inline-flex; align-items:center; gap:6px; font-weight:600;">
                                    📷 Take Photo with Camera
                                </button>
                                <label class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin:0; font-weight:600;">
                                    📁 Select Photo
                                    <input type="file" id="arrival_photo_input" name="arrival_photo" accept="image/*" capture="environment" style="display:none;" onchange="window.AMC.handlePhotoSelect(this, 'arrival_preview')">
                                </label>
                            </div>
                            <div id="arrival_preview"></div>
                            <small class="text-muted">Takes photo with watermark "INFINITY COMPUTER", date, time &amp; GPS coordinates.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Arrival Remark <span style="color:var(--danger)">*</span></label>
                            <textarea name="arrival_remark" class="form-control" rows="2" required placeholder="e.g. Reached customer station at main office gate..."></textarea>
                        </div>

                        <div style="display:flex; justify-content:space-between; gap:10px; margin-top:20px;">
                            <button type="button" class="btn btn-secondary" onclick="window.AMC.setWizardStep(1)">⬅ Back to Step 1</button>
                            <button type="submit" class="btn btn-primary" style="padding:12px 24px; font-weight:700;">Submit Arrival &amp; Proceed to Step 3 ➔</button>
                        </div>
                    </form>
                </div>
            `;
        }

        // ===== WIZARD STEP 3: ROUTINE INSPECTION =====
        else if (this.activeStep === 3) {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:25px;">
                    <h4 style="color:var(--amc-primary-dark); margin-bottom:15px;">Step 3 of 5: Product Inspection &amp; Observations</h4>
                    <form id="amcInspectionForm" onsubmit="window.AMC.submitInspection(event, ${visit.id})">
                        <input type="hidden" name="latitude" value="${this.gpsCoords.lat}">
                        <input type="hidden" name="longitude" value="${this.gpsCoords.lng}">

                        <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label style="font-weight:600;">Product Condition <span style="color:var(--danger)">*</span></label>
                                <select name="product_condition" class="form-control" required style="font-weight:600;">
                                    <option value="Normal" ${visit.product_condition === 'Normal' ? 'selected' : ''}>Normal / Good Condition</option>
                                    <option value="Minor Issue" ${visit.product_condition === 'Minor Issue' ? 'selected' : ''}>Minor Issue (Operational)</option>
                                    <option value="Major Issue" ${visit.product_condition === 'Major Issue' ? 'selected' : ''}>Major Issue (Requires Repair)</option>
                                    <option value="Not Working" ${visit.product_condition === 'Not Working' ? 'selected' : ''}>Not Working / Failure</option>
                                </select>
                            </div>

                            <div class="form-group">
                                <label style="font-weight:600;">Service Performed</label>
                                <input type="text" name="service_performed" class="form-control" value="${visit.service_performed || ''}" placeholder="e.g. Dust cleaning, firmware update, cable test...">
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label style="font-weight:600;">Inspection Result &amp; Observations <span style="color:var(--danger)">*</span></label>
                            <textarea name="inspection_result" class="form-control" rows="3" required placeholder="Describe inspection observations in detail...">${visit.inspection_result || ''}</textarea>
                        </div>

                        <div class="form-group mt-3">
                            <label style="font-weight:600; display:block; margin-bottom:8px;">Inspection Photo (Optional)</label>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                                <button type="button" class="btn btn-primary" onclick="window.AMC.triggerCamera('inspection_photo_input')" style="display:inline-flex; align-items:center; gap:6px;">
                                    📷 Take Photo with Camera
                                </button>
                                <label class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                                    📁 Select Photo
                                    <input type="file" id="inspection_photo_input" name="inspection_photos[]" accept="image/*" capture="environment" style="display:none;" onchange="window.AMC.handlePhotoSelect(this, 'inspection_preview')">
                                </label>
                            </div>
                            <div id="inspection_preview"></div>
                        </div>

                        <div style="display:flex; justify-content:space-between; gap:10px; margin-top:25px;">
                            <button type="button" class="btn btn-secondary" onclick="window.AMC.setWizardStep(2)">⬅ Back to Step 2</button>
                            <button type="submit" class="btn btn-primary" style="padding:12px 24px; font-weight:700;">Save Inspection &amp; Proceed to Step 4 ➔</button>
                        </div>
                    </form>
                </div>
            `;
        }

        // ===== WIZARD STEP 4: MAINTENANCE REQUIREMENT (OPTIONAL) =====
        else if (this.activeStep === 4) {
            html += `
                <!-- Step 4 Optional Banner -->
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:20px; text-align:center; margin-bottom:20px;">
                    <h4 style="margin:0 0 8px 0; color:#15803d;">✅ Everything Working Normally?</h4>
                    <p style="margin:0 0 15px 0; color:#166534; font-size:0.95rem;">Step 4 is <strong>OPTIONAL</strong>. If no equipment is damaged and no extra parts are required, skip to Step 5.</p>
                    <button type="button" class="btn btn-success" onclick="window.AMC.setWizardStep(5)" style="padding:12px 28px; font-weight:700; font-size:1.05rem;">⏩ Skip Maintenance &amp; Proceed to Step 5 (Completion)</button>
                </div>

                <!-- Record Issue / Part Replacement Card -->
                <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:12px; padding:25px;">
                    <h4 style="color:#b45309; margin-bottom:15px;">⚠️ Record Damaged Device / Broken Part / Extra Maintenance Requirement (Optional)</h4>
                    <form id="amcIssueForm" onsubmit="window.AMC.submitIssue(event, ${visit.id})">
                        <input type="hidden" name="latitude" value="${this.gpsCoords.lat}">
                        <input type="hidden" name="longitude" value="${this.gpsCoords.lng}">

                        <div class="form-grid" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label style="font-weight:600;">Affected Product <span style="color:var(--danger)">*</span></label>
                                <select name="product_name" class="form-control" required>
                                    ${visit.products && visit.products.length > 0 ? visit.products.map(p => `<option value="${p.product_name}">${p.product_name} (${p.serial_number || 'SN N/A'})</option>`).join('') : '<option value="General Equipment">General Equipment</option>'}
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Issue Title <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="issue_title" class="form-control" required placeholder="e.g. Camera 3 Blur / Printer Roller Damaged">
                            </div>
                        </div>

                        <div class="form-grid mt-3" style="display:grid; grid-template-columns:1fr 1fr; gap:15px;">
                            <div class="form-group">
                                <label style="font-weight:600;">Severity</label>
                                <select name="severity" class="form-control">
                                    <option value="Low">Low</option>
                                    <option value="Medium" selected>Medium</option>
                                    <option value="High">High</option>
                                    <option value="Critical">Critical</option>
                                </select>
                            </div>
                            <div class="form-group">
                                <label style="font-weight:600;">Part / Component Required</label>
                                <input type="text" name="part_required" class="form-control" placeholder="e.g. 12V 2A Adapter / Cat6 Cable 10m">
                            </div>
                        </div>

                        <div class="form-group mt-3">
                            <label style="font-weight:600;">Issue Description <span style="color:var(--danger)">*</span></label>
                            <textarea name="description" class="form-control" rows="2" required placeholder="Describe the fault or broken part..."></textarea>
                        </div>

                        <div class="form-group mt-3">
                            <label style="font-weight:600;">Required Action / Work to be Done</label>
                            <input type="text" name="required_action" class="form-control" placeholder="e.g. Replace power adapter and reconnect cable">
                        </div>

                        <div class="form-group mt-3">
                            <label style="font-weight:600; display:block; margin-bottom:8px;">Issue Photograph (Optional)</label>
                            <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                                <button type="button" class="btn btn-warning" onclick="window.AMC.triggerCamera('issue_photo_input')" style="display:inline-flex; align-items:center; gap:6px;">
                                    📷 Take Photo with Camera
                                </button>
                                <label class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                                    📁 Select Photo
                                    <input type="file" id="issue_photo_input" name="issue_photo" accept="image/*" capture="environment" style="display:none;" onchange="window.AMC.handlePhotoSelect(this, 'issue_preview')">
                                </label>
                            </div>
                            <div id="issue_preview"></div>
                        </div>

                        <div class="form-group mt-3">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="requires_followup" value="1" style="width:18px; height:18px;">
                                <span style="font-weight:700; color:#b45309;">Mark as Follow-Up Required (Cannot be fixed during current visit)</span>
                            </label>
                        </div>

                        <div style="display:flex; justify-content:space-between; gap:10px; margin-top:20px;">
                            <button type="button" class="btn btn-secondary" onclick="window.AMC.setWizardStep(3)">⬅ Back to Step 3</button>
                            <button type="submit" class="btn btn-warning" style="padding:12px 24px; font-weight:700;">Record Issue &amp; Proceed to Step 5 ➔</button>
                        </div>
                    </form>
                </div>
            `;
        }

        // ===== WIZARD STEP 5: FINAL COMPLETION & DEPARTURE =====
        else if (this.activeStep === 5) {
            if (isCompleted) {
                html += `
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:25px; text-align:center;">
                        <h4 style="color:#166534; margin-bottom:10px;">🎉 AMC Visit Successfully Completed</h4>
                        <p style="color:#15803d; margin:0;">Completion Timestamp: <strong>${visit.completion_timestamp}</strong></p>
                        <p style="color:#15803d;">Final Remark: <em>"${visit.final_remark || 'N/A'}"</em></p>
                    </div>
                `;
            } else {
                html += `
                    <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:25px;">
                        <h4 style="color:#15803d; margin-bottom:15px;">Step 5 of 5: Final Service Completion &amp; Departure</h4>
                        <form id="amcCompletionForm" onsubmit="window.AMC.submitCompletion(event, ${visit.id})">
                            <input type="hidden" name="latitude" value="${this.gpsCoords.lat}">
                            <input type="hidden" name="longitude" value="${this.gpsCoords.lng}">

                            <div class="form-group mb-3">
                                <label style="font-weight:600; display:block; margin-bottom:8px;">After-Service Photograph <span style="color:var(--danger)">*</span></label>
                                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                                    <button type="button" class="btn btn-success" onclick="window.AMC.triggerCamera('after_service_photo_input')" style="display:inline-flex; align-items:center; gap:6px;">
                                        📷 Take Photo with Camera
                                    </button>
                                    <label class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                                        📁 Select Photo
                                        <input type="file" id="after_service_photo_input" name="after_service_photo" accept="image/*" capture="environment" style="display:none;" onchange="window.AMC.handlePhotoSelect(this, 'after_service_preview')">
                                    </label>
                                </div>
                                <div id="after_service_preview"></div>
                                <small class="text-muted">Mandatory photo of completed service with watermark, date, time &amp; GPS.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label style="font-weight:600;">Final Service Remark <span style="color:var(--danger)">*</span></label>
                                <textarea name="final_remark" class="form-control" rows="2" required placeholder="Summarize completed work and customer feedback..."></textarea>
                            </div>

                            <div class="form-group mb-3">
                                <label style="font-weight:600; display:block; margin-bottom:8px;">Departure Photograph <span style="color:var(--danger)">*</span></label>
                                <div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:8px;">
                                    <button type="button" class="btn btn-success" onclick="window.AMC.triggerCamera('departure_photo_input')" style="display:inline-flex; align-items:center; gap:6px;">
                                        📷 Take Photo with Camera
                                    </button>
                                    <label class="btn btn-secondary" style="display:inline-flex; align-items:center; gap:6px; cursor:pointer; margin:0;">
                                        📁 Select Photo
                                        <input type="file" id="departure_photo_input" name="departure_photo" accept="image/*" capture="environment" style="display:none;" onchange="window.AMC.handlePhotoSelect(this, 'departure_preview')">
                                    </label>
                                </div>
                                <div id="departure_preview"></div>
                                <small class="text-muted">Mandatory departure photograph before leaving customer site.</small>
                            </div>

                            <div class="form-group mb-3">
                                <label style="font-weight:600;">Departure Remark <span style="color:var(--danger)">*</span></label>
                                <textarea name="departure_remark" class="form-control" rows="2" required placeholder="e.g. Customer satisfied. Departing from customer office."></textarea>
                            </div>

                            <div style="display:flex; justify-content:space-between; gap:10px; margin-top:20px;">
                                <button type="button" class="btn btn-secondary" onclick="window.AMC.setWizardStep(4)">⬅ Back to Step 4</button>
                                <button type="submit" class="btn btn-success" style="padding:15px 30px; font-size:1.1rem; font-weight:700;">🎉 COMPLETE VISIT &amp; DEPART</button>
                            </div>
                        </form>
                    </div>
                `;
            }
        }

        // Render Photos Gallery for Current Visit
        if (visit.photos && visit.photos.length > 0) {
            html += `
                <div style="margin-top:25px; border-top:1px solid #e2e8f0; padding-top:20px;">
                    <h4 style="font-size:1rem; font-weight:700; color:#334155; margin-bottom:12px;">Visit Photos Gallery (${visit.photos.length})</h4>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        ${visit.photos.map(ph => `
                            <div style="position:relative;">
                                <img src="../${ph.file_path}" style="height:110px; width:110px; border-radius:10px; object-fit:cover; border:1px solid #cbd5e1; cursor:pointer;" onclick="window.open('../${ph.file_path}','_blank')">
                                <span style="position:absolute; bottom:4px; left:4px; background:rgba(0,0,0,0.75); color:#fff; font-size:0.65rem; padding:2px 6px; border-radius:4px; font-weight:700;">${ph.photo_type}</span>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
        }

        contentDiv.innerHTML = html;
    },

    submitAccept: async function (visitId) {
        try {
            const formData = new FormData();
            formData.append('action', 'step_accept');
            formData.append('visit_id', visitId);

            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                this.activeStep = 2;
                this.openVisitModal(visitId);
            } else {
                alert('Error: ' + json.message);
            }
        } catch (e) {
            alert('Request failed.');
        }
    },

    submitReach: async function (e, visitId) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Uploading Arrival Photo & Location...';

        const formData = new FormData(form);
        formData.append('action', 'step_reach');
        formData.append('visit_id', visitId);

        if (this.capturedBlobs['arrival_photo_input']) {
            formData.set('arrival_photo', this.capturedBlobs['arrival_photo_input'], 'arrival.jpg');
        }

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                this.activeStep = 3;
                this.openVisitModal(visitId);
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
                btn.innerText = 'Submit Arrival & Proceed to Step 3 ➔';
            }
        } catch (err) {
            alert('Submission failed.');
            btn.disabled = false;
            btn.innerText = 'Submit Arrival & Proceed to Step 3 ➔';
        }
    },

    submitInspection: async function (e, visitId) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;

        const formData = new FormData(form);
        formData.append('action', 'step_inspection');
        formData.append('visit_id', visitId);

        if (this.capturedBlobs['inspection_photo_input']) {
            formData.set('inspection_photos[]', this.capturedBlobs['inspection_photo_input'], 'inspection.jpg');
        }

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                this.activeStep = 4;
                this.openVisitModal(visitId);
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
            }
        } catch (err) {
            alert('Submission failed.');
            btn.disabled = false;
        }
    },

    submitIssue: async function (e, visitId) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;

        const formData = new FormData(form);
        formData.append('action', 'step_record_issue');
        formData.append('visit_id', visitId);

        if (this.capturedBlobs['issue_photo_input']) {
            formData.set('issue_photo', this.capturedBlobs['issue_photo_input'], 'issue.jpg');
        }

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                form.reset();
                this.activeStep = 5;
                this.openVisitModal(visitId);
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
            }
        } catch (err) {
            alert('Submission failed.');
            btn.disabled = false;
        }
    },

    submitCompletion: async function (e, visitId) {
        e.preventDefault();
        const form = e.target;
        const btn = form.querySelector('button[type="submit"]');
        btn.disabled = true;
        btn.innerText = 'Processing Departure & Final Watermarking...';

        const formData = new FormData(form);
        formData.append('action', 'step_completion');
        formData.append('visit_id', visitId);

        if (this.capturedBlobs['after_service_photo_input']) {
            formData.set('after_service_photo', this.capturedBlobs['after_service_photo_input'], 'after_service.jpg');
        }
        if (this.capturedBlobs['departure_photo_input']) {
            formData.set('departure_photo', this.capturedBlobs['departure_photo_input'], 'departure.jpg');
        }

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert('🎉 ' + json.message);
                this.activeStep = 5;
                this.openVisitModal(visitId);
                if (typeof loadMyAmcAssignments === 'function') loadMyAmcAssignments();
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
                btn.innerText = 'COMPLETE VISIT & DEPART';
            }
        } catch (err) {
            alert('Completion submission failed.');
            btn.disabled = false;
            btn.innerText = 'COMPLETE VISIT & DEPART';
        }
    },

    getStatusBadgeClass: function (status) {
        const s = (status || '').toLowerCase();
        if (s === 'assigned') return 'badge-amc badge-assigned';
        if (s === 'accepted') return 'badge-amc badge-accepted';
        if (s === 'reached') return 'badge-amc badge-reached';
        if (s === 'inspection') return 'badge-amc badge-inspection';
        if (s === 'follow-up required') return 'badge-amc badge-followup';
        if (s === 'completed') return 'badge-amc badge-completed';
        if (s === 'overdue') return 'badge-amc badge-overdue';
        return 'badge-amc badge-cancelled';
    }
};

document.addEventListener('DOMContentLoaded', () => {
    window.AMC.init();
});
