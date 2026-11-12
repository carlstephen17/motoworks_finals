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

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$vehicle = trim((string) ($_POST['vehicle_model'] ?? ''));
$dateValue = (string) ($_POST['booking_date'] ?? '');
$timeValue = (string) ($_POST['booking_time'] ?? '');
$status = $_POST['status'] ?? '';
$notes = trim((string) ($_POST['notes'] ?? ''));
$statuses = ['Pending', 'Cancelled', 'Completed'];
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);

if (
    !$bookingId ||
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

$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ?');
$serviceStmt->execute([$serviceId]);
if (!$serviceStmt->fetchColumn()) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Select an existing service.'));
    exit();
}

$stmt = $pdo->prepare(
    "UPDATE service_booking
     SET serviceId = ?, bookingVehicleModel = ?, bookingDate = ?, bookingTime = ?, bookingStatus = ?, bookingNotes = ?
     WHERE bookingId = ?
       AND COALESCE(LOWER(TRIM(bookingStatus)), '') <> 'completed'"
);
$stmt->execute([
    $serviceId,
    $vehicle,
    $dateValue,
    $time->format('H:i:s'),
    $status,
    $notes !== '' ? $notes : null,
    $bookingId,
]);
if ($stmt->rowCount() === 0) {
    $statusCheck = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ?');
    $statusCheck->execute([$bookingId]);
    $currentStatus = $statusCheck->fetchColumn();
    if ($currentStatus === false) {
        http_response_code(404);
        exit('Appointment not found.');
    }
    if (strtolower(trim((string) $currentStatus)) === 'completed') {
        header('Location: ../staff_appointments.php?msg=' . urlencode('Completed appointments cannot be edited.'));
        exit();
    }
}

header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment updated successfully.'));
exit();
