<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$paymentFilter = trim((string) ($_GET['payment_status'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 50;

$validStatuses = ['Pending', 'Cancelled', 'Completed'];
$validPaymentStatuses = ['Due on delivery', 'Pending', 'Paid'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
foreach (['dateFrom' => $dateFrom, 'dateTo' => $dateTo] as $key => $date) {
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        ${$key} = '';
    }
}
if (!in_array($paymentFilter, $validPaymentStatuses, true)) {
    $paymentFilter = '';
}

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = "(
        CAST(co.orderGroupId AS CHAR) LIKE ?
        OR CONCAT_WS(' ', u.userFirstName, u.userLastName) LIKE ?
        OR u.userEmail LIKE ?
        OR u.userPhone LIKE ?
        OR EXISTS (
            SELECT 1
            FROM `order` oi
            LEFT JOIN product pi ON pi.productId = oi.productId
            WHERE oi.orderGroupId = co.orderGroupId
              AND pi.productName LIKE ?
        )
    )";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($statusFilter !== '') {
    if ($statusFilter === 'Pending') {
        $conditions[] = "co.orderStatus IN ('Pending', 'Confirmed', 'Processing', 'Shipped')";
    } else {
        $conditions[] = 'co.orderStatus = ?';
    }
}
if ($statusFilter !== '' && $statusFilter !== 'Pending') {
    $params[] = $statusFilter;
}
if ($paymentFilter !== '') {
    $conditions[] = 'co.orderPaymentStatus = ?';
    $params[] = $paymentFilter;
}
if ($dateFrom !== '') {
    $conditions[] = 'DATE(co.orderCreatedAt) >= ?';
    $params[] = $dateFrom;
}
if ($dateTo !== '') {
    $conditions[] = 'DATE(co.orderCreatedAt) <= ?';
    $params[] = $dateTo;
}
$where = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM customer_order co
    INNER JOIN `user` u ON u.userId = co.userId
    {$where}
");
$countStmt->execute($params);
$totalOrders = (int) $countStmt->fetchColumn();
$totalPages = max(1, (int) ceil($totalOrders / $perPage));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

$ordersStmt = $pdo->prepare("
    SELECT co.orderGroupId, co.userId, co.orderDeliveryAddress, co.orderPaymentMethod,
           co.orderPaymentReference, co.orderPaymentStatus, co.orderStatus,
           co.orderCancellationReason, co.orderCreatedAt,
           u.userFirstName, u.userLastName, u.userEmail, u.userPhone,
           COUNT(oi.orderId) AS item_count, COALESCE(SUM(oi.orderTotalAmount), 0) AS order_total
    FROM customer_order co
    INNER JOIN `user` u ON u.userId = co.userId
    LEFT JOIN `order` oi ON oi.orderGroupId = co.orderGroupId
    {$where}
    GROUP BY co.orderGroupId, co.userId, co.orderDeliveryAddress,
             co.orderPaymentMethod, co.orderPaymentReference, co.orderPaymentStatus,
             co.orderStatus, co.orderCancellationReason, co.orderCreatedAt,
             u.userFirstName, u.userLastName, u.userEmail, u.userPhone
    ORDER BY co.orderCreatedAt DESC, co.orderGroupId DESC
    LIMIT {$perPage} OFFSET {$offset}
");
$ordersStmt->execute($params);
$orders = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

$itemsByOrder = [];
$orderIds = array_map(static fn(array $order): int => (int) $order['orderGroupId'], $orders);
if ($orderIds) {
    $placeholders = implode(',', array_fill(0, count($orderIds), '?'));
    $itemsStmt = $pdo->prepare("
        SELECT oi.orderGroupId, oi.productId, oi.orderQuantity, oi.orderTotalAmount,
               COALESCE(oi.orderUnitPrice, oi.orderTotalAmount / GREATEST(oi.orderQuantity, 1)) AS unit_price,
               p.productName, p.productImage
        FROM `order` oi
        LEFT JOIN product p ON p.productId = oi.productId
        WHERE oi.orderGroupId IN ({$placeholders})
        ORDER BY oi.orderGroupId, oi.orderId
    ");
    $itemsStmt->execute($orderIds);
    foreach ($itemsStmt->fetchAll(PDO::FETCH_ASSOC) as $item) {
        $itemsByOrder[(int) $item['orderGroupId']][] = $item;
    }
}

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(orderStatus IN ('Pending', 'Confirmed', 'Processing', 'Shipped')) AS pending,
           SUM(orderStatus = 'Cancelled') AS cancelled,
           SUM(orderStatus = 'Completed') AS completed
    FROM customer_order
")->fetch(PDO::FETCH_ASSOC) ?: [];
$csrfToken = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
$filters = [
    'search' => $search,
    'status' => $statusFilter,
    'payment_status' => $paymentFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$pageUrl = static fn(int $targetPage): string => 'admin_orders.php?' . http_build_query($filters + ['page' => $targetPage]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>MotoWorks Admin - Customer Orders</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
    <link rel="stylesheet" href="css/admin_dashboard.css">
    <style>
        .order-filters { display:grid; grid-template-columns:minmax(220px,2fr) repeat(4,minmax(130px,1fr)) auto; gap:12px; align-items:end; background:#fff; padding:18px; border:1px solid #e2e8f0; border-radius:8px; margin-bottom:18px; }
        .order-filters .form-group { margin:0; }
        .order-items { min-width:190px; }
        .order-item { display:flex; align-items:center; gap:8px; margin-bottom:5px; }
        .order-item img { width:36px; height:36px; object-fit:cover; border-radius:6px; }
        .order-details-modal { position:fixed; inset:0; margin:auto; width:min(560px,calc(100vw - 32px)); max-height:90vh; padding:0; overflow:auto; border:0; border-radius:10px; box-shadow:0 20px 60px rgba(15,23,42,.3); }
        .order-details-modal::backdrop { background:rgba(15,23,42,.58); }
        .order-details-modal-header { display:flex; align-items:center; justify-content:space-between; padding:16px 20px; border-bottom:1px solid #e2e8f0; }
        .order-details-modal-header h3 { margin:0; }
        .order-details-body { padding:20px; white-space:normal; }
        .order-details-body ul { padding-left:20px; margin:8px 0; }
        .order-update-form { display:grid; gap:10px; margin-top:14px; }
        .order-pagination { display:flex; justify-content:space-between; align-items:center; padding:16px 4px; color:#64748b; }
        [hidden] { display:none !important; }
        @media (max-width:1000px) { .order-filters { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:600px) { .order-filters { grid-template-columns:1fr; } .order-pagination { align-items:flex-start; flex-direction:column; gap:10px; } }
    </style>
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/admin_sidebar.php'; ?>
    <main class="admin-main">
        <header class="main-header">
            <h2>Customer Orders</h2>
            <div class="admin-user-info"><span>Shared customer checkout orders</span></div>
        </header>
        <div class="admin-content-body">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert-toast" role="status"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>
            <div class="order-stats">
                <?php foreach ([
                    'total' => 'All Orders',
                    'pending' => 'Pending',
                    'cancelled' => 'Cancelled',
                    'completed' => 'Completed',
                ] as $key => $label): ?>
                    <div class="stat-card">
                        <div class="stat-info"><h3><?= (int) ($stats[$key] ?? 0) ?></h3><p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p></div>
                    </div>
                <?php endforeach; ?>
            </div>
            <form method="GET" class="order-filters">
                <div class="form-group">
                    <label for="order-search">Search order, customer, or product</label>
                    <input class="form-control" id="order-search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Order ID, name, email, phone, product">
                </div>
                <div class="form-group">
                    <label for="order-status">Order status</label>
                    <select class="form-control" id="order-status" name="status">
                        <option value="">All statuses</option>
                        <?php foreach ($validStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label for="payment-status">Payment status</label>
                    <select class="form-control" id="payment-status" name="payment_status">
                        <option value="">All payment states</option>
                        <?php foreach ($validPaymentStatuses as $status): ?>
                            <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $paymentFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group"><label for="date-from">From</label><input class="form-control" id="date-from" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>"></div>
                <div class="form-group"><label for="date-to">To</label><input class="form-control" id="date-to" type="date" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>"></div>
                <button class="btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
            </form>

            <div class="table-responsive">
                <table class="admin-table">
                    <thead><tr><th>Order</th><th>Customer</th><th>Products</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th><th>Actions</th></tr></thead>
                    <tbody>
                    <?php if (!$orders): ?><tr><td class="text-center" colspan="8">No orders match the selected filters.</td></tr><?php endif; ?>
                    <?php foreach ($orders as $order): ?>
                        <?php
                        $orderId = (int) $order['orderGroupId'];
                        $items = $itemsByOrder[$orderId] ?? [];
                        $currentStatus = strtolower(trim((string) $order['orderStatus']));
                        $displayStatus = in_array($currentStatus, ['pending', 'cancelled', 'completed'], true)
                            ? ucfirst($currentStatus)
                            : 'Pending';
                        $editable = $displayStatus !== 'Completed';
                        $statusOptions = $editable ? $validStatuses : [];
                        ?>
                        <tr>
                            <td><strong>#<?= $orderId ?></strong><br><small><?= (int) $order['item_count'] ?> item(s)</small></td>
                            <td><?= htmlspecialchars(trim($order['userFirstName'] . ' ' . $order['userLastName']), ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($order['userEmail'], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string) $order['userPhone'], ENT_QUOTES, 'UTF-8') ?></small></td>
                            <td class="order-items">
                                <?php foreach ($items as $item): ?>
                                    <?php
                                    $image = (string) ($item['productImage'] ?? '');
                                    $imageUrl = preg_match('~^(https?:)?//~i', $image) || str_starts_with($image, '/') ? $image : '../' . ltrim($image, '/');
                                    ?>
                                    <div class="order-item">
                                        <?php if ($image !== ''): ?><img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt=""><?php endif; ?>
                                        <span><?= htmlspecialchars($item['productName'] ?? 'Product #' . $item['productId'], ENT_QUOTES, 'UTF-8') ?> × <?= (int) $item['orderQuantity'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            </td>
                            <td>₱<?= number_format((float) $order['order_total'], 2) ?></td>
                            <td><?= htmlspecialchars($order['orderPaymentMethod'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8') ?><br><span class="badge badge-secondary"><?= htmlspecialchars($order['orderPaymentStatus'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><span class="badge <?= $displayStatus === 'Completed' ? 'badge-success' : ($displayStatus === 'Cancelled' ? 'badge-danger' : 'badge-secondary') ?>"><?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td><?= htmlspecialchars($order['orderCreatedAt'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="order-action-cell">
                                <div class="table-row-actions">
                                <button type="button" class="btn-sm btn-secondary" data-open-order-details="order-details-<?= $orderId ?>"><i class="fas fa-eye"></i> View</button>
                                <?php if ($displayStatus !== 'Completed'): ?>
                                    <button type="button" class="btn-sm btn-info" data-edit-order-details="order-details-<?= $orderId ?>"><i class="fas fa-edit"></i> Edit</button>
                                <?php endif; ?>
                                <form method="POST" action="actions/admin_order_delete.php" onsubmit="return confirm('Permanently delete order #<?= $orderId ?>? Pending inventory will be restored.');">
                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                    <input type="hidden" name="id" value="<?= $orderId ?>">
                                    <button type="submit" class="btn-sm btn-danger"><i class="fas fa-trash"></i> Delete</button>
                                </form>
                                </div>
                                <dialog class="order-details-modal" id="order-details-<?= $orderId ?>" aria-labelledby="order-details-title-<?= $orderId ?>">
                                    <div class="order-details-modal-header">
                                        <h3 id="order-details-title-<?= $orderId ?>">Order #<?= $orderId ?> Details</h3>
                                        <button class="btn-sm btn-danger" type="button" data-close-order-details aria-label="Close"><i class="fas fa-times"></i></button>
                                    </div>
                                    <div class="order-details-body">
                                        <strong>Delivery address</strong>
                                        <p><?= nl2br(htmlspecialchars((string) ($order['orderDeliveryAddress'] ?? 'Not recorded'), ENT_QUOTES, 'UTF-8')) ?></p>
                                        <strong>Items</strong>
                                        <ul>
                                            <?php foreach ($items as $item): ?>
                                                <li><?= htmlspecialchars($item['productName'] ?? 'Product #' . $item['productId'], ENT_QUOTES, 'UTF-8') ?> — <?= (int) $item['orderQuantity'] ?> × ₱<?= number_format((float) $item['unit_price'], 2) ?> = ₱<?= number_format((float) $item['orderTotalAmount'], 2) ?></li>
                                            <?php endforeach; ?>
                                        </ul>
                                        <p>Subtotal and total: <strong>₱<?= number_format((float) $order['order_total'], 2) ?></strong><br>
                                            Delivery fee: ₱0.00 (not configured)<br>
                                            Payment reference: <?= htmlspecialchars($order['orderPaymentReference'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8') ?><br>
                                            Payment status: <?= htmlspecialchars($order['orderPaymentStatus'], ENT_QUOTES, 'UTF-8') ?></p>
                                        <?php if (!empty($order['orderCancellationReason'])): ?><p>Cancellation reason: <?= nl2br(htmlspecialchars($order['orderCancellationReason'], ENT_QUOTES, 'UTF-8')) ?></p><?php endif; ?>
                                        <?php if ($editable): ?>
                                            <form method="POST" action="actions/admin_order_update.php" class="order-update-form">
                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                                <label>Update order status
                                                    <select class="form-control" name="status" required data-order-status data-current-status="<?= htmlspecialchars($displayStatus, ENT_QUOTES, 'UTF-8') ?>" id="order-status-<?= $orderId ?>">
                                                        <?php foreach ($statusOptions as $nextStatus): ?><option value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>" <?= $nextStatus === $displayStatus ? 'selected' : '' ?>><?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?>
                                                    </select>
                                                </label>
                                                <label class="cancellation-reason">Cancellation reason<textarea class="form-control" name="cancellation_reason" maxlength="500"></textarea></label>
                                                <button class="btn-primary" type="submit">Save status</button>
                                            </form>
                                        <?php endif; ?>
                                    </div>
                                </dialog>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($totalPages > 1): ?>
                <nav class="order-pagination" aria-label="Order pages">
                    <span>Page <?= $page ?> of <?= $totalPages ?> · <?= $totalOrders ?> orders</span>
                    <div>
                        <?php if ($page > 1): ?><a class="btn-sm btn-info" href="<?= htmlspecialchars($pageUrl($page - 1), ENT_QUOTES, 'UTF-8') ?>">Previous</a><?php endif; ?>
                        <?php if ($page < $totalPages): ?><a class="btn-sm btn-info" href="<?= htmlspecialchars($pageUrl($page + 1), ENT_QUOTES, 'UTF-8') ?>">Next</a><?php endif; ?>
                    </div>
                </nav>
            <?php endif; ?>
        </div>
    <script>
    document.addEventListener('click', (event) => {
        const viewButton = event.target.closest('[data-open-order-details]');
        const editButton = event.target.closest('[data-edit-order-details]');
        const closeButton = event.target.closest('[data-close-order-details]');
        if (closeButton) {
            closeButton.closest('dialog')?.close();
            return;
        }
        const button = viewButton || editButton;
        if (!button) return;
        const details = document.getElementById(button.dataset.openOrderDetails || button.dataset.editOrderDetails);
        if (!details) return;
        details.showModal();
        if (editButton) {
            details.querySelector('select[name="status"]')?.focus();
        }
    });
    </script>
    </main>
</div>
<script>
document.querySelectorAll('[data-order-status]').forEach((select) => {
    const field = select.closest('form').querySelector('.cancellation-reason');
    const reason = field.querySelector('textarea');
    const sync = () => {
        field.hidden = select.value !== 'Cancelled';
        reason.required = select.value === 'Cancelled' && select.dataset.currentStatus !== 'Cancelled';
        if (select.value !== 'Cancelled') {
            reason.value = '';
        }
    };
    select.addEventListener('change', sync);
    sync();
});
</script>
</body>
</html>
