<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/motorcycle_actions.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_index.php');
    exit();
}
requireValidCSRFToken();

$customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$motorcycleId = filter_input(INPUT_POST, 'motorcycle_id', FILTER_VALIDATE_INT) ?: 0;
$motorcycleMode = (string) ($_POST['motorcycle_mode'] ?? '');
$model = trim((string) ($_POST['motorcycle_model'] ?? ''));
$plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
$dateValue = (string) ($_POST['service_date'] ?? '');
$workDescription = trim((string) ($_POST['work_description'] ?? ''));
$partsUsed = trim((string) ($_POST['parts_used'] ?? ''));
$costValue = trim((string) ($_POST['cost'] ?? ''));
$notes = trim((string) ($_POST['notes'] ?? ''));
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$costValid = $costValue === '' || preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', $costValue) === 1;

if (
    !$customerId || !$serviceId ||
    !in_array($motorcycleMode, ['existing', 'new'], true) ||
    ($motorcycleMode === 'existing' && !$motorcycleId) ||
    ($motorcycleMode === 'new' && ($model === '' || mb_strlen($model) > 255 || mb_strlen($plate) > 32)) ||
    !$date || $date->format('Y-m-d') !== $dateValue ||
    $workDescription === '' || mb_strlen($workDescription) > 5000 ||
    mb_strlen($partsUsed) > 5000 || mb_strlen($notes) > 2000 ||
    !$costValid
) {
    header('Location: ../staff_index.php?msg=' . urlencode('Please provide valid service record details.'));
    exit();
}

$customerStmt = $pdo->prepare("SELECT userId FROM `user` WHERE userId = ? AND userRole = 'customer'");
$customerStmt->execute([$customerId]);
$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ? AND serviceActive = 1');
$serviceStmt->execute([$serviceId]);
if (!$customerStmt->fetchColumn() || !$serviceStmt->fetchColumn()) {
    header('Location: ../staff_index.php?msg=' . urlencode('Choose an existing customer and active service.'));
    exit();
}

try {
    $pdo->beginTransaction();
    $motorcycle = getOrCreateCustomerMotorcycle(
        $pdo,
        $customerId,
        $motorcycleMode,
        $motorcycleId,
        $model,
        $plate
    );
    $insert = $pdo->prepare("
        INSERT INTO service_record
            (customerId, motorcycleId, serviceId, createdByStaffId, recordMotorcycleModel,
             recordPlateNumber, recordServiceDate, recordWorkDescription, recordPartsUsed,
             recordCost, recordStatus, recordNotes)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'In Progress', ?)
    ");
    $insert->execute([
        $customerId,
        (int) $motorcycle['motorcycleId'],
        $serviceId,
        (int) $_SESSION['user_id'],
        $motorcycle['motorcycleModel'],
        $motorcycle['motorcyclePlateNumber'],
        $dateValue,
        $workDescription,
        $partsUsed !== '' ? $partsUsed : null,
        $costValue !== '' ? $costValue : null,
        $notes !== '' ? $notes : null,
    ]);
    $pdo->commit();
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../staff_index.php?msg=' . urlencode($e->getMessage()));
    exit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff service record creation failed: ' . $e->getMessage());
    header('Location: ../staff_index.php?msg=' . urlencode('The service record could not be saved. Check the customer and plate details.'));
    exit();
}

header('Location: ../staff_index.php?msg=' . urlencode('Service record saved successfully.'));
exit();
