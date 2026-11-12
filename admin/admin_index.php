<?php
// /admin/admin_index.php
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/config.php';
/** @var PDO $pdo */
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$totalProducts = $pdo->query("SELECT COUNT(*) FROM product")->fetchColumn();
$totalUsers = $pdo->query("SELECT COUNT(*) FROM `user`")->fetchColumn();
$totalStaff = $pdo->query("SELECT COUNT(*) FROM `user` WHERE userRole = 'staff'")->fetchColumn();
$totalCustomers = $pdo->query("SELECT COUNT(*) FROM `user` WHERE userRole = 'customer'")->fetchColumn();
$totalOrders = $pdo->query("SELECT COUNT(*) FROM customer_order")->fetchColumn();
$totalAppointments = $pdo->query("SELECT COUNT(*) FROM service_booking")->fetchColumn();
$totalPromoClaims = $pdo->query("SELECT COUNT(*) FROM promo_claim")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Dashboard</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
</head>

<body>

    <div class="admin-layout">
        <?php include 'includes/admin_sidebar.php'; ?>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-main">
            <header class="main-header">
                <h2>Dashboard Overview</h2>
                <div class="admin-user-info">
                    <span>Welcome back, Admin</span>
                </div>
            </header>

            <div class="admin-content-body">
                <section class="management-overview">
                    <div class="management-section">
                        <div class="management-section-heading">
                            <h3>Business Overview / Users</h3>
                            <a href="admin_users.php">Manage users</a>
                        </div>
                        <div class="management-count-grid management-count-grid-three">
                            <a class="management-count-card" href="admin_users.php"><span class="management-count-label">Total Customers</span><strong><?= (int) $totalCustomers ?></strong></a>
                            <a class="management-count-card" href="admin_users.php"><span class="management-count-label">Total Staff</span><strong><?= (int) $totalStaff ?></strong></a>
                            <a class="management-count-card" href="admin_users.php"><span class="management-count-label">Total Users</span><strong><?= (int) $totalUsers ?></strong></a>
                        </div>
                    </div>

                    <div class="management-section">
                        <div class="management-section-heading">
                            <h3>Inventory / Catalog</h3>
                            <a href="admin_products.php">Manage products</a>
                        </div>
                        <div class="management-count-grid management-count-grid-three">
                            <a class="management-count-card" href="admin_products.php"><span class="management-count-label">Total Products</span><strong><?= (int) $totalProducts ?></strong></a>
                        </div>
                    </div>

                    <div class="management-section">
                        <div class="management-section-heading">
                            <h3>Operations &amp; Engagement</h3>
                        </div>
                        <div class="management-count-grid management-count-grid-three">
                            <a class="management-count-card" href="admin_orders.php"><span class="management-count-label">Total Orders</span><strong><?= (int) $totalOrders ?></strong></a>
                            <a class="management-count-card" href="admin_appointment.php"><span class="management-count-label">Total Service Appointments</span><strong><?= (int) $totalAppointments ?></strong></a>
                            <a class="management-count-card" href="admin_promo_history.php"><span class="management-count-label">Total Promo History</span><strong><?= (int) $totalPromoClaims ?></strong></a>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

</body>

</html>