<?php
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

function respond(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(['success' => false, 'message' => 'Method not allowed.'], 405);
    }

    $sessionToken = $_SESSION['csrf_token'] ?? '';
    $submittedToken = $_POST['csrf_token'] ?? '';
    if (!is_string($sessionToken) || $sessionToken === '' || !is_string($submittedToken) || !hash_equals($sessionToken, $submittedToken)) {
        respond(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.'], 403);
    }

    $userId = filter_var($_SESSION['user_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$userId || $userId < 1) {
        respond(['success' => false, 'message' => 'Please log in to book a service.'], 401);
    }

    require_once __DIR__ . '/includes/config.php';

    $serviceValue = $_POST['service_id'] ?? $_POST['services_id'] ?? null;
    $serviceId = filter_var($serviceValue, FILTER_VALIDATE_INT);
    $vehicleModel = trim((string) ($_POST['vehicle_model'] ?? $_POST['vehicle'] ?? ''));
    $bookingDate = trim((string) ($_POST['booking_date'] ?? $_POST['appointment_date'] ?? ''));
    $bookingTime = trim((string) ($_POST['booking_time'] ?? $_POST['appointment_time'] ?? ''));
    $promoSlug = trim((string) ($_POST['promo_slug'] ?? $_POST['bundle_slug'] ?? ''));
    $promoOption = trim((string) ($_POST['promo_selected_option'] ?? $_POST['selected_option'] ?? ''));
    $notes = trim((string) ($_POST['notes'] ?? ''));

    if (!$serviceId || $serviceId < 1 || $vehicleModel === '' || $bookingDate === '' || $bookingTime === '') {
        respond(['success' => false, 'message' => 'Select a service and complete the vehicle, date, and time fields.'], 422);
    }

    if (mb_strlen($vehicleModel) > 255 || mb_strlen($promoSlug) > 100 || mb_strlen($promoOption) > 255) {
        respond(['success' => false, 'message' => 'One or more fields exceed the allowed length.'], 422);
    }

    if (preg_match('/^(\d{1,2}:\d{2}\s*[AP]M)(?:\s*-\s*\d{1,2}:\d{2}\s*[AP]M)?$/i', $bookingTime, $timeMatch)) {
        $time = DateTimeImmutable::createFromFormat('!h:i A', strtoupper(trim($timeMatch[1])));
        if ($time) {
            $bookingTime = $time->format('H:i:s');
        }
    }
    if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $bookingTime)) {
        $bookingTime .= ':00';
    }
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $bookingTime)) {
        respond(['success' => false, 'message' => 'Enter a valid booking time.'], 422);
    }

    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $bookingDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $bookingDate) {
        respond(['success' => false, 'message' => 'Enter a valid booking date.'], 422);
    }
    if ($bookingDate < date('Y-m-d')) {
        respond(['success' => false, 'message' => 'Booking date cannot be in the past.'], 422);
    }

    if ($promoSlug !== '' && (!preg_match('/^[a-zA-Z0-9_-]+$/', $promoSlug) || $promoOption === '')) {
        respond(['success' => false, 'message' => 'Choose a valid promo and promo option.'], 422);
    }

    $userStmt = $pdo->prepare('SELECT userId as id, userFirstName as first_name, userLastName as last_name, userEmail as email, userPhone as phone, userId, userFirstName, userLastName, userEmail, userPhone FROM `user` WHERE userId = ? LIMIT 1');
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        respond(['success' => false, 'message' => 'Your account could not be found. Please sign in again.'], 401);
    }

    $serviceStmt = $pdo->prepare('SELECT serviceId as id, serviceName as name, serviceId, serviceName FROM service WHERE serviceId = ? AND serviceActive = 1 LIMIT 1');
    $serviceStmt->execute([$serviceId]);
    $service = $serviceStmt->fetch(PDO::FETCH_ASSOC);
    if (!$service) {
        respond(['success' => false, 'message' => 'The selected service is unavailable.'], 422);
    }

    if ($promoSlug !== '') {
        $promoStmt = $pdo->prepare('SELECT promoItemOptions FROM promo WHERE promoSlug = ? AND promoActive = 1 LIMIT 1');
        $promoStmt->execute([$promoSlug]);
        $promoOptions = $promoStmt->fetchColumn();
        $allowedOptions = $promoOptions === false
            ? []
            : array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promoOptions) ?: [])));
        if (!in_array($promoOption, $allowedOptions, true)) {
            respond(['success' => false, 'message' => 'The selected promo or promo option is no longer available.'], 422);
        }
    }

    $insert = $pdo->prepare(
        'INSERT INTO service_booking (userId, serviceId, bookingVehicleModel, bookingDate, bookingTime, bookingBundleSlug, bookingBundleOption, bookingStatus, bookingNotes, bookingCreatedAt) VALUES (:user_id, :service_id, :vehicle_model, :booking_date, :booking_time, :bundle_slug, :bundle_option, :status, :notes, CURRENT_TIMESTAMP)'
    );
    $insert->execute([
        ':user_id' => $userId,
        ':service_id' => $serviceId,
        ':vehicle_model' => $vehicleModel,
        ':booking_date' => $bookingDate,
        ':booking_time' => $bookingTime,
        ':bundle_slug' => $promoSlug !== '' ? $promoSlug : null,
        ':bundle_option' => $promoOption !== '' ? $promoOption : null,
        ':status' => 'Pending',
        ':notes' => $notes !== '' ? $notes : null,
    ]);

    respond([
        'success' => true,
        'message' => $promoSlug !== '' ? 'Promo booking submitted successfully.' : 'Service booking submitted successfully.',
        'booking_id' => (int) $pdo->lastInsertId(),
        'customer_name' => trim($user['first_name'] . ' ' . $user['last_name']),
        'service_name' => $service['name'],
        'booking_date' => $bookingDate,
        'booking_time' => $bookingTime,
    ]);
} catch (Throwable $error) {
    error_log('Booking submission failed: ' . $error->getMessage());
    respond(['success' => false, 'message' => 'Unable to submit the booking right now. Please try again.'], 500);
}