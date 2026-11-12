<?php
// claim_promo.php - Handles promo bundle claims
require_once __DIR__ . '/includes/auth.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'message' => 'You must be logged in to avail a promo.'
    ]);
    exit;
}

require_once __DIR__ . '/includes/config.php';

$userId = intval($_SESSION['user_id']);
if (!hasRole($userId, 'customer')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Only customer accounts can claim a promo.']);
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    http_response_code($_SERVER['REQUEST_METHOD'] === 'POST' ? 403 : 405);
    echo json_encode(['success' => false, 'message' => 'Invalid request or security token. Refresh the page and try again.']);
    exit;
}

try {
    // Make sure the logged-in user actually exists
    $userStmt = $pdo->prepare("SELECT userId as id, userFirstName as first_name, userLastName as last_name, userPhone as phone FROM `user` WHERE userId = ?");
    $userStmt->execute([$userId]);
    $user = $userStmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        echo json_encode([
            'success' => false,
            'message' => 'Your user account could not be found. Please log in again.'
        ]);
        exit;
    }

    // Get submitted values
    $bundleSlug = trim($_POST['bundle_slug'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $selectedOption = trim($_POST['selected_option'] ?? '');
    $appointmentDate = trim($_POST['appointment_date'] ?? '');
    $appointmentTime = trim($_POST['appointment_time'] ?? '');

    // Validate required fields
    if (
        $bundleSlug === '' ||
        !preg_match('/^[a-zA-Z0-9_-]{1,100}$/', $bundleSlug) ||
        $fullname === '' ||
        strlen($fullname) > 255 ||
        $phone === '' ||
        strlen($phone) > 20 ||
        !preg_match('/^[0-9+(). -]{7,20}$/', $phone) ||
        $selectedOption === '' ||
        strlen($selectedOption) > 255 ||
        $appointmentDate === '' ||
        $appointmentTime === ''
    ) {
        echo json_encode([
            'success' => false,
            'message' => 'Please complete all required fields.'
        ]);
        exit;
    }

    // Validate appointment date
    $dateObject = DateTime::createFromFormat('Y-m-d', $appointmentDate);

    if (!$dateObject || $dateObject->format('Y-m-d') !== $appointmentDate) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid appointment date.'
        ]);
        exit;
    }

    if ($appointmentDate < date('Y-m-d')) {
        echo json_encode([
            'success' => false,
            'message' => 'Appointment date cannot be in the past.'
        ]);
        exit;
    }

    $promoStmt = $pdo->prepare('SELECT promoItemOptions FROM promo WHERE promoSlug = ? AND promoActive = 1 LIMIT 1');
    $promoStmt->execute([$bundleSlug]);
    $promoOptions = $promoStmt->fetchColumn();
    $allowedOptions = $promoOptions === false
        ? []
        : array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promoOptions) ?: [])));
    if (!in_array($selectedOption, $allowedOptions, true)) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'The selected promo or promo option is no longer available.'
        ]);
        exit;
    }

    $timeObject = DateTime::createFromFormat('H:i', $appointmentTime);
    if (!$timeObject || $timeObject->format('H:i') !== $appointmentTime) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid appointment time.'
        ]);
        exit;
    }

    // Insert using users_id because this is the actual FK column
    $stmt = $pdo->prepare("
        INSERT INTO promo_claim (userId, claimBundleSlug, claimFullName, claimPhone, claimSelectedOption, claimAppointmentDate, claimAppointmentTime, claimStatus) VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending')
    ");

    $stmt->execute([
        $userId,
        $bundleSlug,
        $fullname,
        $phone,
        $selectedOption,
        $appointmentDate,
        $appointmentTime
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Promo bundle successfully claimed!'
    ]);

} catch (PDOException $e) {
    error_log('Promo claim failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to submit the promo claim right now.'
    ]);
}
?>