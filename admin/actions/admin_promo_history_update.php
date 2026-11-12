<?php
// /admin/actions/admin_promo_history_update.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requireValidCSRFToken();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $bundleSlug = trim($_POST['bundle_slug'] ?? '');
    $status = trim($_POST['status'] ?? 'Pending');
    $allowedStatuses = ['Pending', 'Confirmed', 'Used', 'Completed', 'Cancelled'];

    if ($id && $bundleSlug !== '' && mb_strlen($bundleSlug) <= 100 && in_array($status, $allowedStatuses, true)) {
        $stmt = $pdo->prepare(
            "UPDATE promo_claim
             SET claimBundleSlug = ?, claimStatus = ?
             WHERE claimId = ? AND COALESCE(LOWER(TRIM(claimStatus)), '') <> 'completed'"
        );
        $stmt->execute([$bundleSlug, $status, $id]);

        if ($stmt->rowCount() > 0) {
            header('Location: ../admin_promo_history.php?msg=Promo+claim+updated+successfully');
            exit();
        }

        $checkStmt = $pdo->prepare("SELECT claimStatus FROM promo_claim WHERE claimId = ?");
        $checkStmt->execute([$id]);
        $existingStatus = $checkStmt->fetchColumn();
        $message = $existingStatus === false
            ? 'Promo claim not found'
            : (strtolower(trim((string) $existingStatus)) === 'completed'
                ? 'Completed promo claims cannot be edited'
                : 'Promo claim was not changed');
        header('Location: ../admin_promo_history.php?msg=' . urlencode($message));
        exit();
    }

    header('Location: ../admin_promo_history.php?msg=Invalid+promo+claim+parameters');
    exit();
}

header('Location: ../admin_promo_history.php');
exit();
