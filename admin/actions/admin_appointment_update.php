<?php

require_once __DIR__ . '/../../includes/auth.php';

require_once '../../includes/config.php';
/** @var PDO $pdo */
require_once '../../includes/auth.php';


// =========================================================
// ADMIN ACCESS
// =========================================================

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}


// =========================================================
// ONLY ALLOW POST
// =========================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_appointment.php');
    exit();
}


// =========================================================
// CSRF TOKEN
// =========================================================

$token = $_POST['csrf_token'] ?? '';

if (function_exists('verifyCSRFToken')) {

    if (!verifyCSRFToken($token)) {
        header(
            'Location: ../admin_appointment.php?msg=' .
            urlencode('Invalid Security Token')
        );
        exit();
    }

}


// =========================================================
// GET FORM DATA
// =========================================================

$appointment_id = filter_input(
    INPUT_POST,
    'appointment_id',
    FILTER_VALIDATE_INT
);

$vehicle_model = trim(
    $_POST['vehicle_model'] ?? ''
);

$service_id = filter_input(
    INPUT_POST,
    'service_id',
    FILTER_VALIDATE_INT
);

$booking_date = trim(
    $_POST['booking_date'] ?? ''
);

$booking_time = trim(
    $_POST['booking_time'] ?? ''
);

$status = trim(
    $_POST['status'] ?? 'Pending'
);

$rejection_reason = trim(
    $_POST['rejection_reason'] ?? ''
);

$notes = trim(
    $_POST['notes'] ?? ''
);


// =========================================================
// ALLOWED STATUS
// =========================================================

$allowed_statuses = [
    'Pending',
    'Cancelled',
    'Completed',
];

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $booking_date);
$validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $booking_time) === 1;

if (
    !$appointment_id ||
    $vehicle_model === '' ||
    mb_strlen($vehicle_model) > 255 ||
    !$service_id ||
    !$date ||
    $date->format('Y-m-d') !== $booking_date ||
    !$validTime ||
    !in_array($status, $allowed_statuses, true) ||
    mb_strlen($rejection_reason) > 1000 ||
    mb_strlen($notes) > 2000
) {

    header(
        'Location: ../admin_appointment.php?msg=' .
        urlencode('Please provide valid appointment details.')
    );

    exit();
}


// =========================================================
// CHECK IF SERVICE EXISTS
// =========================================================

try {

    $existingAppointment = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ?');
    $existingAppointment->execute([$appointment_id]);
    $currentStatus = $existingAppointment->fetchColumn();
    if ($currentStatus === false) {
        header('Location: ../admin_appointment.php?msg=' . urlencode('Appointment not found.'));
        exit();
    }
    if (strtolower(trim((string) $currentStatus)) === 'completed') {
        header('Location: ../admin_appointment.php?msg=' . urlencode('Completed appointments cannot be edited.'));
        exit();
    }

    $serviceCheck = $pdo->prepare("
        SELECT serviceId as id FROM service WHERE serviceId = ?
    ");

    $serviceCheck->execute([
        $service_id
    ]);

    if (!$serviceCheck->fetch()) {

        header(
            'Location: ../admin_appointment.php?msg=' .
            urlencode('Selected service does not exist')
        );

        exit();
    }


    // =====================================================
    // UPDATE APPOINTMENT
    // =====================================================

    $stmt = $pdo->prepare("
        UPDATE service_booking
        SET bookingVehicleModel = ?, serviceId = ?, bookingDate = ?, bookingTime = ?,
            bookingStatus = ?, bookingNotes = ?, bookingRejectionReason = ?
        WHERE bookingId = ? AND LOWER(TRIM(bookingStatus)) <> 'completed'
    ");


    $stmt->execute([
        $vehicle_model,
        $service_id,
        $booking_date,
        $booking_time,
        $status,
        $notes,
        $status === 'Cancelled' ? $rejection_reason : null,
        $appointment_id
    ]);

    if ($stmt->rowCount() === 0) {
        $statusCheck = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ?');
        $statusCheck->execute([$appointment_id]);
        $updatedStatus = $statusCheck->fetchColumn();
        if ($updatedStatus === false || strtolower(trim((string) $updatedStatus)) === 'completed') {
            header('Location: ../admin_appointment.php?msg=' . urlencode('Completed appointments cannot be edited.'));
            exit();
        }
    }


    // =====================================================
    // SUCCESS
    // =====================================================

    header(
        'Location: ../admin_appointment.php?msg=' .
        urlencode('Appointment successfully updated')
    );

    exit();


} catch (PDOException $e) {

    error_log('Admin appointment update failed: ' . $e->getMessage());

    // =====================================================
    // ERROR
    // =====================================================

    header(
        'Location: ../admin_appointment.php?msg=' .
        urlencode(
            'Failed to update appointment. Check the submitted details and try again.'
        )
    );

    exit();

}

?>