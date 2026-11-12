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

$staffId = (int) $_SESSION['user_id'];
$csrfToken = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
$customers = $pdo->query("
    SELECT userId, userFirstName, userLastName, userPhone, userAddress
    FROM `user`
    WHERE userRole = 'customer'
    ORDER BY userFirstName, userLastName, userId
")->fetchAll(PDO::FETCH_ASSOC);
$products = $pdo->query("
    SELECT productId, productName, productPrice, productStock
    FROM product
    WHERE productActive = 1
    ORDER BY productName
")->fetchAll(PDO::FETCH_ASSOC);
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = trim((string) ($_GET['status'] ?? ''));
$paymentFilter = trim((string) ($_GET['payment_status'] ?? ''));
$dateFrom = trim((string) ($_GET['date_from'] ?? ''));
$dateTo = trim((string) ($_GET['date_to'] ?? ''));
$page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
$page = $page && $page > 0 ? $page : 1;
$perPage = 50;
$offset = ($page - 1) * $perPage;

$validStatuses = ['Pending', 'Cancelled', 'Completed'];
$validPaymentStatuses = ['Due on delivery', 'Unverified'];
if (!in_array($statusFilter, $validStatuses, true)) {
    $statusFilter = '';
}
if (!in_array($paymentFilter, $validPaymentStatuses, true)) {
    $paymentFilter = '';
}
foreach (['dateFrom' => $dateFrom, 'dateTo' => $dateTo] as $key => $date) {
    if ($date !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
        ${$key} = '';
    }
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
        $params[] = $statusFilter;
    }
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
           co.orderPaymentReference, co.orderPaymentStatus,
           CASE
               WHEN co.orderStatus IN ('Confirmed', 'Processing', 'Shipped') THEN 'Pending'
               ELSE co.orderStatus
           END AS orderStatus,
           co.orderCancellationReason, co.orderCreatedAt, co.orderCreatedByStaffId,
           u.userFirstName, u.userLastName, u.userEmail, u.userPhone,
           COUNT(oi.orderId) AS item_count, COALESCE(SUM(oi.orderTotalAmount), 0) AS order_total
    FROM customer_order co
    INNER JOIN `user` u ON u.userId = co.userId
    LEFT JOIN `order` oi ON oi.orderGroupId = co.orderGroupId
    {$where}
    GROUP BY co.orderGroupId, co.userId, co.orderDeliveryAddress,
             co.orderPaymentMethod, co.orderPaymentReference, co.orderPaymentStatus,
             co.orderStatus, co.orderCancellationReason, co.orderCreatedAt,
             co.orderCreatedByStaffId,
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
$filters = [
    'search' => $search,
    'status' => $statusFilter,
    'payment_status' => $paymentFilter,
    'date_from' => $dateFrom,
    'date_to' => $dateTo,
];
$pageUrl = static function (int $targetPage) use ($filters): string {
    return 'staff_orders.php?' . http_build_query($filters + ['page' => $targetPage]);
};
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Orders - MotoWorks Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/staff_layout.css">
    <link rel="stylesheet" href="css/staff_dashboard.css">
    <link rel="stylesheet" href="css/staff_tables.css">
    <link rel="stylesheet" href="css/staff_forms.css">
    <link rel="stylesheet" href="css/staff_orders.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/staff_nav.php'; ?>
    <main class="admin-main">
        <?php
        $staffPageTitle = 'Orders';
        include __DIR__ . '/includes/staff_header.php';
        ?>
        <div class="admin-content-body">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert-toast" role="status"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="dashboard-grid order-stats">
                <?php foreach ([
                    'total' => ['All Orders', 'fa-boxes-stacked'],
                    'pending' => ['Pending', 'fa-clock'],
                    'cancelled' => ['Cancelled', 'fa-ban'],
                    'completed' => ['Completed', 'fa-circle-check'],
                ] as $key => [$label, $icon]): ?>
                    <div class="stat-card">
                        <div class="stat-icon bg-orders"><i class="fas <?= $icon ?>"></i></div>
                        <div class="stat-info">
                            <h3><?= (int) ($stats[$key] ?? 0) ?></h3>
                            <p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <details class="card-container staff-order-create">
                <summary class="card-header-flex"><h3><i class="fas fa-plus"></i> Create Order</h3></summary>
                <div class="staff-order-create-body">
                    <?php if (!$customers || !$products): ?>
                        <p>Creating an order requires an existing customer and an active product.</p>
                    <?php else: ?>
                        <form method="POST" action="actions/order_create.php" class="staff-order-create-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="staff-order-create-grid">
                                <label>Customer
                                    <select class="form-control" name="customer_id" required>
                                        <option value="">Select customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= (int) $customer['userId'] ?>" data-address="<?= htmlspecialchars((string) ($customer['userAddress'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars(trim($customer['userFirstName'] . ' ' . $customer['userLastName']) . ' — ' . ($customer['userPhone'] ?? 'No phone'), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Delivery address
                                    <textarea class="form-control" name="delivery_address" rows="2" maxlength="2000" required></textarea>
                                </label>
                            </div>
                            <div class="staff-order-items">
                                <div class="staff-order-item-row">
                                    <label>Product
                                        <select class="form-control" name="product_ids[]" required>
                                            <option value="">Select product</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= (int) $product['productId'] ?>">
                                                    <?= htmlspecialchars($product['productName'], ENT_QUOTES, 'UTF-8') ?> — ₱<?= number_format((float) $product['productPrice'], 2) ?> (<?= (int) $product['productStock'] ?> in stock)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Quantity
                                        <input class="form-control" type="number" name="quantities[]" min="1" max="999" value="1" required>
                                    </label>
                                </div>
                            </div>
                            <template id="staff-order-item-template">
                                <div class="staff-order-item-row">
                                    <label>Product
                                        <select class="form-control" name="product_ids[]" required>
                                            <option value="">Select product</option>
                                            <?php foreach ($products as $product): ?>
                                                <option value="<?= (int) $product['productId'] ?>">
                                                    <?= htmlspecialchars($product['productName'], ENT_QUOTES, 'UTF-8') ?> — ₱<?= number_format((float) $product['productPrice'], 2) ?> (<?= (int) $product['productStock'] ?> in stock)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </label>
                                    <label>Quantity
                                        <input class="form-control" type="number" name="quantities[]" min="1" max="999" value="1" required>
                                    </label>
                                    <button class="btn-sm btn-danger" type="button" data-remove-order-item aria-label="Remove item"><i class="fas fa-times"></i></button>
                                </div>
                            </template>
                            <div class="staff-order-create-actions">
                                <button class="btn-sm btn-info" type="button" data-add-order-item><i class="fas fa-plus"></i> Add another product</button>
                                <button class="btn-primary" type="submit"><i class="fas fa-cart-plus"></i> Save Order</button>
                            </div>
                            <p class="form-help">Orders are recorded as Pending with Cash on Delivery. Inventory is checked and deducted when saved.</p>
                        </form>
                    <?php endif; ?>
                </div>
            </details>

            <section class="card-container">
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
                    <div class="form-group">
                        <label for="date-from">From</label>
                        <input class="form-control" id="date-from" type="date" name="date_from" value="<?= htmlspecialchars($dateFrom, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="date-to">To</label>
                        <input class="form-control" id="date-to" type="date" name="date_to" value="<?= htmlspecialchars($dateTo, ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="order-filter-actions">
                        <button class="btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                        <a class="btn-sm btn-info" href="staff_orders.php">Clear</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="admin-table order-table">
                        <thead>
                        <tr>
                            <th>Order</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Order status</th>
                            <th>Placed</th>
                            <th>Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php if (!$orders): ?>
                            <tr><td class="text-center" colspan="8">No customer orders match these filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($orders as $order): ?>
                            <?php
                            $orderId = (int) $order['orderGroupId'];
                            $items = $itemsByOrder[$orderId] ?? [];
                            $currentStatus = (string) $order['orderStatus'];
                            $canEditOrder = strtolower(trim($currentStatus)) !== 'completed';
                            $transitions = $canEditOrder ? ['Pending', 'Cancelled', 'Completed'] : [];
                            ?>
                            <tr>
                                <td><strong>#<?= $orderId ?></strong><br><small><?= (int) $order['item_count'] ?> item(s)</small></td>
                                <td>
                                    <?= htmlspecialchars(trim($order['userFirstName'] . ' ' . $order['userLastName']), ENT_QUOTES, 'UTF-8') ?><br>
                                    <small><?= htmlspecialchars($order['userEmail'], ENT_QUOTES, 'UTF-8') ?><br><?= htmlspecialchars((string) $order['userPhone'], ENT_QUOTES, 'UTF-8') ?></small>
                                </td>
                                <td>
                                    <?php foreach ($items as $item): ?>
                                        <div class="order-item-summary">
                                            <?php if (!empty($item['productImage'])): ?>
                                                <?php
                                                $image = (string) $item['productImage'];
                                                $imageUrl = preg_match('~^(https?:)?//~i', $image) || str_starts_with($image, '/')
                                                    ? $image
                                                    : '../' . ltrim($image, '/');
                                                ?>
                                                <img src="<?= htmlspecialchars($imageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
                                            <?php endif; ?>
                                            <span><?= htmlspecialchars($item['productName'] ?? 'Product #' . $item['productId'], ENT_QUOTES, 'UTF-8') ?> × <?= (int) $item['orderQuantity'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </td>
                                <td>₱<?= number_format((float) $order['order_total'], 2) ?></td>
                                <td>
                                    <?= htmlspecialchars($order['orderPaymentMethod'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8') ?><br>
                                    <span class="badge badge-secondary"><?= htmlspecialchars($order['orderPaymentStatus'], ENT_QUOTES, 'UTF-8') ?></span>
                                </td>
                                <td><span class="badge <?= $currentStatus === 'Completed' ? 'badge-success' : ($currentStatus === 'Cancelled' ? 'badge-danger' : 'badge-secondary') ?>"><?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td><?= htmlspecialchars($order['orderCreatedAt'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="order-actions">
                                    <div class="table-row-actions">
                                        <button class="btn-sm btn-secondary" type="button" data-open-staff-dialog="order-details-<?= $orderId ?>"><i class="fas fa-eye"></i> View</button>
                                        <?php if ($canEditOrder): ?>
                                            <button class="btn-sm btn-info" type="button" data-edit-staff-dialog="order-details-<?= $orderId ?>"><i class="fas fa-edit"></i> Edit</button>
                                        <?php endif; ?>
                                        <form method="POST" action="actions/order_delete.php" onsubmit="return confirm('Permanently delete order #<?= $orderId ?>? Pending inventory will be restored.');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                            <button class="btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                    <dialog class="staff-record-dialog" id="order-details-<?= $orderId ?>" aria-labelledby="order-details-title-<?= $orderId ?>">
                                        <div class="staff-record-dialog-header">
                                            <h3 id="order-details-title-<?= $orderId ?>">Order #<?= $orderId ?></h3>
                                            <button class="btn-sm btn-danger" type="button" data-close-staff-dialog aria-label="Close"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="staff-record-dialog-body">
                                        <div class="order-details-body">
                                            <h4>Customer and fulfillment</h4>
                                            <p><?= htmlspecialchars(trim($order['userFirstName'] . ' ' . $order['userLastName']), ENT_QUOTES, 'UTF-8') ?><br>
                                                <?= htmlspecialchars($order['userEmail'], ENT_QUOTES, 'UTF-8') ?><br>
                                                <?= htmlspecialchars((string) $order['userPhone'], ENT_QUOTES, 'UTF-8') ?><br>
                                                Delivery address: <?= nl2br(htmlspecialchars((string) ($order['orderDeliveryAddress'] ?? 'Not recorded'), ENT_QUOTES, 'UTF-8')) ?></p>
                                            <h4>Order items</h4>
                                            <ul class="order-item-details">
                                                <?php foreach ($items as $item): ?>
                                                    <li>
                                                        <span><?= htmlspecialchars($item['productName'] ?? 'Product #' . $item['productId'], ENT_QUOTES, 'UTF-8') ?> × <?= (int) $item['orderQuantity'] ?></span>
                                                        <span>₱<?= number_format((float) $item['unit_price'], 2) ?> each · ₱<?= number_format((float) $item['orderTotalAmount'], 2) ?></span>
                                                    </li>
                                                <?php endforeach; ?>
                                            </ul>
                                            <p>Subtotal: <strong>₱<?= number_format((float) $order['order_total'], 2) ?></strong><br>
                                                Delivery charge: ₱0.00 (no separate delivery charge is configured)<br>
                                                Total: <strong>₱<?= number_format((float) $order['order_total'], 2) ?></strong><br>
                                                Payment reference: <?= htmlspecialchars($order['orderPaymentReference'] ?? 'Not recorded', ENT_QUOTES, 'UTF-8') ?><br>
                                                Payment status: <?= htmlspecialchars($order['orderPaymentStatus'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if (!empty($order['orderCancellationReason'])): ?>
                                                <p>Cancellation reason: <?= nl2br(htmlspecialchars($order['orderCancellationReason'], ENT_QUOTES, 'UTF-8')) ?></p>
                                            <?php endif; ?>
                                            <?php if ($canEditOrder): ?>
                                                <form method="POST" action="actions/order_status_update.php" class="order-status-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="order_id" value="<?= $orderId ?>">
                                                    <label>Update order status
                                                        <select class="form-control" name="status" required data-order-status data-current-status="<?= htmlspecialchars($currentStatus, ENT_QUOTES, 'UTF-8') ?>">
                                                            <?php foreach ($transitions as $nextStatus): ?>
                                                                <option value="<?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?>" <?= $nextStatus === $currentStatus ? 'selected' : '' ?>><?= htmlspecialchars($nextStatus, ENT_QUOTES, 'UTF-8') ?></option>
                                                            <?php endforeach; ?>
                                                        </select>
                                                    </label>
                                                    <label class="cancellation-reason">Cancellation reason
                                                        <textarea class="form-control" name="cancellation_reason" maxlength="500"></textarea>
                                                    </label>
                                                    <button class="btn-primary" type="submit">Save status</button>
                                                    <small>Payment status is read-only here; Staff cannot mark an order paid.</small>
                                                </form>
                                            <?php endif; ?>
                                        </div>
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
            </section>
        </div>
    </main>
</div>
<script src="js/staff.js" defer></script>
<script src="js/staff_orders.js" defer></script>
</body>
</html>
