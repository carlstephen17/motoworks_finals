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
if (!$bookingId || $bookingId < 1) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Invalid appointment.'));
    exit();
}

$stmt = $pdo->prepare("
    UPDATE service_booking
    SET bookingStatus = 'Cancelled'
    WHERE bookingId = ? AND createdByStaffId = ?
      AND COALESCE(LOWER(TRIM(bookingStatus)), '') <> 'completed'
");
$stmt->execute([$bookingId, (int) $_SESSION['user_id']]);
if ($stmt->rowCount() === 0) {
    $exists = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ? AND createdByStaffId = ?');
    $exists->execute([$bookingId, (int) $_SESSION['user_id']]);
    $status = $exists->fetchColumn();
    if ($status === false) {
        http_response_code(403);
        exit('You are not allowed to cancel this appointment.');
    }
    if (strtolower(trim((string) $status)) === 'completed') {
        header('Location: ../staff_appointments.php?msg=' . urlencode('Completed appointments cannot be edited.'));
        exit();
    }
}

header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment cancelled successfully.'));
exit();
