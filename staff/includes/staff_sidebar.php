<?php
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<div class="staff-sidebar-backdrop" data-sidebar-backdrop></div>
<aside class="admin-sidebar" id="staff-sidebar" aria-label="Staff navigation">
    <div class="sidebar-brand">
        <a href="../index.php">
            <h2>MotoWorks Staff</h2>
        </a>
    </div>
    <ul class="sidebar-menu">
        <li class="sidebar-section-label" aria-hidden="true">Overview</li>
        <li class="<?= $currentPage === 'staff_index.php' ? 'active' : '' ?>">
            <a href="staff_index.php"><i class="fas fa-home"></i> Dashboard</a>
        </li>
        <li class="sidebar-section-label" aria-hidden="true">Operations</li>
        <li class="<?= $currentPage === 'staff_appointments.php' ? 'active' : '' ?>">
            <a href="staff_appointments.php"><i class="fas fa-calendar-check"></i> Appointments</a>
        </li>
        <li class="<?= $currentPage === 'staff_orders.php' ? 'active' : '' ?>">
            <a href="staff_orders.php"><i class="fas fa-box-open"></i> Orders</a>
        </li>
        <li class="<?= $currentPage === 'staff_promo_history.php' ? 'active' : '' ?>">
            <a href="staff_promo_history.php"><i class="fas fa-clock-rotate-left"></i> Promo History</a>
        </li>
        <li>
            <form method="POST" action="../logout.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit"><i class="fas fa-sign-out-alt"></i> Logout</button>
            </form>
        </li>
    </ul>
</aside>
