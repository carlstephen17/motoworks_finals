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

$claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);
$promoSlug = trim((string) ($_POST['promo_slug'] ?? ''));
$selectedOption = trim((string) ($_POST['selected_option'] ?? ''));
$dateValue = (string) ($_POST['appointment_date'] ?? '');
$timeValue = (string) ($_POST['appointment_time'] ?? '');
$status = trim((string) ($_POST['status'] ?? ''));
$validStatuses = ['Pending', 'Confirmed', 'Used', 'Completed', 'Cancelled'];
$date = DateTimeImmutable::createFromFormat('!Y-m-d', $dateValue);
$time = DateTimeImmutable::createFromFormat('!H:i', $timeValue);
if (
    !$claimId || $claimId < 1 ||
    !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $promoSlug) ||
    $selectedOption === '' || mb_strlen($selectedOption) > 255 ||
    !$date || $date->format('Y-m-d') !== $dateValue ||
    !$time || $time->format('H:i') !== $timeValue ||
    !in_array($status, $validStatuses, true)
) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Please provide valid promo claim details.'));
    exit();
}

$ownerStmt = $pdo->prepare('SELECT claimStatus, claimBundleSlug, claimSelectedOption FROM promo_claim WHERE claimId = ?');
$ownerStmt->execute([$claimId]);
$claim = $ownerStmt->fetch(PDO::FETCH_ASSOC);
if (!$claim) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim not found.'));
    exit();
}
if (strtolower(trim((string) $claim['claimStatus'])) === 'completed') {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Completed promo claims cannot be edited.'));
    exit();
}

$promoStmt = $pdo->prepare('SELECT promoItemOptions FROM promo WHERE promoSlug = ? LIMIT 1');
$promoStmt->execute([$promoSlug]);
$promoOptions = $promoStmt->fetchColumn();
$allowedOptions = $promoOptions === false
    ? []
    : array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promoOptions) ?: [])));
$unchangedHistoricalOption = $promoSlug === $claim['claimBundleSlug']
    && $selectedOption === $claim['claimSelectedOption'];
if ($promoOptions === false || (!in_array($selectedOption, $allowedOptions, true) && !$unchangedHistoricalOption)) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('The selected promo option is unavailable.'));
    exit();
}

$updateStmt = $pdo->prepare("
    UPDATE promo_claim
    SET claimBundleSlug = ?, claimSelectedOption = ?, claimAppointmentDate = ?,
        claimAppointmentTime = ?, claimStatus = ?
    WHERE claimId = ?
      AND COALESCE(LOWER(TRIM(claimStatus)), '') <> 'completed'
");
$updateStmt->execute([
    $promoSlug,
    $selectedOption,
    $dateValue,
    $time->format('H:i:s'),
    $status,
    $claimId,
]);

if ($updateStmt->rowCount() === 0) {
    $verifyStmt = $pdo->prepare('SELECT claimStatus FROM promo_claim WHERE claimId = ?');
    $verifyStmt->execute([$claimId]);
    $currentStatus = $verifyStmt->fetchColumn();
    if ($currentStatus === false) {
        header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim not found.'));
        exit();
    }
    if (strtolower(trim((string) $currentStatus)) === 'completed') {
        header('Location: ../staff_promo_history.php?msg=' . urlencode('Completed promo claims cannot be edited.'));
        exit();
    }
}

header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim updated successfully.'));
exit();
