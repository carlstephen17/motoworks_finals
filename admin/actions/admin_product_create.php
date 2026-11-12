<?php
// /admin/actions/admin_product_create.php
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

$name = trim((string) ($_POST['name'] ?? ''));
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
$stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
$active = $_POST['active'] ?? null;

if ($name === '' || mb_strlen($name) > 255 || $price === false || $price < 0 || $stock === false || $stock < 0 || !in_array($active, ['0', '1', 0, 1], true)) {
    header('Location: ../admin_products.php?msg=' . urlencode('Please provide valid product details.'));
    exit();
}

try {
    $imagePath = saveAdminImageUpload($_FILES['image'] ?? []);
} catch (RuntimeException $error) {
    header('Location: ../admin_products.php?msg=' . urlencode($error->getMessage()));
    exit();
}

try {
    $stmt = $pdo->prepare('INSERT INTO product (productName, productPrice, productStock, productActive, productImage) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $price, $stock, (int) $active, $imagePath]);
} catch (PDOException $error) {
    if ($imagePath !== null && !removeAdminImageUpload($imagePath)) {
        error_log('Unable to remove image after product creation failed: ' . $imagePath);
    }
    error_log('Product creation failed: ' . $error->getMessage());
    header('Location: ../admin_products.php?msg=' . urlencode('Unable to create product. Please try again.'));
    exit();
}

header('Location: ../admin_products.php?msg=' . urlencode('Product successfully created.'));
exit();