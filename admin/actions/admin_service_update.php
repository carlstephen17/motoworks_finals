<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../includes/upload_helpers.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_services.php');
    exit();
}
requireValidCSRFToken();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$name = trim((string) ($_POST['name'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
$status = $_POST['status'] ?? 'Active';
if (!$id || $id < 1 || $name === '' || mb_strlen($name) > 255 || mb_strlen($description) > 65535 || $price === false || $price < 0 || !in_array($status, ['Active', 'Inactive'], true)) {
    header('Location: ../admin_services.php?msg=' . urlencode('Please provide valid service details.'));
    exit();
}

$existingStmt = $pdo->prepare('SELECT serviceImage FROM service WHERE serviceId = ?');
$existingStmt->execute([$id]);
$existing = $existingStmt->fetch(PDO::FETCH_ASSOC);
if (!$existing) {
    header('Location: ../admin_services.php?msg=' . urlencode('Service not found.'));
    exit();
}

try {
    $newImagePath = saveAdminImageUpload($_FILES['image'] ?? []);
} catch (RuntimeException $error) {
    header('Location: ../admin_services.php?msg=' . urlencode($error->getMessage()));
    exit();
}

try {
    if ($newImagePath === null) {
        $stmt = $pdo->prepare('UPDATE service SET serviceName = ?, serviceDescription = ?, servicePrice = ?, serviceActive = ? WHERE serviceId = ?');
        $stmt->execute([$name, $description, $price, $status === 'Active' ? 1 : 0, $id]);
    } else {
        $stmt = $pdo->prepare('UPDATE service SET serviceName = ?, serviceDescription = ?, servicePrice = ?, serviceActive = ?, serviceImage = ? WHERE serviceId = ?');
        $stmt->execute([$name, $description, $price, $status === 'Active' ? 1 : 0, $newImagePath, $id]);
    }
} catch (PDOException $error) {
    if ($newImagePath !== null && !removeAdminImageUpload($newImagePath)) {
        error_log('Unable to remove image after service update failed: ' . $newImagePath);
    }
    error_log('Service update failed: ' . $error->getMessage());
    header('Location: ../admin_services.php?msg=' . urlencode('Unable to update service. Please try again.'));
    exit();
}

if ($newImagePath !== null && !empty($existing['serviceImage']) && str_starts_with($existing['serviceImage'], 'uploads/') && !removeAdminImageUpload($existing['serviceImage'])) {
    error_log('Unable to remove replaced service image: ' . $existing['serviceImage']);
}
header('Location: ../admin_services.php?msg=' . urlencode('Service successfully updated.'));
exit();
