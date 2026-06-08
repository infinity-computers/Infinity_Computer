// Task Management Frontend Controller

let tasksState = {
    tasks: [],
    activeTaskId: null,
    activeTaskDetails: null,
    filters: {
        search: '',
        status: '',
        priority: '',
        assigned_to: '',
        due_date_start: '',
        due_date_end: '',
        sort_by: 'created_at',
        sort_order: 'DESC',
        page: 1,
        limit: 8
    },
    pagination: {
        total: 0,
        page: 1,
        limit: 8,
        pages: 1
    }
};

let chartInstances = {};

document.addEventListener('DOMContentLoaded', () => {
    // 1. Initial Data Fetch
    fetchTasks();
    if (document.getElementById('tabHeaderDash')) {
        fetchDashboardStats();
    }

    // 2. Set Up Event Listeners
    setupEventListeners();
});

// Setup global events
function setupEventListeners() {
    // Search input with debounce
    const searchInput = document.getElementById('searchQuery');
    if (searchInput) {
        let debounceTimer;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                tasksState.filters.search = e.target.value;
                tasksState.filters.page = 1;
                fetchTasks();
            }, 300);
        });
    }

    // Dropdown filters
    const filterIds = ['filterStatus', 'filterPriority', 'filterAssignee', 'sortSelect'];
    filterIds.forEach(id => {
        const el = document.getElementById(id);
        if (el) {
            el.addEventListener('change', (e) => {
                if (id === 'sortSelect') {
                    const parts = e.target.value.split('-');
                    tasksState.filters.sort_by = parts[0];
                    tasksState.filters.sort_order = parts[1] || 'DESC';
                } else {
                    const filterKey = id.replace('filter', '').toLowerCase();
                    const finalKey = filterKey === 'assignee' ? 'assigned_to' : filterKey;
                    tasksState.filters[finalKey] = e.target.value;
                }
                tasksState.filters.page = 1;
                fetchTasks();
            });
        }
    });

    // Create Task Form submission
    const createTaskForm = document.getElementById('createTaskForm');
    if (createTaskForm) {
        createTaskForm.addEventListener('submit', handleCreateTaskSubmit);
    }

    // Status Update Form submission
    const statusForm = document.getElementById('statusUpdateForm');
    if (statusForm) {
        statusForm.addEventListener('submit', handleStatusUpdateSubmit);
    }

    // Reassign Form submission
    const reassignForm = document.getElementById('reassignTaskForm');
    if (reassignForm) {
        reassignForm.addEventListener('submit', handleReassignSubmit);
    }

    // Comment Form submission
    const commentForm = document.getElementById('commentForm');
    if (commentForm) {
        commentForm.addEventListener('submit', handleCommentSubmit);
    }
}

// Fetch lists of tasks from get_tasks.php
async function fetchTasks() {
    const listBody = document.getElementById('tasksListBody');
    if (!listBody) return;

    listBody.innerHTML = '<div style="text-align:center; padding: 40px;"><p style="color:#64748b;">Loading tasks...</p></div>';

    // Build URL query params
    const q = new URLSearchParams(tasksState.filters).toString();

    try {
        const res = await fetch(`api/get_tasks.php?${q}`);
        const json = await res.json();

        if (json.status === 'success') {
            tasksState.tasks = json.data.tasks;
            tasksState.pagination = json.data.pagination;
            
            renderTasksList();
            renderPaginationControls();

            // Auto-select first task if none is active
            if (tasksState.tasks.length > 0) {
                if (!tasksState.activeTaskId || !tasksState.tasks.some(t => t.task_id === tasksState.activeTaskId)) {
                    selectTask(tasksState.tasks[0].task_id);
                }
            } else {
                tasksState.activeTaskId = null;
                tasksState.activeTaskDetails = null;
                renderTaskDetailsEmptyState();
            }
        } else {
            listBody.innerHTML = `<div class="empty-state"><h3>Failed to load tasks</h3><p>${json.message}</p></div>`;
        }
    } catch (err) {
        listBody.innerHTML = '<div class="empty-state"><h3>Request failed</h3><p>Server error occurred while loading tasks.</p></div>';
    }
}

// Render task items in listing panel
function renderTasksList() {
    const listBody = document.getElementById('tasksListBody');
    if (!listBody) return;

    if (tasksState.tasks.length === 0) {
        listBody.innerHTML = `
            <div class="empty-state">
                <h3>🔍 No tasks found</h3>
                <p>Try clearing your filters or add a new task assignment.</p>
            </div>
        `;
        return;
    }

    listBody.innerHTML = '';
    tasksState.tasks.forEach(task => {
        const div = document.createElement('div');
        div.className = `task-item ${task.task_id === tasksState.activeTaskId ? 'active' : ''}`;
        div.onclick = () => selectTask(task.task_id);

        const statusClass = task.status.toLowerCase().replace(' ', '_');
        const priClass = task.priority.toLowerCase();
        
        let dueDateStr = task.due_date ? formatDate(task.due_date) : 'No due date';
        
        div.innerHTML = `
            <div class="task-item-left">
                <div class="task-item-title">${escapeHtml(task.title)}</div>
                <div class="task-item-meta">
                    <span style="font-weight: 600; color: var(--primary-color);">${task.task_id}</span>
                    <span>👤 ${escapeHtml(task.assigned_to)}</span>
                    <span>📅 ${dueDateStr}</span>
                </div>
            </div>
            <div class="task-item-right">
                <span class="task-badge s-${statusClass}">${task.status}</span>
                <span class="task-badge p-${priClass}">${task.priority}</span>
            </div>
        `;
        listBody.appendChild(div);
    });
}

// Render pagination metadata and button controls
function renderPaginationControls() {
    const pagContainer = document.getElementById('paginationControls');
    if (!pagContainer) return;

    const p = tasksState.pagination;
    if (p.pages <= 1) {
        pagContainer.innerHTML = '';
        return;
    }

    let buttonsHtml = `
        <button class="page-btn" onclick="changePage(1)" ${p.page === 1 ? 'disabled' : ''}>&laquo;</button>
        <button class="page-btn" onclick="changePage(${p.page - 1})" ${p.page === 1 ? 'disabled' : ''}>&lsaquo;</button>
    `;

    // Limit pages displayed in layout
    const maxPagesToShow = 5;
    let startPage = Math.max(1, p.page - 2);
    let endPage = Math.min(p.pages, startPage + maxPagesToShow - 1);

    if (endPage - startPage < maxPagesToShow - 1) {
        startPage = Math.max(1, endPage - maxPagesToShow + 1);
    }

    for (let i = startPage; i <= endPage; i++) {
        buttonsHtml += `
            <button class="page-btn ${p.page === i ? 'active' : ''}" onclick="changePage(${i})">${i}</button>
        `;
    }

    buttonsHtml += `
        <button class="page-btn" onclick="changePage(${p.page + 1})" ${p.page === p.pages ? 'disabled' : ''}>&rsaquo;</button>
        <button class="page-btn" onclick="changePage(${p.pages})" ${p.page === p.pages ? 'disabled' : ''}>&raquo;</button>
    `;

    pagContainer.innerHTML = `
        <div>Showing ${(p.page - 1) * p.limit + 1} - ${Math.min(p.page * p.limit, p.total)} of ${p.total} tasks</div>
        <div class="pagination-buttons">${buttonsHtml}</div>
    `;
}

function changePage(pageNumber) {
    tasksState.filters.page = pageNumber;
    fetchTasks();
}

// Select a single task to view complete details
function selectTask(taskId) {
    tasksState.activeTaskId = taskId;
    
    // Highlight list row
    document.querySelectorAll('.task-item').forEach(el => el.classList.remove('active'));
    renderTasksList(); // Re-render slightly to apply class correctly or target direct DOM
    
    fetchTaskDetails(taskId);
}

// Call API for details + comments + logs of task
async function fetchTaskDetails(taskId) {
    const detailsContainer = document.getElementById('taskDetailsBody');
    if (!detailsContainer) return;

    detailsContainer.innerHTML = '<div style="text-align:center; padding: 80px;"><p style="color:#64748b;">Loading task details...</p></div>';

    try {
        const res = await fetch(`api/get_tasks.php?task_id=${taskId}`);
        const json = await res.json();

        if (json.status === 'success') {
            tasksState.activeTaskDetails = json.data;
            renderTaskDetailsPanel();
        } else {
            detailsContainer.innerHTML = `<div class="empty-state"><h3>Error loading details</h3><p>${json.message}</p></div>`;
        }
    } catch (err) {
        detailsContainer.innerHTML = '<div class="empty-state"><h3>Request failed</h3><p>Server error occurred while loading details.</p></div>';
    }
}

// Render selected task details in the right side split pane
function renderTaskDetailsPanel() {
    const detailsContainer = document.getElementById('taskDetailsBody');
    if (!detailsContainer) return;

    const data = tasksState.activeTaskDetails;
    const task = data.task;
    const comments = data.comments;
    const logs = data.logs;

    const statusClass = task.status.toLowerCase().replace(' ', '_');
    const priClass = task.priority.toLowerCase();
    const dueDateStr = task.due_date ? formatDate(task.due_date) : 'No due date';
    const createdAtStr = formatDate(task.created_at, true);

    // Render attachments link
    let attachmentHtml = '<span style="color:#64748b; font-style:italic;">None</span>';
    if (task.attachment_path) {
        const ext = task.attachment_path.split('.').pop().toLowerCase();
        let label = '📎 View Attachment';
        if (['jpg', 'jpeg', 'png'].includes(ext)) {
            label = '🖼 View Image';
        } else if (ext === 'pdf') {
            label = '📄 View PDF';
        }
        attachmentHtml = `<a href="${task.attachment_path}" target="_blank" class="badge default" style="text-decoration:none; background:#1f5fae; color:#fff; display:inline-flex; align-items:center; gap:5px; font-weight:600;">${label}</a>`;
    }

    // Comments HTML
    let commentsListHtml = '<p style="color:#64748b; font-size:0.88rem; font-style:italic; text-align:center; padding:10px;">No comments yet.</p>';
    if (comments.length > 0) {
        commentsListHtml = comments.map(comm => `
            <div class="comment-card">
                <div class="comment-card-meta">
                    <span style="font-weight:600; color:var(--primary-color);">${escapeHtml(comm.comment_by)}</span>
                    <span>${formatDate(comm.created_at, true)}</span>
                </div>
                <div class="comment-card-text">${escapeHtml(comm.comment)}</div>
            </div>
        `).join('');
    }

    // Timeline Logs HTML
    const timelineHtml = logs.map(log => {
        let typeClass = 'event-status';
        if (log.action === 'Task Created') typeClass = 'event-creation';
        else if (log.action === 'Task Reassigned') typeClass = 'event-reassigned';
        else if (log.action === 'Comment Added') typeClass = 'event-comment';
        else if (log.action === 'Email Sent') typeClass = 'event-email';
        else if (log.action === 'Reminder Sent') typeClass = 'event-email';
        else if (log.remarks && log.remarks.includes('Completed')) typeClass = 'event-completion';

        return `
            <li class="timeline-event ${typeClass}">
                <div class="timeline-dot"></div>
                <div class="timeline-event-content">
                    <div class="timeline-time">${formatDate(log.created_at, true)}</div>
                    <div class="timeline-action">${escapeHtml(log.action)} <span style="font-size:0.75rem; color:#64748b;">(by ${escapeHtml(log.done_by)})</span></div>
                    ${log.remarks ? `<div class="timeline-remarks">${escapeHtml(log.remarks)}</div>` : ''}
                </div>
            </li>
        `;
    }).join('');

    // Access control logic
    const loggedInEngineerName = Object.keys(ENGINEER_MAP).find(key => ENGINEER_MAP[key].toLowerCase() === LOGGED_IN_EMAIL.toLowerCase()) || '';
    const canModify = IS_ADMIN || (loggedInEngineerName && task.assigned_to && task.assigned_to.toLowerCase() === loggedInEngineerName.toLowerCase());

    let actionButtonsHtml = '';
    if (canModify) {
        actionButtonsHtml = `
            <div style="display:flex; gap:10px; margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom:20px;">
                <button class="btn" style="flex:1; padding: 10px; font-size:0.85rem; border-radius:8px; box-shadow:none;" onclick="openStatusModal('${task.task_id}', '${task.status}')">⚡ Update Status</button>
                <button class="btn" style="flex:1; padding: 10px; font-size:0.85rem; border-radius:8px; background:#8b5cf6; box-shadow:none;" onclick="openReassignModal('${task.task_id}', '${task.assigned_to}')">🔄 Reassign Task</button>
            </div>
        `;
    } else {
        actionButtonsHtml = `
            <div style="margin-bottom: 25px; border-bottom: 1px solid var(--border-color); padding-bottom:20px; color:#64748b; font-size:0.88rem; font-style:italic; text-align:center; background:#f8fafc; border-radius:8px; padding:12px;">
                🔒 Read-Only: This task is assigned to <strong>${escapeHtml(task.assigned_to)}</strong>.
            </div>
        `;
    }

    detailsContainer.innerHTML = `
        <!-- Task Header Info -->
        <div class="task-details-header">
            <div style="display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 10px;">
                <span style="font-size:0.85rem; font-weight:700; color:var(--primary-color); background:#edf2f7; padding:4px 8px; border-radius:6px;">${task.task_id}</span>
                <div style="display:flex; gap:6px;">
                    <span class="task-badge p-${priClass}">${task.priority}</span>
                    <span class="task-badge s-${statusClass}">${task.status}</span>
                </div>
            </div>
            <h2>${escapeHtml(task.title)}</h2>
            <div style="font-size:0.75rem; color:#64748b; margin-top:5px;">
                Created by <strong>${escapeHtml(task.created_by)}</strong> on ${createdAtStr}
            </div>
        </div>

        <!-- Meta Grid -->
        <div class="task-details-grid">
            <div class="task-details-item">
                <label>Assigned Engineer</label>
                <span>👨‍🔧 ${escapeHtml(task.assigned_to)}</span>
            </div>
            <div class="task-details-item">
                <label>Due Date</label>
                <span>📅 ${dueDateStr}</span>
            </div>
            <div class="task-details-item">
                <label>Customer Name</label>
                <span>👤 ${task.customer_name ? escapeHtml(task.customer_name) : '<span style="color:#94a3b8">N/A</span>'}</span>
            </div>
            <div class="task-details-item">
                <label>Customer Phone</label>
                <span>📞 ${task.phone ? escapeHtml(task.phone) : '<span style="color:#94a3b8">N/A</span>'}</span>
            </div>
            <div class="task-details-item" style="grid-column: span 2;">
                <label>Attachment Document</label>
                <span>${attachmentHtml}</span>
            </div>
        </div>

        <!-- Action Panel Buttons -->
        ${actionButtonsHtml}

        <!-- Description Box -->
        <h4 style="font-size:0.95rem; font-weight:600; color:var(--text-dark); margin-bottom:8px;">📝 Task Description</h4>
        <div class="task-description-box">${escapeHtml(task.description)}</div>

        <!-- Tab Controls inside Details -->
        <div class="tab-controls" style="margin-bottom: 15px;">
            <button class="tab-btn active" id="detailsTabCommentsBtn" onclick="switchDetailsTab('comments')">Comments (${comments.length})</button>
            <button class="tab-btn" id="detailsTabTimelineBtn" onclick="switchDetailsTab('timeline')">Timeline Logs (${logs.length})</button>
        </div>

        <!-- Comments Pane -->
        <div id="detailsPaneComments">
            <div style="max-height: 250px; overflow-y:auto; margin-bottom: 15px; padding-right:5px;">
                ${commentsListHtml}
            </div>
            
            <!-- Comment Form -->
            <form id="commentForm" style="display:flex; gap:10px;">
                <input type="hidden" name="task_id" value="${task.task_id}">
                <input type="text" name="comment" class="form-control" placeholder="Add internal comment..." required style="padding:10px;">
                <button type="submit" class="btn" style="padding:10px 18px; box-shadow:none; border-radius:10px;">Send</button>
            </form>
        </div>

        <!-- Timeline Pane -->
        <div id="detailsPaneTimeline" class="hidden">
            <div style="max-height: 350px; overflow-y:auto; padding-right:5px;">
                <ul class="timeline-list">
                    ${timelineHtml}
                </ul>
            </div>
        </div>
    `;
}

function renderTaskDetailsEmptyState() {
    const detailsContainer = document.getElementById('taskDetailsBody');
    if (!detailsContainer) return;

    detailsContainer.innerHTML = `
        <div class="empty-state" style="padding: 100px 20px;">
            <h3>📊 Task Details Panel</h3>
            <p>Select a task from the list on the left to view complete tracking, attachments, timelines, and comment history.</p>
        </div>
    `;
}

function switchDetailsTab(tabId) {
    const commBtn = document.getElementById('detailsTabCommentsBtn');
    const timeBtn = document.getElementById('detailsTabTimelineBtn');
    const commPane = document.getElementById('detailsPaneComments');
    const timePane = document.getElementById('detailsPaneTimeline');

    if (tabId === 'comments') {
        commBtn.classList.add('active');
        timeBtn.classList.remove('active');
        commPane.classList.remove('hidden');
        timePane.classList.add('hidden');
    } else {
        timeBtn.classList.add('active');
        commBtn.classList.remove('active');
        timePane.classList.remove('hidden');
        commPane.classList.add('hidden');
    }
}

// Fetch dashboard aggregate metrics
async function fetchDashboardStats() {
    try {
        const res = await fetch('api/task_analytics.php');
        const json = await res.json();

        if (json.status === 'success') {
            const data = json.data;
            updateDashboardCounters(data.counters);
            renderAnalyticsCharts(data);
        }
    } catch (err) {
        console.error("Failed to load dashboard metrics", err);
    }
}

// Update numerical dashboard KPI cards
function updateDashboardCounters(c) {
    const mapping = {
        'countTotal': c.total,
        'countPending': c.pending,
        'countAccepted': c.accepted,
        'countCompleted': c.completed,
        'countOverdue': c.overdue,
        'countReassigned': c.reassigned
    };

    for (const [id, val] of Object.entries(mapping)) {
        const el = document.getElementById(id);
        if (el) {
            el.innerText = val;
        }
    }
}

// Draw Chart.js visualizations
function renderAnalyticsCharts(data) {
    const engStats = data.engineer_stats;
    const trends = data.monthly_trends;

    // Chart 1: Tasks per Engineer
    const ctx1 = document.getElementById('chartEngineerTasks');
    if (ctx1 && typeof Chart !== 'undefined') {
        if (chartInstances['eng']) chartInstances['eng'].destroy();
        
        const labels = engStats.map(e => e.engineer);
        const totals = engStats.map(e => e.total);
        const completed = engStats.map(e => e.completed);

        chartInstances['eng'] = new Chart(ctx1, {
            type: 'bar',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Assigned',
                        data: totals,
                        backgroundColor: 'rgba(59, 130, 246, 0.75)',
                        borderColor: '#3b82f6',
                        borderWidth: 1.5,
                        borderRadius: 5
                    },
                    {
                        label: 'Completed',
                        data: completed,
                        backgroundColor: 'rgba(16, 185, 129, 0.75)',
                        borderColor: '#10b981',
                        borderWidth: 1.5,
                        borderRadius: 5
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, grid: { color: '#f1f5f9' } },
                    x: { grid: { display: false } }
                },
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12, font: { family: 'Poppins' } } }
                }
            }
        });
    }

    // Chart 2: Pending vs Completed (Doughnut)
    const ctx2 = document.getElementById('chartStatusShare');
    if (ctx2 && typeof Chart !== 'undefined') {
        if (chartInstances['status']) chartInstances['status'].destroy();

        const counters = data.counters;
        chartInstances['status'] = new Chart(ctx2, {
            type: 'doughnut',
            data: {
                labels: ['Pending', 'Accepted', 'In Progress', 'On Hold', 'Completed', 'Rejected'],
                datasets: [{
                    data: [
                        counters.pending,
                        counters.accepted,
                        counters.in_progress,
                        counters.on_hold,
                        counters.completed,
                        counters.rejected
                    ],
                    backgroundColor: [
                        '#f59e0b', // warning
                        '#64748b', // default
                        '#6366f1', // indigo
                        '#f97316', // orange
                        '#10b981', // success
                        '#ef4444'  // danger
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right', labels: { boxWidth: 12, font: { family: 'Poppins' } } }
                },
                cutout: '65%'
            }
        });
    }
}

// Handle Forms Actions
async function handleCreateTaskSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    const oldText = btn.innerText;

    // Check Google reCAPTCHA
    if (typeof grecaptcha !== 'undefined') {
        const recaptchaResponse = grecaptcha.getResponse();
        if (!recaptchaResponse) {
            alert('Please complete the Google reCAPTCHA verification.');
            return;
        }
    }

    btn.disabled = true;
    btn.innerText = 'Creating...';

    const formData = new FormData(e.target);

    try {
        const res = await fetch('api/add_task.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.status === 'success') {
            alert(`Task created successfully!\nTask ID: ${json.task_id}`);
            e.target.reset();
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.reset();
            }
            
            // Switch view tab to main dashboard list
            switchMainTab('tasks-list-view');
            
            // Reload all tasks
            fetchTasks();
            if (document.getElementById('tabHeaderDash')) {
                fetchDashboardStats();
            }
        } else {
            alert('Error: ' + json.message);
            if (typeof grecaptcha !== 'undefined') {
                grecaptcha.reset();
            }
        }
    } catch (err) {
        alert('Server error. Failed to submit task.');
        if (typeof grecaptcha !== 'undefined') {
            grecaptcha.reset();
        }
    }

    btn.disabled = false;
    btn.innerText = oldText;
}

async function handleStatusUpdateSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    const formData = new FormData(e.target);

    try {
        const res = await fetch('api/update_task_status.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.status === 'success') {
            closeModal('modalStatusUpdate');
            e.target.reset();
            
            // Refresh
            fetchTasks();
            if (document.getElementById('tabHeaderDash')) {
                fetchDashboardStats();
            }
            if (tasksState.activeTaskId) {
                fetchTaskDetails(tasksState.activeTaskId);
            }

            } else {
                // Show detailed error info if available (temporary debug)
                const errMsg = json.details ? json.message + ' – Details: ' + json.details : json.message;
                alert('Error: ' + errMsg);
            }
    } catch (err) {
        alert('Request failed');
    }
    btn.disabled = false;
}

async function handleReassignSubmit(e) {
    e.preventDefault();
    const btn = e.target.querySelector('button[type="submit"]');
    btn.disabled = true;

    const formData = new FormData(e.target);

    try {
        const res = await fetch('api/reassign_task.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.status === 'success') {
            closeModal('modalReassignTask');
            e.target.reset();

            // Refresh
            fetchTasks();
            if (document.getElementById('tabHeaderDash')) {
                fetchDashboardStats();
            }
            if (tasksState.activeTaskId) {
                fetchTaskDetails(tasksState.activeTaskId);
            }
        } else {
            alert('Error: ' + json.message);
        }
    } catch (err) {
        alert('Request failed');
    }
    btn.disabled = false;
}

async function handleCommentSubmit(e) {
    e.preventDefault();
    const input = e.target.querySelector('input[name="comment"]');
    const comment = input.value.trim();
    if (!comment) return;

    const formData = new FormData(e.target);

    try {
        const res = await fetch('api/add_task_comment.php', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();

        if (json.status === 'success') {
            input.value = '';
            // Refresh details pane
            if (tasksState.activeTaskId) {
                fetchTaskDetails(tasksState.activeTaskId);
            }
        } else {
            alert('Error: ' + json.message);
        }
    } catch (err) {
        alert('Request failed');
    }
}

// Modal Helpers
function openStatusModal(taskId, currentStatus) {
    document.getElementById('statusUpdateTaskId').value = taskId;
    document.getElementById('statusSelect').value = currentStatus;
    
    // Auto populate status remarks text info
    document.getElementById('statusRemarks').value = '';

    openModal('modalStatusUpdate');
}

function openReassignModal(taskId, currentAssignee) {
    document.getElementById('reassignTaskId').value = taskId;
    document.getElementById('reassignSelect').value = currentAssignee;

    openModal('modalReassignTask');
}

function openModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.classList.add('active');
    }
}

function closeModal(modalId) {
    const el = document.getElementById(modalId);
    if (el) {
        el.classList.remove('active');
    }
}

// Tab Switching
function switchMainTab(id) {
    document.querySelectorAll('.task-view-pane').forEach(p => p.classList.add('hidden'));
    document.getElementById(id).classList.remove('hidden');

    document.querySelectorAll('.task-header-tab').forEach(t => t.classList.remove('active'));
    
    const mapping = {
        'tasks-dashboard-view': 'tabHeaderDash',
        'tasks-list-view': 'tabHeaderList',
        'tasks-create-view': 'tabHeaderCreate'
    };

    const tabId = mapping[id];
    if (tabId) {
        document.getElementById(tabId).classList.add('active');
    }
}

// UI Utilities
function formatDate(dateStr, includeTime = false) {
    if (!dateStr) return '';
    // Replace dashes with slashes for compatibility with Safari and other browsers
    const d = new Date(dateStr.replace(/-/g, "/"));
    if (isNaN(d.getTime())) return dateStr;

    const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
    const datePart = `${d.getDate()}-${months[d.getMonth()]}-${d.getFullYear()}`;

    if (!includeTime) return datePart;

    let hr = d.getHours();
    let min = d.getMinutes();
    const ampm = hr >= 12 ? 'PM' : 'AM';
    hr = hr % 12;
    hr = hr ? hr : 12; // 0 should be 12
    min = min < 10 ? '0' + min : min;
    
    return `${datePart} ${hr}:${min} ${ampm}`;
}

function escapeHtml(str) {
    if (!str) return '';
    return str
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;")
        .replace(/'/g, "&#039;");
}
