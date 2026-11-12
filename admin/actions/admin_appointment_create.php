<?php
require_once __DIR__ . '/../../includes/auth.php';

require_once '../../includes/config.php';
/** @var PDO $pdo */
require_once '../../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_appointment.php');
    exit();
}

$token = $_POST['csrf_token'] ?? '';

if (!verifyCSRFToken($token)) {
    header('Location: ../admin_appointment.php?msg=Invalid+Security+Token');
    exit();
}

$vehicle_model = trim($_POST['vehicle_model'] ?? '');
$user_id = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);
$service_id = filter_input(INPUT_POST, 'service_id', FILTER_VALIDATE_INT);
$booking_date = trim($_POST['booking_date'] ?? '');
$booking_time = trim($_POST['booking_time'] ?? '');
$status = trim($_POST['status'] ?? 'Pending');
$notes = trim($_POST['notes'] ?? '');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $booking_date);
$validTime = preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d(?::[0-5]\d)?$/', $booking_time) === 1;

$allowed_statuses = ['Pending', 'Cancelled', 'Completed'];

if (
    !$user_id ||
    !$service_id ||
    $vehicle_model === '' ||
    mb_strlen($vehicle_model) > 255 ||
    !$date ||
    $date->format('Y-m-d') !== $booking_date ||
    !$validTime ||
    !in_array($status, $allowed_statuses, true) ||
    mb_strlen($notes) > 2000
) {
    header('Location: ../admin_appointment.php?msg=' . urlencode('Please provide valid appointment details.'));
    exit();
}

$customerStmt = $pdo->prepare("SELECT userId FROM `user` WHERE userId = ? AND userRole = 'customer'");
$customerStmt->execute([$user_id]);
$serviceStmt = $pdo->prepare('SELECT serviceId FROM service WHERE serviceId = ?');
$serviceStmt->execute([$service_id]);
if (!$customerStmt->fetchColumn() || !$serviceStmt->fetchColumn()) {
    header('Location: ../admin_appointment.php?msg=' . urlencode('Choose an existing customer and service.'));
    exit();
}

try {

    $stmt = $pdo->prepare("
        INSERT INTO service_booking (userId, serviceId, bookingVehicleModel, bookingDate, bookingTime, bookingStatus, bookingNotes, bookingSource) VALUES (?, ?, ?, ?, ?, ?, ?, 'admin')
    ");

    $stmt->execute([
        $user_id,
        $service_id,
        $vehicle_model,
        $booking_date,
        $booking_time,
        $status,
        $notes !== '' ? $notes : null
    ]);

    header('Location: ../admin_appointment.php?msg=Appointment+successfully+created');
    exit();

} catch (PDOException $e) {
    error_log('Admin appointment creation failed: ' . $e->getMessage());
    header('Location: ../admin_appointment.php?msg=' . urlencode('Failed to create appointment. Check the submitted details and try again.'));
    exit();
}
?>