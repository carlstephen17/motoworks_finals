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
if (!$bookingId || $bookingId < 1) {
    header('Location: ../staff_appointments.php?msg=' . urlencode('Invalid appointment ID.'));
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM service_booking WHERE bookingId = ?');
    $stmt->execute([$bookingId]);
    if ($stmt->rowCount() !== 1) {
        header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment not found.'));
        exit();
    }
} catch (PDOException $error) {
    error_log('Staff appointment deletion failed: ' . $error->getMessage());
    header('Location: ../staff_appointments.php?msg=' . urlencode('Unable to delete this appointment.'));
    exit();
}

header('Location: ../staff_appointments.php?msg=' . urlencode('Appointment deleted.'));
exit();
