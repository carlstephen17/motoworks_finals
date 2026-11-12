<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if (!isStaff($_SESSION['user_id'])) {
    header('Location: ' . (isAdmin($_SESSION['user_id']) ? '../admin/admin_index.php' : '../index.php'));
    exit();
}

$overviewStats = [
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM product')->fetchColumn(),
    'orders' => (int) $pdo->query('SELECT COUNT(*) FROM customer_order')->fetchColumn(),
    'appointments' => (int) $pdo->query('SELECT COUNT(*) FROM service_booking')->fetchColumn(),
    'promo_claims' => (int) $pdo->query('SELECT COUNT(*) FROM promo_claim')->fetchColumn(),
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Staff Dashboard - MotoWorks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/staff_layout.css">
    <link rel="stylesheet" href="css/staff_dashboard.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/staff_nav.php'; ?>
    <main class="admin-main">
        <?php
        $staffPageTitle = 'Staff Dashboard';
        include __DIR__ . '/includes/staff_header.php';
        ?>
        <div class="admin-content-body">
            <section class="staff-overview" aria-label="Business overview">
                <div class="staff-overview-section">
                    <h3>Inventory &amp; Catalog</h3>
                    <div class="dashboard-grid staff-overview-grid">
                        <div class="stat-card">
                            <div class="stat-icon bg-services"><i class="fas fa-boxes-stacked"></i></div>
                            <div class="stat-info"><h3><?= $overviewStats['products'] ?></h3><p>Total Products</p></div>
                        </div>
                    </div>
                </div>
                <div class="staff-overview-section">
                    <h3>Operations &amp; Business</h3>
                    <div class="dashboard-grid staff-overview-grid">
                        <?php foreach ([
                            'orders' => ['Total Orders', 'fa-box-open', 'staff_orders.php'],
                            'appointments' => ['Total Service Appointments', 'fa-calendar-check', 'staff_appointments.php'],
                            'promo_claims' => ['Total Promo History', 'fa-tags', 'staff_promo_history.php'],
                        ] as $key => [$label, $icon, $href]): ?>
                            <a class="stat-card staff-stat-link" href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>">
                                <div class="stat-icon bg-orders"><i class="fas <?= $icon ?>"></i></div>
                                <div class="stat-info"><h3><?= $overviewStats[$key] ?></h3><p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p></div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="js/staff.js" defer></script>
</body>
</html>
