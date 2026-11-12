<?php
// /admin/actions/admin_users_delete.php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_users.php');
    exit();
}

requireValidCSRFToken();

$userId = filter_input(INPUT_POST, 'user_id', FILTER_VALIDATE_INT);

if (!$userId || $userId < 1) {
    header('Location: ../admin_users.php?msg=' . urlencode('Invalid user ID.'));
    exit();
}

if ((int) $userId === (int) $_SESSION['user_id']) {
    header('Location: ../admin_users.php?msg=' . urlencode('Action restricted: You cannot delete your own active admin account.'));
    exit();
}

try {
    $stmt = $pdo->prepare("DELETE FROM `user` WHERE userId = ?");
    $stmt->execute([$userId]);
    header('Location: ../admin_users.php?msg=' . urlencode('User account deleted successfully.'));
    exit();
} catch (Exception $e) {
    error_log('Admin account deletion failed: ' . $e->getMessage());
    header('Location: ../admin_users.php?msg=' . urlencode('Error deleting user from database.'));
    exit();
}