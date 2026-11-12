<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_orders.php');
    exit();
}
requireValidCSRFToken();

$customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$deliveryAddress = trim((string) ($_POST['delivery_address'] ?? ''));
$productIds = $_POST['product_ids'] ?? null;
$quantities = $_POST['quantities'] ?? null;
if (
    !$customerId || $customerId < 1 ||
    $deliveryAddress === '' || mb_strlen($deliveryAddress) > 2000 ||
    !is_array($productIds) || !is_array($quantities) ||
    count($productIds) < 1 || count($productIds) > 20 ||
    count($productIds) !== count($quantities)
) {
    header('Location: ../staff_orders.php?msg=' . urlencode('Please provide a customer, delivery address, and between 1 and 20 valid order items.'));
    exit();
}

$items = [];
foreach ($productIds as $index => $productIdValue) {
    $productId = filter_var($productIdValue, FILTER_VALIDATE_INT);
    $quantity = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT);
    if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 999) {
        header('Location: ../staff_orders.php?msg=' . urlencode('Each order item must have a valid product and quantity.'));
        exit();
    }
    $items[$productId] = ($items[$productId] ?? 0) + $quantity;
    if ($items[$productId] > 999) {
        header('Location: ../staff_orders.php?msg=' . urlencode('The total quantity of a product cannot exceed 999.'));
        exit();
    }
}
ksort($items, SORT_NUMERIC);

$customerStmt = $pdo->prepare("SELECT userId FROM `user` WHERE userId = ? AND userRole = 'customer'");
$customerStmt->execute([$customerId]);
if (!$customerStmt->fetchColumn()) {
    header('Location: ../staff_orders.php?msg=' . urlencode('Select an existing customer account.'));
    exit();
}

try {
    $pdo->beginTransaction();
    $placeholders = implode(',', array_fill(0, count($items), '?'));
    $productStmt = $pdo->prepare("
        SELECT productId, productName, productPrice, productStock
        FROM product
        WHERE productActive = 1 AND productId IN ({$placeholders})
        ORDER BY productId
        FOR UPDATE
    ");
    $productStmt->execute(array_keys($items));
    $lockedProducts = $productStmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($lockedProducts) !== count($items)) {
        throw new DomainException('One or more selected products are unavailable.');
    }

    foreach ($lockedProducts as $product) {
        $productId = (int) $product['productId'];
        if ((int) $product['productStock'] < $items[$productId]) {
            throw new DomainException(
                $product['productName'] . ' has only ' . (int) $product['productStock'] . ' in stock.'
            );
        }
    }

    $paymentReference = 'MWO-' . date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));
    $groupStmt = $pdo->prepare("
        INSERT INTO customer_order
            (userId, orderDeliveryAddress, orderPaymentMethod, orderPaymentReference,
             orderPaymentStatus, orderStatus, orderCreatedByStaffId, orderCreatedAt)
        VALUES (?, ?, 'Cash on Delivery', ?, 'Due on delivery', 'Pending', ?, NOW())
    ");
    $groupStmt->execute([
        $customerId,
        $deliveryAddress,
        $paymentReference,
        (int) $_SESSION['user_id'],
    ]);
    $orderGroupId = (int) $pdo->lastInsertId();

    $stockStmt = $pdo->prepare('UPDATE product SET productStock = productStock - ? WHERE productId = ? AND productStock >= ?');
    $orderStmt = $pdo->prepare("
        INSERT INTO `order`
            (userId, productId, orderQuantity, orderTotalAmount, orderPaymentMethod,
             orderPaymentReference, orderStatus, orderCreatedAt, orderGroupId, orderUnitPrice)
        VALUES (?, ?, ?, ?, 'Cash on Delivery', ?, 'Pending', NOW(), ?, ?)
    ");
    $totalAmount = 0.0;
    foreach ($lockedProducts as $product) {
        $productId = (int) $product['productId'];
        $quantity = $items[$productId];
        $price = (float) $product['productPrice'];
        $lineTotal = round($price * $quantity, 2);

        $stockStmt->execute([$quantity, $productId, $quantity]);
        if ($stockStmt->rowCount() !== 1) {
            throw new DomainException('Unable to reserve inventory for ' . $product['productName'] . '.');
        }
        $orderStmt->execute([
            $customerId,
            $productId,
            $quantity,
            $lineTotal,
            $paymentReference,
            $orderGroupId,
            $price,
        ]);
        $totalAmount += $lineTotal;
    }

    $pdo->commit();
} catch (DomainException $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../staff_orders.php?msg=' . urlencode($error->getMessage()));
    exit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff order creation failed: ' . $error->getMessage());
    header('Location: ../staff_orders.php?msg=' . urlencode('Unable to save the order. No changes were made.'));
    exit();
}

header('Location: ../staff_orders.php?msg=' . urlencode('Order #' . $orderGroupId . ' created successfully. Total: ₱' . number_format($totalAmount, 2)));
exit();
