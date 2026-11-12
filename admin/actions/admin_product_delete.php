<?php
// /admin/actions/admin_product_delete.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Administrator access required.');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_products.php?msg=' . urlencode('Invalid request.'));
    exit();
}

requireValidCSRFToken();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: ../admin_products.php?msg=' . urlencode('Invalid product ID.'));
    exit();
}

$image = null;
$productHasOrders = false;
$message = 'Product not found.';
try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare('SELECT productImage FROM product WHERE productId = ? FOR UPDATE');
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($product) {
        $image = $product['productImage'];
        $orderCountStmt = $pdo->prepare('SELECT COUNT(*) FROM `order` WHERE productId = ?');
        $orderCountStmt->execute([$id]);
        $productHasOrders = (int) $orderCountStmt->fetchColumn() > 0;
        if ($productHasOrders) {
            $deactivateStmt = $pdo->prepare('UPDATE product SET productActive = 0 WHERE productId = ?');
            $deactivateStmt->execute([$id]);
            $message = 'Product deactivated; its order history has been retained.';
        } else {
            $deleteStmt = $pdo->prepare('DELETE FROM product WHERE productId = ?');
            $deleteStmt->execute([$id]);
            $message = 'Product successfully deleted.';
        }
    }
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Product delete/deactivation failed: ' . $error->getMessage());
    header('Location: ../admin_products.php?msg=' . urlencode('Unable to remove this product. No changes were saved.'));
    exit();
}

if ($product && !$productHasOrders && !empty($image)) {
    $uploadsRoot = realpath(__DIR__ . '/../../uploads');
    $filePath = realpath(__DIR__ . '/../../' . ltrim((string) $image, '/\\'));
    if ($uploadsRoot && $filePath && str_starts_with($filePath, $uploadsRoot . DIRECTORY_SEPARATOR) && is_file($filePath) && !unlink($filePath)) {
        error_log('Unable to remove deleted product image: ' . $filePath);
    }
}

header('Location: ../admin_products.php?msg=' . urlencode($message));
exit();