<?php
require_once 'config.php';
checkAdminLogin();

// Quick Stats
$studentCount = $conn->query("SELECT COUNT(*) FROM students WHERE verification = 'Successful'")->fetch_row()[0];
$pendingStudentCount = $conn->query("SELECT COUNT(*) FROM students WHERE verification = 'Pending'")->fetch_row()[0];
$laptopCount = $conn->query("SELECT COUNT(*) FROM second_hand_laptop_requests WHERE status = 'Verified'")->fetch_row()[0];
$pendingLaptopCount = $conn->query("SELECT COUNT(*) FROM second_hand_laptop_requests WHERE status = 'Pending'")->fetch_row()[0];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Hub - Infinity Computer</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;600;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #fb2a71;
            --primary-hover: #ff0000;
            --secondary-color: #111111;
            --main-bg: #f8fafc;
        }

        body {
            font-family: 'Outfit', sans-serif;
            background-color: var(--main-bg);
            color: #334155;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        .navbar {
            background-color: #fff;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border-bottom: 3px solid var(--primary-color);
        }

        .hub-container {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .hub-card {
            background: #fff;
            border-radius: 24px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.05);
            padding: 3rem;
            max-width: 1000px;
            width: 100%;
        }

        .module-card {
            background: #f8fafc;
            border-radius: 20px;
            padding: 2rem;
            transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
            border: 2px solid transparent;
            text-decoration: none;
            color: inherit;
            display: block;
            height: 100%;
            position: relative;
            overflow: hidden;
        }

        .module-card:hover {
            transform: translateY(-10px);
            border-color: var(--primary-color);
            background: #fff;
            box-shadow: 0 15px 30px rgba(251, 42, 113, 0.1);
        }

        .module-icon {
            width: 70px;
            height: 70px;
            background: var(--primary-color);
            color: #fff;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            margin-bottom: 1.5rem;
            transition: all 0.3s ease;
        }

        .module-card:hover .module-icon {
            transform: scale(1.1) rotate(-5deg);
            background: var(--secondary-color);
        }

        .module-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--secondary-color);
            margin-bottom: 0.75rem;
        }

        .module-desc {
            color: #64748b;
            font-size: 0.95rem;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .stat-badge {
            display: inline-flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 600;
            margin-right: 0.5rem;
        }

        .stat-badge i {
            margin-right: 0.5rem;
        }

        .bg-light-primary { background: rgba(251, 42, 113, 0.1); color: var(--primary-color); }
        .bg-light-warning { background: rgba(255, 193, 7, 0.1); color: #856404; }

        .brand-logo {
            height: 40px;
            width: auto;
        }

        .brand-text {
            font-size: 1.25rem;
            font-weight: 800;
            background: linear-gradient(90deg, #fb2a71, #ff0000);
            -webkit-background-clip: text;
            background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .welcome-text {
            text-align: center;
            margin-bottom: 3.5rem;
        }

        .welcome-text h1 {
            font-weight: 800;
            color: var(--secondary-color);
            font-size: 2.5rem;
        }
    </style>
</head>
<body>

    <nav class="navbar navbar-expand-lg navbar-light sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand ms-3 d-flex align-items-center text-decoration-none" href="admin_dashboard.php">
                <img src="../images/logos/infinity_computer_logo.png" alt="Infinity Computer Logo" class="brand-logo">
                <span class="brand-text ms-2">Infinity<span style="-webkit-text-fill-color: #111; color: #111;">Computer</span></span>
            </a>
            <div class="d-flex align-items-center me-3">
                <span class="text-muted small me-3">Logged in as Administrator</span>
                <a href="logout.php" class="btn btn-outline-danger btn-sm rounded-pill px-3">Logout</a>
            </div>
        </div>
    </nav>

    <div class="hub-container">
        <div class="hub-card">
            <div class="welcome-text">
                <h1>Admin Command Center</h1>
                <p class="text-muted">Welcome back! Please select a system to manage.</p>
            </div>
            <div class="row g-4">
                <!-- Students Module -->
                <div class="col-md-6">
                    <div class="module-card">
                        <a href="student_management.php" class="text-decoration-none">
                            <div class="module-icon">
                                <i class="fa-solid fa-user-graduate"></i>
                            </div>
                            <h2 class="module-title">Student System</h2>
                            <p class="module-desc">Manage student enrollments, pending verifications, course details, and qualification records.</p>
                        </a>
                        
                        <div class="mt-auto">
                            <a href="student_management.php?status=Successful" class="stat-badge bg-light-primary text-decoration-none">
                                <i class="fa-solid fa-users"></i> <?php echo $studentCount; ?> Enrolled
                            </a>
                            <a href="student_management.php?status=Pending" class="stat-badge bg-light-warning text-decoration-none">
                                <i class="fa-solid fa-clock"></i> <?php echo $pendingStudentCount; ?> Pending
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Laptops Module -->
                <div class="col-md-6">
                    <div class="module-card">
                        <a href="laptop_requests.php" class="text-decoration-none">
                            <div class="module-icon">
                                <i class="fa-solid fa-laptop-medical"></i>
                            </div>
                            <h2 class="module-title">Gadget Buyback</h2>
                            <p class="module-desc">Handle second-hand Gadget selling requests, verify device details, and manage buyback workflow.</p>
                        </a>
                        
                        <div class="mt-auto">
                            <a href="laptop_requests.php?status=Verified" class="stat-badge bg-light-primary text-decoration-none">
                                <i class="fa-solid fa-check-double"></i> <?php echo $laptopCount; ?> Verified
                            </a>
                            <a href="laptop_requests.php?status=Pending" class="stat-badge bg-light-warning text-decoration-none">
                                <i class="fa-solid fa-hourglass-half"></i> <?php echo $pendingLaptopCount; ?> New
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="text-center mt-5 pt-4 border-top">
                <p class="small text-muted mb-0">Infinity Computer &copy; 2026 Dashboard v2.0 | System Secure</p>
            </div>
        </div>
    </div>

    <!-- Bootstrap 5 JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
