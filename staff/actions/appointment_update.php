<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_appointments.php');
    exit();
}
requireValidCSRFToken();

$bookingId = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
$serviceId = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$dateValue = (string) ($_POST['booking_date'] ?? '');
$timeValue = (string) ($_POST['booking_time'] ?? '');
$status = (string) ($_POST['status'] ?? '');
$notes = trim((string) ($_POST['notes'] ?? ''));
$cancellationReason = trim((string) ($_POST['cancellation_reason'] ?? ''));
$statuses = ['Pending', 'Cancelled', 'Completed'];
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);

if (
    !$bookingId || !$serviceId ||
    !$date || $date->format('Y-m-d') !== $dateValue ||
    !$time || $time->format('H:i') !== $timeValue ||
    !in_array($status, $statuses, true) ||
    mb_strlen($notes) > 2000 || mb_strlen($cancellationReason) > 1000
) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Please provide valid appointment details.'));
    exit();
}

$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ?');
$serviceStmt->execute([$serviceId]);
if (!$serviceStmt->fetchColumn()) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('The selected service does not exist.'));
    exit();
}

$stmt = $pdo->prepare("
    UPDATE service_booking
    SET serviceId = ?, bookingDate = ?, bookingTime = ?, bookingStatus = ?, bookingNotes = ?,
        bookingRejectionReason = ?
    WHERE bookingId = ?
      AND COALESCE(LOWER(TRIM(bookingStatus)), '') <> 'completed'
");
$stmt->execute([
    $serviceId,
    $dateValue,
    $time->format('H:i:s'),
    $status,
    $notes !== '' ? $notes : null,
    $status === 'Cancelled' && $cancellationReason !== '' ? $cancellationReason : null,
    $bookingId,
]);

if ($stmt->rowCount() === 0) {
    $statusStmt = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ?');
    $statusStmt->execute([$bookingId]);
    $currentStatus = $statusStmt->fetchColumn();
    if ($currentStatus === false) {
        http_response_code(404);
        exit('Appointment not found.');
    }
    if (strtolower(trim((string) $currentStatus)) === 'completed') {
        header('Location: ../staff_appointments.php?msg=' . urlencode('Completed appointments cannot be edited.'));
        exit();
    }
}

header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment changes saved successfully.'));
exit();
