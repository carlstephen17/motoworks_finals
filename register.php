<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');

$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!verifyCSRFToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Security verification failed. Refresh the page and try again.']);
    exit;
}

$firstName = trim($_POST['first_name'] ?? '');
$lastName  = trim($_POST['last_name'] ?? '');
$username  = trim($_POST['username'] ?? '');
$email     = trim($_POST['email'] ?? '');
$phone     = trim($_POST['phone'] ?? '');
$password  = $_POST['password'] ?? '';

if (empty($firstName) || empty($lastName) || empty($username) || empty($email) || empty($phone) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'All fields are required.']);
    exit;
}

if (
    mb_strlen($firstName) > 50 ||
    mb_strlen($lastName) > 50 ||
    !preg_match('/^[A-Za-z0-9._-]{3,50}$/', $username) ||
    !filter_var($email, FILTER_VALIDATE_EMAIL) ||
    mb_strlen($email) > 100 ||
    strlen($password) < 8
) {
    echo json_encode(['success' => false, 'message' => 'Check the name, username, email, or password requirements and try again.']);
    exit;
}

if (!preg_match('/^09[0-9]{9}$/', $phone)) {
    echo json_encode(['success' => false, 'message' => 'Phone number must be 11 digits and start with 09.']);
    exit;
}

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit;
}

try {
    // Check both unique identifiers before inserting.
    $check = $pdo->prepare("SELECT userId FROM `user` WHERE username = ? OR userEmail = ?");
    $check->execute([$username, $email]);
    if ($check->rowCount() > 0) {
        echo json_encode(['success' => false, 'message' => 'Username or email already exists.']);
        exit;
    }

    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("INSERT INTO `user` (userFirstName, userLastName, username, userEmail, userPhone, userPassword, userRole) VALUES (?, ?, ?, ?, ?, ?, 'customer')");
    $stmt->execute([$firstName, $lastName, $username, $email, $phone, $hashedPassword]);

    $userId = $pdo->lastInsertId();
    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    generateCSRFToken();
    $_SESSION['user_id'] = $userId;
    $_SESSION['username'] = $username;
    $_SESSION['first_name'] = $firstName;
    $_SESSION['last_name'] = $lastName;
    $_SESSION['phone'] = $phone;
    $_SESSION['role'] = 'customer';

    echo json_encode(['success' => true, 'message' => 'Account created successfully!']);
} catch (Throwable $e) {
    error_log('Registration failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Registration failed. Please try again.']);
}
