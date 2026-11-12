<?php
require_once __DIR__ . '/includes/auth.php';
require_once 'includes/config.php';
header('Content-Type: application/json');

$response = [
    'success' => false,
    'message' => '',
    'reset_link' => ''
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Please submit your email address to request a password reset.';
    echo json_encode($response);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    $response['message'] = 'Invalid security token. Refresh the page and try again.';
    echo json_encode($response);
    exit;
}

$email = strtolower(trim($_POST['email'] ?? ''));

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $response['message'] = 'Please enter a valid email address.';
    echo json_encode($response);
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT userId, userEmail FROM `user` WHERE LOWER(userEmail) = LOWER(?) LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {
        $resetToken = bin2hex(random_bytes(32));
        $storedToken = hash('sha256', $resetToken);
        $updateStmt = $pdo->prepare(
            'UPDATE `user` SET userResetToken = ?, userResetExpiry = DATE_ADD(NOW(), INTERVAL 1 HOUR) WHERE userId = ?'
        );
        $updateStmt->execute([$storedToken, $user['userId']]);

        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $basePath = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/');
        $baseUrl = $protocol . '://' . ($_SERVER['SERVER_NAME'] ?? 'localhost') . $basePath;
        $resetLink = $baseUrl . '/reset_password.php?token=' . urlencode($resetToken);

        if (APP_DEBUG) {
            $response['reset_link'] = $resetLink;
            $response['message'] = 'Development reset link: ' . $resetLink;
        } else {
            $sent = mail(
                $user['userEmail'],
                'MotoWorks password reset',
                "Use this link within one hour to reset your password:\n\n" . $resetLink
            );
            if (!$sent) {
                error_log('Password reset email could not be sent.');
            }
        }
        $response['success'] = true;
    }
    if (!APP_DEBUG || empty($response['reset_link'])) {
        $response['message'] = 'If that email exists, reset instructions have been sent.';
    }
} catch (PDOException $e) {
    error_log('Password reset request failed: ' . $e->getMessage());
    http_response_code(500);
    $response['message'] = 'Unable to process the password reset request right now.';
}

echo json_encode($response);
exit;
