<?php
include __DIR__ . '/auth_guard.php';
require_once __DIR__ . '/config/db.php';

// Check if user is admin
$admins = ['icc@infinitycomputer.in', 'suraj@staff.infinitycomputer.in'];
if (!in_array($_SESSION['staff_email'] ?? '', $admins)) {
    die("<h2>Access Denied</h2><p>You do not have permission to view this page. This page is restricted to administrators.</p><a href='index.php'>Go back</a>");
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Engineers Management - Infinity Computer</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <style>
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .eng-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            box-shadow: 0 4px 6px rgba(0,0,0,0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        
        .eng-table th, .eng-table td {
            padding: 15px 20px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
        }
        
        .eng-table th {
            background: #f8fafc;
            font-weight: 600;
            color: var(--text-dark);
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        
        .eng-table tr:hover {
            background: #f1f5f9;
        }
        
        .action-btn {
            background: none;
            border: none;
            cursor: pointer;
            font-size: 1.1rem;
            margin-right: 10px;
            color: #64748b;
            transition: color 0.2s;
        }
        
        .action-btn:hover { color: var(--primary-color); }
        .action-btn.delete:hover { color: var(--danger); }
        
        /* Modal Styles */
        .modal-overlay {
            position: fixed;
            top: 0; left: 0; right: 0; bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            justify-content: center;
            align-items: center;
            z-index: 1000;
            backdrop-filter: blur(4px);
        }
        
        .modal {
            background: #fff;
            padding: 30px;
            border-radius: 12px;
            width: 90%;
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }
        
        .modal-title { font-size: 1.25rem; font-weight: 600; margin: 0; }
        .close-btn { background: none; border: none; font-size: 1.5rem; cursor: pointer; color: #94a3b8; }
        
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: 500; }
        .form-control { width: 100%; padding: 10px 15px; border: 1px solid var(--border-color); border-radius: 6px; font-family: inherit; }
        
        .toast {
            position: fixed;
            bottom: 20px;
            right: 20px;
            padding: 15px 25px;
            background: #333;
            color: #fff;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(100px);
            opacity: 0;
            transition: all 0.3s;
            z-index: 2000;
        }
        .toast.show { transform: translateY(0); opacity: 1; }
        .toast-success { background: #10b981; }
        .toast-error { background: #ef4444; }
    </style>
</head>
<body>
    <header>
        <div class="container" style="padding:0;">
            <a href="../index.html" style="display: flex; align-items: center; gap: 0.6rem; text-decoration: none;">
                <img src="../images/logos/infinity_computer_logo.png" alt="Infinity Computer Logo" style="height: 38px; width: auto;">
                <div style="display: flex; flex-direction: column; align-items: flex-start; line-height: 1;">
                    <span class="brand-text">Infinity<span class="text-accent">Computer</span></span>
                    <span style="font-size: 0.65rem; color: #fb2a71; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px;">Service Panel</span>
                </div>
            </a>
            <ul class="nav-links">
                <li><a href="index.php">Track Service</a></li>
                <li><a href="index.php?tab=new-service">Add New Service</a></li>
                <li><a href="dashboard.php">Dashboard</a></li>
                <li><a href="crm.php">CRM Analytics</a></li>
                <li><a href="task_management.php">Task Management</a></li>
                <li><a href="engineers_management.php" class="header-active" style="color: var(--primary-color);">Manage Engineers</a></li>
                <li><a href="logout.php" style="color: #dc3545; font-weight: 600; border: 1px solid #dc3545; border-radius: 5px; padding: 5px 12px; margin-left: 10px; text-decoration: none;">Logout</a></li>
            </ul>
        </div>
    </header>

    <div class="container" style="max-width: 1000px; margin-top: 40px; margin-bottom: 40px;">
        <div class="page-header">
            <h2>Engineers Management</h2>
            <button class="btn btn-primary" onclick="openAddModal()">+ Add Engineer</button>
        </div>

        <table class="eng-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="engTableBody">
                <tr><td colspan="4" style="text-align:center;">Loading...</td></tr>
            </tbody>
        </table>
    </div>

    <!-- Add/Edit Modal -->
    <div class="modal-overlay" id="engModal">
        <div class="modal">
            <div class="modal-header">
                <h3 class="modal-title" id="modalTitle">Add Engineer</h3>
                <button class="close-btn" onclick="closeModal()">&times;</button>
            </div>
            <form id="engForm" onsubmit="handleEngSubmit(event)">
                <input type="hidden" id="engId" name="id">
                <div class="form-group">
                    <label>Engineer Name</label>
                    <input type="text" id="engName" name="name" class="form-control" required>
                </div>
                <div class="form-group">
                    <label>Email Address (Optional)</label>
                    <input type="email" id="engEmail" name="email" class="form-control">
                </div>
                <div style="margin-top: 25px; text-align: right;">
                    <button type="button" class="btn" style="background: #e2e8f0; color: #333;" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save Engineer</button>
                </div>
            </form>
        </div>
    </div>

    <div id="toast" class="toast"></div>

    <script>
        document.addEventListener('DOMContentLoaded', loadEngineers);

        async function loadEngineers() {
            try {
                const res = await fetch('api/engineers_api.php');
                const result = await res.json();
                
                if (result.status === 'success') {
                    renderTable(result.data);
                } else {
                    showToast(result.message || 'Error loading engineers data', 'error');
                }
            } catch (err) {
                showToast('Network error loading engineers', 'error');
            }
        }

        function renderTable(engList) {
            const tbody = document.getElementById('engTableBody');
            tbody.innerHTML = '';
            
            if(engList.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center;">No engineers found</td></tr>';
                return;
            }

            engList.forEach(eng => {
                const tr = document.createElement('tr');
                
                tr.innerHTML = `
                    <td>${eng.id}</td>
                    <td style="font-weight: 500;">${eng.name}</td>
                    <td>${eng.email || '-'}</td>
                    <td>
                        <button class="action-btn edit" onclick='openEditModal(${JSON.stringify(eng)})' title="Edit">✏️</button>
                        <button class="action-btn delete" onclick="deleteEngineer(${eng.id}, '${eng.name}')" title="Delete">🗑️</button>
                    </td>
                `;
                tbody.appendChild(tr);
            });
        }

        function openAddModal() {
            document.getElementById('modalTitle').innerText = 'Add New Engineer';
            document.getElementById('engForm').reset();
            document.getElementById('engId').value = '';
            document.getElementById('engModal').style.display = 'flex';
        }

        function openEditModal(eng) {
            document.getElementById('modalTitle').innerText = 'Edit Engineer';
            document.getElementById('engId').value = eng.id;
            document.getElementById('engName').value = eng.name;
            document.getElementById('engEmail').value = eng.email || '';
            document.getElementById('engModal').style.display = 'flex';
        }

        function closeModal() {
            document.getElementById('engModal').style.display = 'none';
        }

        async function handleEngSubmit(e) {
            e.preventDefault();
            const btn = document.getElementById('saveBtn');
            btn.disabled = true;
            btn.innerText = 'Saving...';

            const formData = {
                id: document.getElementById('engId').value,
                name: document.getElementById('engName').value,
                email: document.getElementById('engEmail').value
            };

            try {
                const res = await fetch('api/engineers_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(formData)
                });
                const result = await res.json();
                
                if (result.status === 'success') {
                    showToast(result.message, 'success');
                    closeModal();
                    loadEngineers();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Network error while saving', 'error');
            }
            
            btn.disabled = false;
            btn.innerText = 'Save Engineer';
        }

        async function deleteEngineer(id, name) {
            if (!confirm(`Are you sure you want to delete ${name}?`)) {
                return;
            }

            try {
                const res = await fetch('api/engineers_api.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: id, action: 'delete' })
                });
                const result = await res.json();
                
                if (result.status === 'success') {
                    showToast(result.message, 'success');
                    loadEngineers();
                } else {
                    showToast(result.message, 'error');
                }
            } catch (err) {
                showToast('Network error while deleting', 'error');
            }
        }

        function showToast(message, type = 'success') {
            const toast = document.getElementById('toast');
            toast.className = `toast toast-${type}`;
            toast.innerText = message;
            toast.classList.add('show');
            
            setTimeout(() => {
                toast.classList.remove('show');
            }, 3000);
        }
    </script>
</body>
</html>
