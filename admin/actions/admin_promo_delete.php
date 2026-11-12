<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_promos.php?msg=' . urlencode('Invalid request.'));
    exit();
}
requireValidCSRFToken();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id < 1) {
    header('Location: ../admin_promos.php?msg=' . urlencode('Invalid promo ID.'));
    exit();
}

try {
    $stmt = $pdo->prepare('DELETE FROM promo WHERE promoId = ?');
    $stmt->execute([$id]);
    $message = $stmt->rowCount() > 0
        ? 'Promo deleted from the catalog. Existing promo history was retained.'
        : 'Promo not found.';
} catch (PDOException $error) {
    error_log('Promo deletion failed: ' . $error->getMessage());
    header('Location: ../admin_promos.php?msg=' . urlencode('Unable to delete promo. Please try again.'));
    exit();
}

header('Location: ../admin_promos.php?msg=' . urlencode($message));
exit();
