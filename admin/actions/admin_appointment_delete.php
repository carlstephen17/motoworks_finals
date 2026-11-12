<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_appointment.php');
    exit();
}

requireValidCSRFToken();

$appointment_id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$appointment_id) {
    header(
        'Location: ../admin_appointment.php?msg=Invalid+appointment+ID'
    );
    exit();
}

try {

    $stmt = $pdo->prepare("
        DELETE FROM service_booking WHERE bookingId = ?
    ");

    $stmt->execute([$appointment_id]);

    header(
        'Location: ../admin_appointment.php?msg=' .
        urlencode('Appointment deleted successfully')
    );
    exit();

} catch (PDOException $e) {
    error_log('Appointment deletion failed: ' . $e->getMessage());
    header('Location: ../admin_appointment.php?msg=' . urlencode('Failed to delete appointment.'));
    exit();
}