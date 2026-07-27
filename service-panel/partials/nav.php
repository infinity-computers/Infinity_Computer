<?php
/**
 * Shared Navigation Partial
 * Usage: include __DIR__ . '/../partials/nav.php';
 *        Set $activeNav before including:
 *          'track'    => Track Service
 *          'dashboard' => Dashboard
 *          'crm'      => CRM Analytics
 *          'billing'  => Billing
 *          'reports'  => Reports (sub of billing dropdown)
 *          'tasks'    => Task Management
 *          'staff'    => Manage Staff
 */

if (!isset($activeNav)) $activeNav = '';

$isAdmin = function_exists('isAdmin') ? isAdmin() : false;
$isSuperAdmin = function_exists('isSuperAdmin') ? isSuperAdmin() : false;
$staffEmail = $_SESSION['staff_email'] ?? '';

// Billing dropdown active when on billing OR reports
$billingDropdownActive = in_array($activeNav, ['billing', 'reports']);
?>
<header>
    <div class="container" style="padding:0;">
        <a href="../index.html" style="display:flex;align-items:center;gap:0.6rem;text-decoration:none;flex-shrink:0;">
            <img src="../images/logos/infinity_computer_logo.png" alt="Infinity Computer Logo" style="height:38px;width:auto;">
            <div style="display:flex;flex-direction:column;align-items:flex-start;line-height:1;">
                <span class="brand-text">Infinity<span class="text-accent">Computer</span></span>
                <span style="font-size:0.65rem;color:#fb2a71;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;">Service Panel</span>
            </div>
        </a>

        <!-- Hamburger toggle for mobile -->
        <button class="nav-hamburger" id="navHamburger" aria-label="Toggle navigation" onclick="toggleMobileNav()">
            <span></span><span></span><span></span>
        </button>

        <ul class="nav-links" id="mainNavLinks">
            <li><a href="index.php" <?= $activeNav === 'track' ? 'class="header-active"' : '' ?>>Track Service</a></li>
            <li><a href="dashboard.php" <?= $activeNav === 'dashboard' ? 'class="header-active"' : '' ?>>Dashboard</a></li>

            <?php if ($isAdmin): ?>
            <li><a href="crm.php" <?= $activeNav === 'crm' ? 'class="header-active"' : '' ?>>CRM Analytics</a></li>

            <!-- Billing & Reports dropdown -->
            <li class="nav-dropdown <?= $billingDropdownActive ? 'dropdown-active' : '' ?>">
                <a href="billing.php" class="nav-dropdown-toggle <?= $billingDropdownActive ? 'header-active' : '' ?>" onclick="toggleNavDropdown(event)">
                    Billing &amp; Reports
                    <svg class="dropdown-arrow" viewBox="0 0 10 6" width="10" height="6" fill="none">
                        <path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                    </svg>
                </a>
                <ul class="nav-dropdown-menu">
                    <li>
                        <a href="billing.php" <?= $activeNav === 'billing' ? 'class="dropdown-item-active"' : '' ?>>
                            💳 Billing
                        </a>
                    </li>
                    <li>
                        <a href="reports.php" <?= $activeNav === 'reports' ? 'class="dropdown-item-active"' : '' ?>>
                            📊 Reports
                        </a>
                    </li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="task_management.php" <?= $activeNav === 'tasks' ? 'class="header-active"' : '' ?>>Task Management</a></li>

            <?php if ($isAdmin): ?>
            <li><a href="engineers_management.php" <?= $activeNav === 'staff' ? 'class="header-active"' : '' ?>>Manage Staff</a></li>
            <?php endif; ?>

            <li><a href="logout.php">Logout</a></li>
        </ul>
    </div>
</header>

<script>
function toggleMobileNav() {
    const nav = document.getElementById('mainNavLinks');
    const btn = document.getElementById('navHamburger');
    nav.classList.toggle('nav-open');
    btn.classList.toggle('is-open');
}
function toggleNavDropdown(e) {
    const li = e.currentTarget.closest('.nav-dropdown');
    const isOpen = li.classList.contains('is-open');
    // Close all dropdowns first
    document.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('is-open'));
    if (!isOpen) {
        li.classList.add('is-open');
        e.preventDefault();
    }
}
// Close dropdown on outside click
document.addEventListener('click', function(e) {
    if (!e.target.closest('.nav-dropdown')) {
        document.querySelectorAll('.nav-dropdown').forEach(d => d.classList.remove('is-open'));
    }
});
</script>
