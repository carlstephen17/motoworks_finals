<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Administrator access required.');
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_promos.php?msg=' . urlencode('Invalid request.'));
    exit();
}
requireValidCSRFToken();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$active = $_POST['active'] ?? null;
if (!$id || $id < 1 || !in_array($active, ['0', '1', 0, 1], true)) {
    header('Location: ../admin_promos.php?msg=' . urlencode('Please choose a valid promo status.'));
    exit();
}

try {
    $stmt = $pdo->prepare('UPDATE promo SET promoActive = ? WHERE promoId = ?');
    $stmt->execute([(int) $active, $id]);
    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM promo WHERE promoId = ?');
        $exists->execute([$id]);
        if (!$exists->fetchColumn()) {
            header('Location: ../admin_promos.php?msg=' . urlencode('Promo not found.'));
            exit();
        }
    }
} catch (PDOException $error) {
    error_log('Promo status update failed: ' . $error->getMessage());
    header('Location: ../admin_promos.php?msg=' . urlencode('Unable to update promo status. Please try again.'));
    exit();
}

$status = (int) $active === 1 ? 'Active' : 'Inactive';
header('Location: ../admin_promos.php?msg=' . urlencode('Promo status updated to ' . $status . '.'));
exit();
