<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';
require_once __DIR__ . '/../includes/motorcycle_actions.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_appointments.php');
    exit();
}
requireValidCSRFToken();

$customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$motorcycleId = filter_input(INPUT_POST, 'motorcycle_id', FILTER_VALIDATE_INT) ?: 0;
$motorcycleMode = (string) ($_POST['motorcycle_mode'] ?? '');
$model = trim((string) ($_POST['motorcycle_model'] ?? ''));
$plate = strtoupper(trim((string) ($_POST['plate_number'] ?? '')));
$dateValue = (string) ($_POST['booking_date'] ?? '');
$timeValue = (string) ($_POST['booking_time'] ?? '');
$notes = trim((string) ($_POST['notes'] ?? ''));
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);

if (
    !$customerId || !$serviceId ||
    !in_array($motorcycleMode, ['existing', 'new'], true) ||
    ($motorcycleMode === 'existing' && !$motorcycleId) ||
    ($motorcycleMode === 'new' && ($model === '' || mb_strlen($model) > 255 || mb_strlen($plate) > 32)) ||
    !$date || $date->format('Y-m-d') !== $dateValue ||
    !$time || $time->format('H:i') !== $timeValue ||
    mb_strlen($notes) > 2000
) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Please provide valid walk-in appointment details.'));
    exit();
}

$customerStmt = $pdo->prepare("SELECT userId FROM `user` WHERE userId = ? AND userRole = 'customer'");
$customerStmt->execute([$customerId]);
$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ? AND serviceActive = 1');
$serviceStmt->execute([$serviceId]);
if (!$customerStmt->fetchColumn() || !$serviceStmt->fetchColumn()) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Choose an existing customer and active service.'));
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
    $motorcycleId = (int) $motorcycle['motorcycleId'];
    $model = $motorcycle['motorcycleModel'];

    $insert = $pdo->prepare("
        INSERT INTO service_booking
            (userId, serviceId, bookingVehicleModel, bookingDate, bookingTime, bookingStatus, bookingNotes, bookingMotorcycleId, createdByStaffId, bookingSource)
        VALUES (?, ?, ?, ?, ?, 'Pending', ?, ?, ?, 'staff')
    ");
    $insert->execute([
        $customerId,
        $serviceId,
        $model,
        $dateValue,
        $time->format('H:i:s'),
        $notes !== '' ? $notes : null,
        $motorcycleId,
        (int) $_SESSION['user_id'],
    ]);
    $pdo->commit();
} catch (InvalidArgumentException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    header('Location: ../staff_appointments.php?msg=' . urlencode($e->getMessage()));
    exit();
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('Staff walk-in appointment creation failed: ' . $e->getMessage());
    header('Location: ../staff_appointments.php?msg=' . urlencode('The appointment could not be saved. Check the customer and plate details.'));
    exit();
}

header('Location: ../staff_appointments.php?msg=' . urlencode('Walk-in appointment saved successfully.'));
exit();
