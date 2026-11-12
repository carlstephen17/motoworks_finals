<?php
// /admin/actions/admin_users_update.php
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
$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$role = $_POST['role'] ?? '';
$allowedRoles = ['customer', 'staff', 'admin'];

if (
    !$userId ||
    $firstName === '' ||
    $lastName === '' ||
    mb_strlen($firstName) > 50 ||
    mb_strlen($lastName) > 50 ||
    !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($email) > 100 ||
    !preg_match('/^09[0-9]{9}$/', $phone) ||
    !in_array($role, $allowedRoles, true)
) {
    header('Location: ../admin_users.php?msg=' . urlencode('Please provide valid account details.'));
    exit();
}

if ((int) $userId === (int) $_SESSION['user_id'] && $role !== 'admin') {
    header('Location: ../admin_users.php?msg=' . urlencode('You cannot remove your own administrator role.'));
    exit();
}

try {
    $stmt = $pdo->prepare(
        'UPDATE `user`
         SET userFirstName = ?, userLastName = ?, username = ?, userEmail = ?, userPhone = ?, userRole = ?
         WHERE userId = ?'
    );
    $stmt->execute([$firstName, $lastName, $username, $email, $phone, $role, $userId]);
    header('Location: ../admin_users.php?msg=' . urlencode('User account updated successfully.'));
} catch (PDOException $error) {
    error_log('Admin account update failed: ' . $error->getMessage());
    $message = $error->getCode() === '23000'
        ? 'Username or email is already in use.'
        : 'Unable to update the account. Please try again.';
    header('Location: ../admin_users.php?msg=' . urlencode($message));
}
exit();