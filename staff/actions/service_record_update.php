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
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$dateValue = (string) ($_POST['service_date'] ?? '');
$workDescription = trim((string) ($_POST['work_description'] ?? ''));
$partsUsed = trim((string) ($_POST['parts_used'] ?? ''));
$costValue = trim((string) ($_POST['cost'] ?? ''));
$status = (string) ($_POST['status'] ?? '');
$notes = trim((string) ($_POST['notes'] ?? ''));
$statuses = ['In Progress', 'On Hold', 'Completed'];
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$costValid = $costValue === '' || preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $costValue) === 1;

if (
    !$recordId || !$serviceId ||
    !$date || $date->format('Y-m-d') !== $dateValue ||
    $workDescription === '' || mb_strlen($workDescription) > 5000 ||
    mb_strlen($partsUsed) > 5000 || mb_strlen($notes) > 2000 ||
    !$costValid || !in_array($status, $statuses, true)
) {
    header('Location: ../staff_index.php?msg=' . urlencode('Please provide valid service record details.'));
    exit();
}
$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ?');
$serviceStmt->execute([$serviceId]);
if (!$serviceStmt->fetchColumn()) {
    header('Location: ../staff_index.php?msg=' . urlencode('The selected service does not exist.'));
    exit();
}

$stmt = $pdo->prepare("
    UPDATE service_record
    SET serviceId = ?, recordServiceDate = ?, recordWorkDescription = ?, recordPartsUsed = ?,
        recordCost = ?, recordStatus = ?, recordNotes = ?
    WHERE recordId = ? AND createdByStaffId = ?
");
$stmt->execute([
    $serviceId,
    $dateValue,
    $workDescription,
    $partsUsed !== '' ? $partsUsed : null,
    $costValue !== '' ? $costValue : null,
    $status,
    $notes !== '' ? $notes : null,
    $recordId,
    (int) $_SESSION['user_id'],
]);

if ($stmt->rowCount() === 0) {
    $ownerStmt = $pdo->prepare('SELECT 1 FROM service_record WHERE recordId = ? AND createdByStaffId = ?');
    $ownerStmt->execute([$recordId, (int) $_SESSION['user_id']]);
    if (!$ownerStmt->fetchColumn()) {
        http_response_code(403);
        exit('You are not allowed to modify this service record.');
    }
}

header('Location: ../staff_index.php?msg=' . urlencode('Service record updated successfully.'));
exit();
