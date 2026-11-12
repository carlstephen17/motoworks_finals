<?php
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isStaff($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Staff access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../staff_promo_history.php');
    exit();
}
requireValidCSRFToken();

$customerId = filter_input(INPUT_POST, 'customer_id', FILTER_VALIDATE_INT);
$promoSlug = trim((string) ($_POST['promo_slug'] ?? ''));
$selectedOption = trim((string) ($_POST['selected_option'] ?? ''));
$dateValue = (string) ($_POST['appointment_date'] ?? '');
$timeValue = (string) ($_POST['appointment_time'] ?? '');
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);
if (
    !$customerId || $customerId < 1 ||
    !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $promoSlug) ||
    $selectedOption === '' || mb_strlen($selectedOption) > 255 ||
    !$date || $date->format('Y-m-d') !== $dateValue || $dateValue < date('Y-m-d') ||
    !$time || $time->format('H:i') !== $timeValue
) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Please provide valid customer, promo, option, and appointment details.'));
    exit();
}

$customerStmt = $pdo->prepare("
    SELECT userId, CONCAT_WS(' ', userFirstName, userLastName) AS fullName, userPhone
    FROM `user`
    WHERE userId = ? AND userRole = 'customer'
");
$customerStmt->execute([$customerId]);
$customer = $customerStmt->fetch(PDO::FETCH_ASSOC);
if (!$customer || trim((string) $customer['fullName']) === '' || !preg_match('/^[0-9+(). -]{7,20}$/', (string) $customer['userPhone'])) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Choose a customer with a valid name and phone number.'));
    exit();
}

$promoStmt = $pdo->prepare('SELECT promoItemOptions FROM promo WHERE promoSlug = ? AND promoActive = 1 LIMIT 1');
$promoStmt->execute([$promoSlug]);
$promoOptions = $promoStmt->fetchColumn();
$allowedOptions = $promoOptions === false
    ? []
    : array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promoOptions) ?: [])));
if (!in_array($selectedOption, $allowedOptions, true)) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('The selected promo option is unavailable.'));
    exit();
}

try {
    $stmt = $pdo->prepare("
        INSERT INTO promo_claim
            (userId, claimFullName, claimPhone, claimBundleSlug, claimSelectedOption,
             claimAppointmentDate, claimAppointmentTime, claimStatus, claimCreatedByStaffId)
        VALUES (?, ?, ?, ?, ?, ?, ?, 'Pending', ?)
    ");
    $stmt->execute([
        $customerId,
        trim((string) $customer['fullName']),
        trim((string) $customer['userPhone']),
        $promoSlug,
        $selectedOption,
        $dateValue,
        $time->format('H:i:s'),
        (int) $_SESSION['user_id'],
    ]);
} catch (PDOException $error) {
    error_log('Staff promo claim creation failed: ' . $error->getMessage());
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Unable to create the promo claim. Please try again.'));
    exit();
}

header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim created successfully.'));
exit();
