<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../../includes/order_deletion.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_orders.php');
    exit();
}

requireValidCSRFToken();

$orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
if (!$orderId || $orderId < 1) {
    header('Location: ../staff_orders.php?msg=' . urlencode('Invalid order ID.'));
    exit();
}

try {
    $message = deleteCustomerOrder($pdo, $orderId);
} catch (DomainException $error) {
    $message = $error->getMessage();
} catch (Throwable $error) {
    error_log('Staff order deletion failed: ' . $error->getMessage());
    $message = 'Unable to delete this order. No changes were saved.';
}

header('Location: ../staff_orders.php?msg=' . urlencode($message));
exit();
