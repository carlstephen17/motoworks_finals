<?php
// /admin/actions/admin_service_create.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../includes/upload_helpers.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_services.php');
    exit();
}
requireValidCSRFToken();

$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
$status = $_POST['status'] ?? 'Active';
if ($name === '' || mb_strlen($name) > 255 || mb_strlen($description) > 65535 || $price === false || $price < 0 || !in_array($status, ['Active', 'Inactive'], true)) {
    header('Location: ../admin_services.php?msg=' . urlencode('Please provide valid service details.'));
    exit();
}

try {
    $imagePath = saveAdminImageUpload($_FILES['image'] ?? []);
} catch (RuntimeException $error) {
    header('Location: ../admin_services.php?msg=' . urlencode($error->getMessage()));
    exit();
}

try {
    $stmt = $pdo->prepare('INSERT INTO service (serviceName, serviceDescription, servicePrice, serviceActive, serviceImage) VALUES (?, ?, ?, ?, ?)');
    $stmt->execute([$name, $description, $price, $status === 'Active' ? 1 : 0, $imagePath]);
} catch (PDOException $error) {
    if ($imagePath !== null && !removeAdminImageUpload($imagePath)) {
        error_log('Unable to remove image after service creation failed: ' . $imagePath);
    }
    error_log('Service creation failed: ' . $error->getMessage());
    header('Location: ../admin_services.php?msg=' . urlencode('Unable to create service. Please try again.'));
    exit();
}

header('Location: ../admin_services.php?msg=' . urlencode('Service successfully created.'));
exit();
