<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_index.php');
    exit();
}
requireValidCSRFToken();

$recordId = filter_input(INPUT_POST, 'record_id', FILTER_VALIDATE_INT);
if (!$recordId || $recordId < 1) {
    header('Location: ../staff_index.php?msg=' . urlencode('Invalid service record.'));
    exit();
}

$stmt = $pdo->prepare('DELETE FROM service_record WHERE recordId = ? AND createdByStaffId = ?');
$stmt->execute([$recordId, (int) $_SESSION['user_id']]);
if ($stmt->rowCount() !== 1) {
    http_response_code(403);
    exit('You are not allowed to delete this service record.');
}

header('Location: ../staff_index.php?msg=' . urlencode('Service record deleted successfully.'));
exit();
