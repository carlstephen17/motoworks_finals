<?php
require_once __DIR__ . '/includes/auth.php';
header('Content-Type: application/json');

$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php?admin_login=1');
    exit;
}

$token = $_POST['csrf_token'] ?? null;
if (!verifyCSRFToken($token)) {
    echo json_encode(['success' => false, 'message' => 'Security verification failed. Refresh the page and try again.']);
    exit;
}

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if (empty($username) || empty($password)) {
    echo json_encode(['success' => false, 'message' => 'Please fill in all fields.']);
    exit;
}

if (!isset($pdo)) {
    echo json_encode(['success' => false, 'message' => 'Database connection error.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT * FROM `user` WHERE username = ? OR userEmail = ?");
    $stmt->execute([$username, $username]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user && password_verify($password, $user['userPassword'] ?? $user['password'] ?? '')) {
        session_regenerate_id(true);
        unset($_SESSION['csrf_token']);
        generateCSRFToken();

        $role = $user['userRole'] ?? 'customer';
        $isAdmin = ($role === 'admin');
        $userId = (int) $user['userId'];
        $firstName = $user['userFirstName'] ?? $user['username'];

        if (!in_array($role, ['admin', 'staff', 'customer'], true)) {
            echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
            exit;
        }

        $_SESSION['user_id'] = $userId;
        $_SESSION['username'] = $user['username'];
        $_SESSION['first_name'] = $firstName;
        $_SESSION['role'] = $role;
        $_SESSION['is_admin'] = $isAdmin;

        if ($isAdmin) {
            $_SESSION['admin_id'] = $userId;
        } else {
            unset($_SESSION['admin_id']);
        }

        $redirects = [
            'admin' => 'admin/admin_index.php',
            'staff' => 'staff/staff_index.php',
            'customer' => 'index.php',
        ];

        echo json_encode([
            'success' => true,
            'message' => 'Login successful!',
            'role' => $role,
            'is_admin' => $isAdmin,
            'user_name' => $firstName,
            'redirect' => $redirects[$role],
            'csrf_token' => $_SESSION['csrf_token'],
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid username or password.']);
    }
} catch (Throwable $e) {
    error_log('Login failed: ' . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error occurred.']);
}
