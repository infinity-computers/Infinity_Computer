<?php
/**
 * Shared Navigation Partial
 * Set $activeNav before including:
 *   'track' | 'dashboard' | 'crm' | 'billing' | 'reports' | 'tasks' | 'staff'
 */

if (!isset($activeNav)) $activeNav = '';

$isAdmin    = function_exists('isAdmin') ? isAdmin() : false;
$staffEmail = $_SESSION['staff_email'] ?? '';

// Billing dropdown is "active" when on billing OR reports
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
            <li class="nav-dropdown<?= $billingDropdownActive ? ' dropdown-active' : '' ?>" id="billingDropdownLi">
                <button class="nav-dropdown-btn<?= $billingDropdownActive ? ' header-active-btn' : '' ?>" onclick="toggleNavDropdown(event)" type="button">
                    Billing &amp; Reports
                    <svg class="dropdown-caret" viewBox="0 0 12 8" width="11" height="7" fill="none" aria-hidden="true">
                        <path d="M1 1.5l5 5 5-5" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </button>
                <ul class="nav-dropdown-menu">
                    <li><a href="billing.php" <?= $activeNav === 'billing' ? 'class="dropdown-item-active"' : '' ?>>Billing</a></li>
                    <li><a href="reports.php" <?= $activeNav === 'reports' ? 'class="dropdown-item-active"' : '' ?>>Reports</a></li>
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
(function() {
    function toggleMobileNav() {
        var nav = document.getElementById('mainNavLinks');
        var btn = document.getElementById('navHamburger');
        if (nav) nav.classList.toggle('nav-open');
        if (btn) btn.classList.toggle('is-open');
    }
    function toggleNavDropdown(e) {
        if (window.innerWidth > 900) {
            return; // On desktop, CSS hover handles this, so ignore click
        }
        e.stopPropagation();
        var li = e.currentTarget.closest('.nav-dropdown');
        if (!li) return;
        var isOpen = li.classList.contains('is-open');
        document.querySelectorAll('.nav-dropdown').forEach(function(d) { d.classList.remove('is-open'); });
        if (!isOpen) li.classList.add('is-open');
    }
    document.addEventListener('click', function(e) {
        if (window.innerWidth > 900) return;
        if (!e.target.closest('.nav-dropdown')) {
            document.querySelectorAll('.nav-dropdown').forEach(function(d) { d.classList.remove('is-open'); });
        }
    });
    window.toggleMobileNav  = toggleMobileNav;
    window.toggleNavDropdown = toggleNavDropdown;
})();
</script>
