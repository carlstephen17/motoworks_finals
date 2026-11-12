<?php
// /admin/actions/admin_stock_update.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_products.php');
    exit();
}
requireValidCSRFToken();

$productId = filter_input(INPUT_POST, 'product_id', FILTER_VALIDATE_INT);
$actionType = $_POST['action_type'] ?? '';
$quantity = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
if (!$productId || $productId < 1 || $quantity === false || $quantity < 0 || $quantity > 1000000 || !in_array($actionType, ['add', 'subtract', 'set'], true)) {
    header('Location: ../admin_products.php?msg=' . urlencode('Invalid stock parameters.'));
    exit();
}

$pdo->beginTransaction();
try {
    $stmt = $pdo->prepare('SELECT productStock FROM product WHERE productId = ? FOR UPDATE');
    $stmt->execute([$productId]);
    $currentStock = $stmt->fetchColumn();
    if ($currentStock === false) {
        $pdo->rollBack();
        header('Location: ../admin_products.php?msg=' . urlencode('Product not found.'));
        exit();
    }

    $newStock = match ($actionType) {
        'add' => (int) $currentStock + $quantity,
        'subtract' => max(0, (int) $currentStock - $quantity),
        default => $quantity,
    };
    if ($newStock > 2147483647) {
        $pdo->rollBack();
        header('Location: ../admin_products.php?msg=' . urlencode('Stock exceeds the allowed maximum.'));
        exit();
    }

    $updateStmt = $pdo->prepare('UPDATE product SET productStock = ? WHERE productId = ?');
    $updateStmt->execute([$newStock, $productId]);
    $pdo->commit();
} catch (Throwable $error) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Stock update failed: ' . $error->getMessage());
    header('Location: ../admin_products.php?msg=' . urlencode('Unable to update stock. Please try again.'));
    exit();
}

header('Location: ../admin_products.php?msg=' . urlencode('Stock successfully updated.'));
exit();