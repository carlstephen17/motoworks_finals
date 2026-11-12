<?php
// /admin/actions/admin_product_update.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../includes/upload_helpers.php';
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

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$name = trim((string) ($_POST['name'] ?? ''));
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
$stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
$active = $_POST['active'] ?? null;

if (!$id || $id < 1 || $name === '' || mb_strlen($name) > 255 || $price === false || $price < 0 || $stock === false || $stock < 0 || !in_array($active, ['0', '1', 0, 1], true)) {
    header('Location: ../admin_products.php?msg=' . urlencode('Please provide valid product details.'));
    exit();
}

$existingStmt = $pdo->prepare('SELECT productImage FROM product WHERE productId = ?');
$existingStmt->execute([$id]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    header('Location: ../admin_products.php?msg=' . urlencode('Product not found.'));
    exit();
}

try {
    $newImagePath = saveAdminImageUpload($_FILES['image'] ?? []);
} catch (RuntimeException $error) {
    header('Location: ../admin_products.php?msg=' . urlencode($error->getMessage()));
    exit();
}

try {
    if ($newImagePath === null) {
        $stmt = $pdo->prepare('UPDATE product SET productName = ?, productPrice = ?, productStock = ?, productActive = ? WHERE productId = ?');
        $stmt->execute([$name, $price, $stock, (int) $active, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE product SET productName = ?, productPrice = ?, productStock = ?, productActive = ?, productImage = ? WHERE productId = ?');
        $stmt->execute([$name, $price, $stock, (int) $active, $newImagePath, $id]);
    }
} catch (PDOException $error) {
    if ($newImagePath !== null && !removeAdminImageUpload($newImagePath)) {
        error_log('Unable to remove image after product update failed: ' . $newImagePath);
    }
    error_log('Product update failed: ' . $error->getMessage());
    header('Location: ../admin_products.php?msg=' . urlencode('Unable to update product. Please try again.'));
    exit();
}

if ($newImagePath !== null && !empty($existing['productImage']) && str_starts_with($existing['productImage'], 'uploads/') && !removeAdminImageUpload($existing['productImage'])) {
    error_log('Unable to remove replaced product image: ' . $existing['productImage']);
}
header('Location: ../admin_products.php?msg=' . urlencode('Product successfully updated.'));
exit();