<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/orders.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Administrator access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_orders.php');
    exit();
}

requireValidCSRFToken();

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
$status = trim((string) ($_POST['status'] ?? ''));
$reason = trim((string) ($_POST['cancellation_reason'] ?? ''));
if (!$orderId || $orderId < 1) {
    header('Location: ../admin_orders.php?msg=' . urlencode('Invalid order ID.'));
    exit();
}

try {
    $message = updateCustomerOrderStatus($pdo, $orderId, $status, $reason);
} catch (DomainException $error) {
    $message = $error->getMessage();
} catch (Throwable $error) {
    error_log('Admin order update failed: ' . $error->getMessage());
    $message = 'Unable to update this order. No changes were saved.';
}

header('Location: ../admin_orders.php?msg=' . urlencode($message));
exit();
