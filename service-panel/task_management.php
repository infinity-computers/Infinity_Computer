<?php include __DIR__ . '/auth_guard.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Task Management - Infinity Computer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;850&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/task-management.css">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <style>
        .nav-links a.header-active {
            color: var(--primary-color) !important;
        }
        .nav-links a.header-active::after {
            width: 60% !important;
            left: 20% !important;
        }
        .task-header-tab {
            font-size: 1.05rem;
            font-weight: 600;
            color: #64748b;
            padding: 10px 20px;
            border: none;
            background: none;
            cursor: pointer;
            position: relative;
            transition: all 0.3s ease;
        }
        .task-header-tab:hover {
            color: var(--primary-color);
        }
        .task-header-tab.active {
            color: var(--primary-color);
        }
        .task-header-tab.active::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: var(--primary-color);
            border-radius: 3px 3px 0 0;
        }
        .charts-grid {
            display: grid;
            grid-template-columns: 1.3fr 1fr;
            gap: 25px;
            margin-top: 25px;
        }
        @media (max-width: 992px) {
            .charts-grid {
                grid-template-columns: 1fr;
            }
        }
        .chart-card {
            background: var(--card-bg);
            border-radius: 12px;
            border: 1px solid var(--border-color);
            padding: 22px;
            box-shadow: var(--shadow);
        }
        .chart-card h3 {
            font-size: 1.05rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 15px;
            border-bottom: 1px solid var(--border-color);
            padding-bottom: 8px;
        }
        .chart-container {
            position: relative;
            height: 300px;
            width: 100%;
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
                <li><a href="javascript:void(0)" id="headerNewService" onclick="window.location.href='index.php?tab=new-service-tab'">Add New Service</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <?php if (in_array($_SESSION['staff_email'] ?? '', ['suraj@staff.infinitycomputer.in', 'icc@infinitycomputer.in'])): ?>
                <li><a href="crm.php">CRM Analytics</a></li>
                <?php endif; ?>
                <li><a href="task_management.php" class="header-active">Task Management</a></li>
                <li><a href="logout.php" style="color: #dc3545; font-weight: 600; border: 1px solid #dc3545; border-radius: 5px; padding: 5px 12px; margin-left: 10px; text-decoration: none;">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container">
        <!-- Module Header Navigation -->
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom: 2px solid var(--border-color); margin-bottom:30px; flex-wrap:wrap; gap:15px;">
            <div style="display:flex; gap:5px;">
                <button class="task-header-tab active" id="tabHeaderDash" onclick="switchMainTab('tasks-dashboard-view')">📊 Analytics Dashboard</button>
                <button class="task-header-tab" id="tabHeaderList" onclick="switchMainTab('tasks-list-view')">📋 Manage Assignments</button>
                <button class="task-header-tab" id="tabHeaderCreate" onclick="switchMainTab('tasks-create-view')">📥 Create New Task</button>
            </div>
            <div style="font-size:0.85rem; color:#64748b; font-weight:500;">
                Logged in as: <strong style="color:var(--primary-color);"><?php echo htmlspecialchars($_SESSION['staff_email'] ?? 'User'); ?></strong>
            </div>
        </div>

        <!-- ==================== 1. ANALYTICS DASHBOARD VIEW ==================== -->
        <div id="tasks-dashboard-view" class="task-view-pane">
            <!-- Stats Counters Row -->
            <div class="task-stats-grid">
                <div class="stat-card">
                    <h3>📋 Total Tasks</h3>
                    <div class="stat-value" id="countTotal">0</div>
                </div>
                <div class="stat-card stat-pending">
                    <h3>⏳ Pending</h3>
                    <div class="stat-value" id="countPending">0</div>
                </div>
                <div class="stat-card stat-accepted">
                    <h3>✅ Accepted</h3>
                    <div class="stat-value" id="countAccepted">0</div>
                </div>
                <div class="stat-card stat-completed">
                    <h3>🏆 Completed</h3>
                    <div class="stat-value" id="countCompleted">0</div>
                </div>
                <div class="stat-card stat-overdue">
                    <h3>⚠️ Overdue</h3>
                    <div class="stat-value" id="countOverdue">0</div>
                </div>
                <div class="stat-card stat-reassigned">
                    <h3>🔄 Reassigned</h3>
                    <div class="stat-value" id="countReassigned">0</div>
                </div>
            </div>

            <!-- Charts Split -->
            <div class="charts-grid">
                <div class="chart-card">
                    <h3>👨‍🔧 Engineer Workloads (Assigned vs Completed)</h3>
                    <div class="chart-container">
                        <canvas id="chartEngineerTasks"></canvas>
                    </div>
                </div>
                <div class="chart-card">
                    <h3>📊 Task Status Breakdown Share</h3>
                    <div class="chart-container">
                        <canvas id="chartStatusShare"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== 2. TASKS LIST SPLIT VIEW ==================== -->
        <div id="tasks-list-view" class="task-view-pane hidden">
            <!-- Filter Bar -->
            <div class="filter-controls">
                <div class="filter-group search-group">
                    <input type="text" id="searchQuery" class="form-control" placeholder="🔍 Search by Task ID, title, description, phone...">
                </div>
                <div class="filter-group">
                    <select id="filterStatus" class="form-control">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Accepted">Accepted</option>
                        <option value="In Progress">In Progress</option>
                        <option value="On Hold">On Hold</option>
                        <option value="Completed">Completed</option>
                        <option value="Rejected">Rejected</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="filterPriority" class="form-control">
                        <option value="">All Priorities</option>
                        <option value="Low">Low</option>
                        <option value="Medium">Medium</option>
                        <option value="High">High</option>
                        <option value="Urgent">Urgent</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="filterAssignee" class="form-control">
                        <option value="">All Engineers</option>
                        <option value="Suraj">Suraj</option>
                        <option value="Akshar">Akshar</option>
                        <option value="Karan">Karan</option>
                        <option value="Rahul">Rahul</option>
                        <option value="Paresh">Paresh</option>
                        <option value="Om">Om</option>
                        <option value="Jatin">Jatin</option>
                    </select>
                </div>
                <div class="filter-group">
                    <select id="sortSelect" class="form-control">
                        <option value="created_at-DESC">Newest Created</option>
                        <option value="created_at-ASC">Oldest Created</option>
                        <option value="due_date-ASC">Soonest Due</option>
                        <option value="priority-DESC">Highest Priority</option>
                    </select>
                </div>
            </div>

            <!-- Content Split Grid -->
            <div class="task-content-split">
                <!-- Left Pane: Scrollable List -->
                <div class="task-list-panel">
                    <h3 class="card-title" style="margin-bottom:15px; border:none; padding:0; display:flex; justify-content:space-between; align-items:center;">
                        <span>📋 Assignments List</span>
                        <span style="font-size:0.75rem; color:#64748b; font-weight:500;">Click on a task to view details</span>
                    </h3>
                    <div id="tasksListBody" style="max-height: 520px; overflow-y: auto; padding-right: 5px;">
                        <!-- Tasks dynamically populated -->
                    </div>
                    <!-- Pagination -->
                    <div id="paginationControls" class="pagination-container">
                        <!-- Pagination controls dynamically populated -->
                    </div>
                </div>

                <!-- Right Pane: Sticky Details -->
                <div class="task-details-panel">
                    <div id="taskDetailsBody">
                        <!-- Task details dynamically populated -->
                        <div class="empty-state" style="padding: 100px 20px;">
                            <h3>📊 Task Details Panel</h3>
                            <p>Select a task from the list on the left to view complete tracking, attachments, timelines, and comment history.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ==================== 3. CREATE TASK FORM VIEW ==================== -->
        <div id="tasks-create-view" class="task-view-pane hidden">
            <div class="card" style="max-width: 800px; margin: 0 auto; padding: 35px;">
                <h2 class="card-title" style="font-size:1.35rem; font-weight:700; color:var(--primary-dark); margin-bottom:25px;">📥 Assign New Company Task</h2>
                
                <form id="createTaskForm" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="taskTitle">Task Title / Action Item <span style="color:var(--danger)">*</span></label>
                        <input type="text" id="taskTitle" name="title" class="form-control" required placeholder="e.g. Repair Dell Latitude motherboard diagnostic">
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="form-group">
                            <label for="taskPriority">Priority Level <span style="color:var(--danger)">*</span></label>
                            <select id="taskPriority" name="priority" class="form-control" required>
                                <option value="Low">Low (General items)</option>
                                <option value="Medium" selected>Medium (Standard operations)</option>
                                <option value="High">High (Urgent customer delay)</option>
                                <option value="Urgent">Urgent (Action immediate)</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="taskAssignee">Assign To Engineer <span style="color:var(--danger)">*</span></label>
                            <select id="taskAssignee" name="assigned_to" class="form-control" required>
                                <option value="">Select engineer...</option>
                                <option value="Suraj">Suraj</option>
                                <option value="Akshar">Akshar</option>
                                <option value="Karan">Karan</option>
                                <option value="Rahul">Rahul</option>
                                <option value="Paresh">Paresh</option>
                                <option value="Om">Om</option>
                                <option value="Jatin">Jatin</option>
                            </select>
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="form-group">
                            <label for="taskCustomer">Customer Name (Optional)</label>
                            <input type="text" id="taskCustomer" name="customer_name" class="form-control" placeholder="e.g. Raj Sharma">
                        </div>
                        <div class="form-group">
                            <label for="taskPhone">Customer Phone (Optional)</label>
                            <input type="tel" id="taskPhone" name="phone" class="form-control" placeholder="e.g. 9876543210">
                        </div>
                    </div>

                    <div style="display:grid; grid-template-columns:1fr 1fr; gap:20px;">
                        <div class="form-group">
                            <label for="taskDueDate">Target Due Date (Optional)</label>
                            <input type="date" id="taskDueDate" name="due_date" class="form-control">
                        </div>
                        <div class="form-group">
                            <label for="taskAttachment">Attachment Document (Optional)</label>
                            <input type="file" id="taskAttachment" name="attachment" class="form-control" accept=".jpg,.jpeg,.png,.pdf,.docx,.doc,.txt,.zip">
                            <small style="color:#64748b; display:block; margin-top:4px;">Allowed formats: PDF, Images, Word, Text, Zip (Max 10MB)</small>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="taskDescription">Task Description & Instructions <span style="color:var(--danger)">*</span></label>
                        <textarea id="taskDescription" name="description" class="form-control" rows="5" required placeholder="Describe task specific details, required parts, diagnostic status, and steps clearly..."></textarea>
                    </div>

                    <div style="text-align:center; margin-top:30px;">
                        <button type="submit" class="btn" style="width:100%; max-width:300px; padding:12px; font-size:1.05rem;">Submit Task Assignment</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==================== MODALS ==================== -->
    
    <!-- 1. Status Update Modal -->
    <div id="modalStatusUpdate" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>⚡ Update Task Status</h3>
                <button class="modal-close" onclick="closeModal('modalStatusUpdate')">&times;</button>
            </div>
            <form id="statusUpdateForm">
                <input type="hidden" id="statusUpdateTaskId" name="task_id">
                <div class="modal-body">
                    <div class="form-group">
                        <label for="statusSelect">Select Current Status <span style="color:var(--danger)">*</span></label>
                        <select id="statusSelect" name="status" class="form-control" required>
                            <option value="Pending">Pending (Waiting response)</option>
                            <option value="Accepted">Accepted (Awaiting action)</option>
                            <option value="In Progress">In Progress (Active execution)</option>
                            <option value="On Hold">On Hold (Waiting parts/delay)</option>
                            <option value="Completed">Completed (Work finished)</option>
                            <option value="Rejected">Rejected (Cancelled/Failed)</option>
                        </select>
                    </div>
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="statusRemarks">Remarks / Progress Remarks (Optional)</label>
                        <textarea id="statusRemarks" name="remarks" class="form-control" rows="3" placeholder="Enter comments or details on current work progress..."></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#6c757d; box-shadow:none;" onclick="closeModal('modalStatusUpdate')">Cancel</button>
                    <button type="submit" class="btn" style="box-shadow:none;">Update Status</button>
                </div>
            </form>
        </div>
    </div>

    <!-- 2. Reassign Engineer Modal -->
    <div id="modalReassignTask" class="modal-overlay">
        <div class="modal-container">
            <div class="modal-header">
                <h3>🔄 Reassign Task Owner</h3>
                <button class="modal-close" onclick="closeModal('modalReassignTask')">&times;</button>
            </div>
            <form id="reassignTaskForm">
                <input type="hidden" id="reassignTaskId" name="task_id">
                <div class="modal-body">
                    <div class="form-group" style="margin-bottom: 0;">
                        <label for="reassignSelect">Assign New Engineer <span style="color:var(--danger)">*</span></label>
                        <select id="reassignSelect" name="assigned_to" class="form-control" required>
                            <option value="Suraj">Suraj</option>
                            <option value="Akshar">Akshar</option>
                            <option value="Karan">Karan</option>
                            <option value="Rahul">Rahul</option>
                            <option value="Paresh">Paresh</option>
                            <option value="Om">Om</option>
                            <option value="Jatin">Jatin</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn" style="background:#6c757d; box-shadow:none;" onclick="closeModal('modalReassignTask')">Cancel</button>
                    <button type="submit" class="btn" style="background:#8b5cf6; box-shadow:none;">Reassign Owner</button>
                </div>
            </form>
        </div>
    </div>

    <script src="assets/js/task-management.js"></script>
</body>
</html>
