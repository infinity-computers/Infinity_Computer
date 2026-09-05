/**
 * Infinity Computer - AMC Workflow & Management Engine
 * Handles step-by-step visit progress, GPS auto-capture, photo upload with watermarking,
 * previous maintenance history view, and contract creation.
 */

window.AMC = {
    currentVisit: null,
    gpsCoords: { lat: '', lng: '' },

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

        // Refresh GPS
        this.fetchGps();

        try {
            const res = await fetch(`api/amc_visits_api.php?action=get_details&visit_id=${visitId}`);
            const json = await res.json();

            if (json.status !== 'success' || !json.data) {
                contentDiv.innerHTML = `<div class="alert alert-danger" style="padding:20px; color:#b91c1c; background:#fee2e2; border-radius:8px;">${json.message || 'Failed to load visit details.'}</div>`;
                return;
            }

            this.currentVisit = json.data;
            this.renderVisitWorkflow(json.data);
        } catch (e) {
            contentDiv.innerHTML = `<div class="alert alert-danger" style="padding:20px; color:#b91c1c; background:#fee2e2; border-radius:8px;">Network or database communication error.</div>`;
        }
    },

    closeVisitModal: function () {
        const modal = document.getElementById('amc-visit-modal');
        if (modal) modal.classList.remove('active');
        this.currentVisit = null;
    },

    renderVisitWorkflow: function (visit) {
        const contentDiv = document.getElementById('amc-visit-modal-content');
        const isCompleted = visit.status === 'COMPLETED';

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

        // ===== WORKFLOW STEPPER =====
        const status = visit.status;
        const step1Done = ['ACCEPTED', 'REACHED', 'INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step2Done = ['REACHED', 'INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step3Done = ['INSPECTION', 'FOLLOW-UP REQUIRED', 'COMPLETED'].includes(status);
        const step4Done = ['COMPLETED'].includes(status);

        html += `
            <div class="amc-stepper">
                <div class="amc-step-item ${step1Done ? 'done' : (status === 'ASSIGNED' ? 'active' : '')}">
                    <div class="amc-step-circle">1</div>
                    <div class="amc-step-label">Accept</div>
                </div>
                <div class="amc-step-item ${step2Done ? 'done' : (status === 'ACCEPTED' ? 'active' : '')}">
                    <div class="amc-step-circle">2</div>
                    <div class="amc-step-label">Reached</div>
                </div>
                <div class="amc-step-item ${step3Done ? 'done' : (status === 'REACHED' ? 'active' : '')}">
                    <div class="amc-step-circle">3</div>
                    <div class="amc-step-label">Inspection</div>
                </div>
                <div class="amc-step-item ${status === 'INSPECTION' || status === 'FOLLOW-UP REQUIRED' ? 'active' : (step4Done ? 'done' : '')}">
                    <div class="amc-step-circle">4</div>
                    <div class="amc-step-label">Maintenance</div>
                </div>
                <div class="amc-step-item ${isCompleted ? 'done active' : ''}">
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

        // ===== STEP FORMS BASED ON CURRENT STATUS =====
        if (status === 'ASSIGNED') {
            html += `
                <div style="text-align:center; padding:30px; background:#f0f9ff; border:1px solid #bae6fd; border-radius:12px;">
                    <h4 style="color:#0369a1; margin-bottom:10px;">Step 1: Accept AMC Assignment</h4>
                    <p style="color:#0c4a6e; font-size:0.95rem; margin-bottom:20px;">Please confirm acceptance of this AMC visit assignment to begin customer visit workflow.</p>
                    <button class="btn btn-primary" onclick="window.AMC.submitAccept(${visit.id})" style="padding:12px 30px; font-size:1.1rem;">Accept Assignment</button>
                </div>
            `;
        } else if (status === 'ACCEPTED') {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:25px;">
                    <h4 style="color:var(--amc-primary-dark); margin-bottom:15px;">Step 2: Reach Customer Location</h4>
                    <form id="amcReachForm" onsubmit="window.AMC.submitReach(event, ${visit.id})">
                        <input type="hidden" name="latitude" id="reachLat" value="${this.gpsCoords.lat}">
                        <input type="hidden" name="longitude" id="reachLng" value="${this.gpsCoords.lng}">
                        
                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Arrival Photograph <span style="color:var(--danger)">*</span></label>
                            <input type="file" name="arrival_photo" accept="image/*" capture="environment" class="form-control" required style="padding:10px;">
                            <small class="text-muted">Takes photo with watermark "INFINITY COMPUTER", date, time, and GPS coordinates.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Arrival Remark <span style="color:var(--danger)">*</span></label>
                            <textarea name="arrival_remark" class="form-control" rows="2" required placeholder="e.g. Reached customer station at main office gate..."></textarea>
                        </div>

                        <button type="submit" class="btn btn-primary" style="width:100%; padding:14px; font-size:1.05rem;">Submit Arrival &amp; Proceed to Inspection</button>
                    </form>
                </div>
            `;
        } else if (status === 'REACHED' || status === 'INSPECTION' || status === 'FOLLOW-UP REQUIRED') {
            html += `
                <div style="background:#fff; border:1px solid #e2e8f0; border-radius:12px; padding:25px; margin-bottom:20px;">
                    <h4 style="color:var(--amc-primary-dark); margin-bottom:15px;">Step 3 &amp; 4: Inspection &amp; Maintenance Requirements</h4>
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
                            <label style="font-weight:600;">Inspection Photo (Optional)</label>
                            <input type="file" name="inspection_photos[]" accept="image/*" capture="environment" multiple class="form-control">
                        </div>

                        <button type="submit" class="btn btn-secondary mt-3" style="width:100%; background:#475569; color:#fff; padding:12px;">Save Inspection Progress</button>
                    </form>
                </div>

                <!-- Record Issue / Part Replacement Card -->
                <div style="background:#fffbeb; border:1px solid #fef3c7; border-radius:12px; padding:25px; margin-bottom:20px;">
                    <h4 style="color:#b45309; margin-bottom:15px;">⚠️ Record Specific Issue / Broken Part / Maintenance Requirement</h4>
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
                            <label style="font-weight:600;">Issue Photograph</label>
                            <input type="file" name="issue_photo" accept="image/*" capture="environment" class="form-control">
                        </div>

                        <div class="form-group mt-3">
                            <label style="display:flex; align-items:center; gap:8px; cursor:pointer;">
                                <input type="checkbox" name="requires_followup" value="1" style="width:18px; height:18px;">
                                <span style="font-weight:700; color:#b45309;">Mark as Follow-Up Required (Cannot be fixed during current visit)</span>
                            </label>
                        </div>

                        <button type="submit" class="btn btn-warning mt-3" style="width:100%; padding:12px; font-weight:700;">Record Issue / Requirement</button>
                    </form>
                </div>

                <!-- Final Service Completion & Departure Card -->
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:25px;">
                    <h4 style="color:#15803d; margin-bottom:15px;">Step 5 &amp; 6: Final Service Completion &amp; Departure</h4>
                    <form id="amcCompletionForm" onsubmit="window.AMC.submitCompletion(event, ${visit.id})">
                        <input type="hidden" name="latitude" value="${this.gpsCoords.lat}">
                        <input type="hidden" name="longitude" value="${this.gpsCoords.lng}">

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">After-Service Photograph <span style="color:var(--danger)">*</span></label>
                            <input type="file" name="after_service_photo" accept="image/*" capture="environment" class="form-control" required style="padding:10px;">
                            <small class="text-muted">Mandatory photo of completed service with watermark, date, time &amp; GPS.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Final Service Remark <span style="color:var(--danger)">*</span></label>
                            <textarea name="final_remark" class="form-control" rows="2" required placeholder="Summarize completed work and customer feedback..."></textarea>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Departure Photograph <span style="color:var(--danger)">*</span></label>
                            <input type="file" name="departure_photo" accept="image/*" capture="environment" class="form-control" required style="padding:10px;">
                            <small class="text-muted">Mandatory departure photograph before leaving customer site.</small>
                        </div>

                        <div class="form-group mb-3">
                            <label style="font-weight:600;">Departure Remark <span style="color:var(--danger)">*</span></label>
                            <textarea name="departure_remark" class="form-control" rows="2" required placeholder="e.g. Customer satisfied. Departing from customer office."></textarea>
                        </div>

                        <button type="submit" class="btn btn-success" style="width:100%; padding:15px; font-size:1.1rem; font-weight:700;">COMPLETE VISIT &amp; DEPART</button>
                    </form>
                </div>
            `;
        } else if (isCompleted) {
            html += `
                <div style="background:#f0fdf4; border:1px solid #bbf7d0; border-radius:12px; padding:25px; text-align:center;">
                    <h4 style="color:#166534; margin-bottom:10px;">🎉 AMC Visit Successfully Completed</h4>
                    <p style="color:#15803d; margin:0;">Completion Timestamp: <strong>${visit.completion_timestamp}</strong></p>
                    <p style="color:#15803d;">Final Remark: <em>"${visit.final_remark || 'N/A'}"</em></p>
                </div>
            `;
        }

        // Render Photos Gallery for Current Visit
        if (visit.photos && visit.photos.length > 0) {
            html += `
                <div style="margin-top:25px; border-top:1px solid #e2e8f0; padding-top:20px;">
                    <h4 style="font-size:1rem; font-weight:700; color:#334155; margin-bottom:12px;">Visit Photos (${visit.photos.length})</h4>
                    <div style="display:flex; gap:12px; flex-wrap:wrap;">
                        ${visit.photos.map(ph => `
                            <div style="position:relative;">
                                <img src="../${ph.file_path}" style="height:110px; width:110px; border-radius:10px; object-fit:cover; border:1px solid #cbd5e1; cursor:pointer;" onclick="window.open('../${ph.file_path}','_blank')">
                                <span style="position:absolute; bottom:4px; left:4px; background:rgba(0,0,0,0.7); color:#fff; font-size:0.65rem; padding:2px 5px; border-radius:4px; font-weight:700;">${ph.photo_type}</span>
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

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                this.openVisitModal(visitId);
            } else {
                alert('Error: ' + json.message);
                btn.disabled = false;
                btn.innerText = 'Submit Arrival & Proceed to Inspection';
            }
        } catch (err) {
            alert('Submission failed.');
            btn.disabled = false;
            btn.innerText = 'Submit Arrival & Proceed to Inspection';
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

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
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

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert(json.message);
                form.reset();
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

        try {
            const res = await fetch('api/amc_visits_api.php', { method: 'POST', body: formData });
            const json = await res.json();
            if (json.status === 'success') {
                alert('🎉 ' + json.message);
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
