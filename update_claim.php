<?php
// update_claim.php

require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access. Please log in.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

// Validate CSRF Token if sent
if (!isset($_POST['csrf_token'], $_SESSION['csrf_token']) || !hash_equals((string) $_SESSION['csrf_token'], (string) $_POST['csrf_token'])) {
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh and try again.']);
    exit;
}

require_once 'includes/config.php';

$userId = intval($_SESSION['user_id']);
if (!hasRole($userId, 'customer')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'You are not allowed to update this claim.']);
    exit;
}
$isAdmin = false;

$claimId = isset($_POST['claim_id']) ? intval($_POST['claim_id']) : 0;
$selectedOption = isset($_POST['selected_option']) ? trim($_POST['selected_option']) : '';
$appointment_date = isset($_POST['appointment_date']) ? trim($_POST['appointment_date']) : '';
$appointment_time = isset($_POST['appointment_time']) ? trim($_POST['appointment_time']) : '';
$status = isset($_POST['status']) ? trim($_POST['status']) : 'Pending';

if ($claimId <= 0 || empty($selectedOption) || empty($appointment_date) || empty($appointment_time)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all required fields.']);
    exit;
}

$date = DateTimeImmutable::createFromFormat('!Y-m-d', $appointment_date);
$dateErrors = DateTimeImmutable::getLastErrors();
if (!$date || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0)) || $date->format('Y-m-d') !== $appointment_date || $appointment_date < date('Y-m-d')) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid future booking date.']);
    exit;
}

if (preg_match('/^(\d{1,2}:\d{2}\s*[AP]M)(?:\s*-\s*\d{1,2}:\d{2}\s*[AP]M)?$/i', $appointment_time, $timeMatch)) {
    $time = DateTimeImmutable::createFromFormat('!h:i A', strtoupper(trim($timeMatch[1])));
    if ($time) {
        $appointment_time = $time->format('H:i:s');
    }
}
if (preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $appointment_time)) {
    $appointment_time .= ':00';
}
if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $appointment_time)) {
    echo json_encode(['success' => false, 'message' => 'Enter a valid booking time.']);
    exit;
}

try {
    if ($isAdmin) {
        $allowedStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'Pending';
        }
        $stmt = $pdo->prepare("
            UPDATE service_booking SET bookingBundleOption = ?, bookingDate = ?, bookingTime = ?, bookingStatus = ? WHERE bookingId = ? AND bookingBundleSlug IS NOT NULL
        ");
        $stmt->execute([$selectedOption, $appointment_date, $appointment_time, $status, $claimId]);
    } else {
        $stmt = $pdo->prepare("
            UPDATE service_booking
            SET bookingBundleOption = ?, bookingDate = ?, bookingTime = ?
            WHERE bookingId = ? AND userId = ? AND bookingBundleSlug IS NOT NULL
              AND COALESCE(LOWER(TRIM(bookingStatus)), '') <> 'completed'
        ");
        $stmt->execute([$selectedOption, $appointment_date, $appointment_time, $claimId, $userId]);
    }

    $existsSql = 'SELECT bookingStatus FROM service_booking WHERE bookingId = ? AND bookingBundleSlug IS NOT NULL';
    $existsParams = [$claimId];
    if (!$isAdmin) {
        $existsSql .= ' AND userId = ?';
        $existsParams[] = $userId;
    }
    $exists = $pdo->prepare($existsSql);
    $exists->execute($existsParams);
    $existingClaim = $exists->fetch(PDO::FETCH_ASSOC);
    if ($existingClaim && strtolower(trim((string) $existingClaim['bookingStatus'])) === 'completed') {
        echo json_encode(['success' => false, 'message' => 'Completed promo claims cannot be edited.']);
    } elseif ($stmt->rowCount() > 0 || $existingClaim) {
        echo json_encode(['success' => true, 'message' => 'Appointment updated successfully!']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Claim not found or you do not have permission to edit it.']);
    }

} catch (PDOException $e) {
    error_log('Promo booking update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update this promo booking right now.']);
}
?>