<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_users.php');
    exit();
}

requireValidCSRFToken();

$firstName = trim((string) ($_POST['first_name'] ?? ''));
$lastName = trim((string) ($_POST['last_name'] ?? ''));
$username = trim((string) ($_POST['username'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$password = $_POST['password'] ?? '';
$role = $_POST['role'] ?? '';
$allowedRoles = ['customer', 'staff', 'admin'];

if (
    $firstName === '' ||
    $lastName === '' ||
    mb_strlen($firstName) > 50 ||
    mb_strlen($lastName) > 50 ||
    !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($email) > 100 ||
    !preg_match('/^09[0-9]{9}$/', $phone) ||
    !is_string($password) ||
    strlen($password) < 8 ||
    !in_array($role, $allowedRoles, true)
) {
    header('Location: ../admin_users.php?msg=' . urlencode('Please provide valid account details.'));
    exit();
}

$duplicate = $pdo->prepare('SELECT userId FROM `user` WHERE username = ? OR userEmail = ? LIMIT 1');
$duplicate->execute([$username, $email]);
if ($duplicate->fetchColumn()) {
    header('Location: ../admin_users.php?msg=' . urlencode('Username or email is already in use.'));
    exit();
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO `user` (username, userFirstName, userLastName, userEmail, userPhone, userPassword, userRole)
         VALUES (?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $username,
        $firstName,
        $lastName,
        $email,
        $phone,
        password_hash($password, PASSWORD_DEFAULT),
        $role,
    ]);

    header('Location: ../admin_users.php?msg=' . urlencode(ucfirst($role) . ' account created successfully.'));
    exit();
} catch (PDOException $error) {
    error_log('Admin account creation failed: ' . $error->getMessage());
    $message = $error->getCode() === '23000'
        ? 'Username or email is already in use.'
        : 'Unable to create the account. Please try again.';
    header('Location: ../admin_users.php?msg=' . urlencode($message));
    exit();
}
