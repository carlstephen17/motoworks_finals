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
if (!$claimId || $claimId < 1) {
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Invalid promo claim ID.'));
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM promo_claim WHERE claimId = ?');
    $stmt->execute([$claimId]);
    if ($stmt->rowCount() !== 1) {
        header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim not found.'));
        exit();
    }
} catch (PDOException $error) {
    error_log('Staff promo claim deletion failed: ' . $error->getMessage());
    header('Location: ../staff_promo_history.php?msg=' . urlencode('Unable to delete this promo claim.'));
    exit();
}

header('Location: ../staff_promo_history.php?msg=' . urlencode('Promo claim deleted.'));
exit();
