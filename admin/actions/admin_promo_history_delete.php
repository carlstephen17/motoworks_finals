<?php
// /admin/actions/admin_promo_history_delete.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

$id = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT)
    : false;

if ($id) {
    requireValidCSRFToken();
    $stmt = $pdo->prepare('DELETE FROM promo_claim WHERE claimId = ?');
    $stmt->execute([$id]);

    header('Location: ../admin_promo_history.php?msg=Promo+claim+deleted+successfully');
    exit();
}

header('Location: ../admin_promo_history.php?msg=' . urlencode($_SERVER['REQUEST_METHOD'] === 'POST' ? 'Invalid promo claim.' : 'Invalid request.'));
exit();
