<?php
// /admin/actions/admin_service_delete.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
require_once '../includes/upload_helpers.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
    : false;

if ($id) {
    requireValidCSRFToken();
    $imageStmt = $pdo->prepare('SELECT serviceImage FROM service WHERE serviceId = ?');
    $imageStmt->execute([$id]);
    $imagePath = $imageStmt->fetchColumn();
    $stmt = $pdo->prepare('DELETE FROM service WHERE serviceId = ?');
    $stmt->execute([$id]);

    if ($stmt->rowCount() > 0 && is_string($imagePath) && str_starts_with($imagePath, 'uploads/') && !removeAdminImageUpload($imagePath)) {
        error_log('Unable to remove deleted service image: ' . $imagePath);
    }
    header('Location: ../admin_services.php?msg=Service+successfully+deleted');
    exit();
}

header('Location: ../admin_services.php?msg=' . urlencode($_SERVER['REQUEST_METHOD'] === 'POST' ? 'Invalid service ID.' : 'Invalid request.'));
exit();