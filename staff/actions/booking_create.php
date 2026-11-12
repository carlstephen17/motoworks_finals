<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_index.php');
    exit();
}
requireValidCSRFToken();

$customerId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$vehicle = trim((string) ($_POST['vehicle_model'] ?? ''));
$dateValue = (string) ($_POST['booking_date'] ?? '');
$timeValue = (string) ($_POST['booking_time'] ?? '');
$status = $_POST['status'] ?? '';
$notes = trim((string) ($_POST['notes'] ?? ''));
$statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);

if (
    !$customerId ||
    !$serviceId ||
    $vehicle === '' ||
    mb_strlen($vehicle) > 255 ||
    !$date ||
    $date->format('Y-m-d') !== $dateValue ||
    !$time ||
    $time->format('H:i') !== $timeValue ||
    !in_array($status, $statuses, true) ||
    mb_strlen($notes) > 2000
) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Please provide valid appointment details.'));
    exit();
}

$customerStmt = $pdo->prepare("SELECT userId FROM `user` WHERE userId = ? AND userRole = 'customer'");
$customerStmt->execute([$customerId]);
$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ? AND serviceActive = 1');
$serviceStmt->execute([$serviceId]);
if (!$customerStmt->fetchColumn() || !$serviceStmt->fetchColumn()) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Select an existing customer and active service.'));
    exit();
}

$stmt = $pdo->prepare(
    "INSERT INTO service_booking
        (userId, serviceId, bookingVehicleModel, bookingDate, bookingTime, bookingStatus, bookingNotes, createdByStaffId, bookingSource)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'staff')"
);
$stmt->execute([
    $customerId,
    $serviceId,
    $vehicle,
    $dateValue,
    $time->format('H:i:s'),
    $status,
    $notes !== '' ? $notes : null,
    (int) $_SESSION['user_id'],
]);

header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment created successfully.'));
exit();
