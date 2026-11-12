<?php
require_once __DIR__ . '/includes/auth.php';
require_once 'includes/config.php';
generateCSRFToken();

$message = '';
$status = '';
$validToken = false;
$token = $_GET['token'] ?? '';
if (!is_string($token) || !preg_match('/^[a-f0-9]{64}$/', $token)) {
    $token = '';
}

if (!empty($token)) {
    $stmt = $pdo->prepare('SELECT userId FROM `user` WHERE userResetToken = ? AND userResetExpiry > NOW()');
    $stmt->execute([hash('sha256', $token)]);
    $user = $stmt->fetch();

    if ($user) {
        $validToken = true;
        
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
                http_response_code(403);
                $message = 'Invalid security token. Refresh the page and try again.';
                $status = 'error';
            } else {
            $newPassword = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if (!empty($newPassword) && !empty($confirmPassword)) {
                if ($newPassword === $confirmPassword) {
                    if (strlen($newPassword) >= 8) {
                        $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

                        $updateStmt = $pdo->prepare('UPDATE `user` SET userPassword = ?, userResetToken = NULL, userResetExpiry = NULL WHERE userId = ? AND userResetToken = ? AND userResetExpiry > NOW()');
                        if ($updateStmt->execute([$hashedPassword, $user['userId'], hash('sha256', $token)]) && $updateStmt->rowCount() > 0) {
                            $message = "Your password has been successfully reset! You can now log in.";
                            $status = "success";
                            $validToken = false;
                        } else {
                            $message = "Something went wrong. Please try again.";
                            $status = "error";
                        }
                    } else {
                        $message = "Password must be at least 8 characters long.";
                        $status = "error";
                    }
                } else {
                    $message = "Passwords do not match.";
                    $status = "error";
                }
            } else {
                $message = "Please fill in all fields.";
                $status = "error";
            }
            }
        }
    } else {
        $message = "Invalid or expired password reset token.";
        $status = "error";
    }
} else {
    $message = "No reset token provided.";
    $status = "error";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Bencalo MotoWorks</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/modals.css">
</head>
<body style="display: flex; justify-content: center; align-items: center; height: 100vh; background: var(--color-bg);">
    <div style="background: var(--color-bg-alt); padding: 30px; border-radius: var(--radius-md); width: 100%; max-width: 400px; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 1px solid var(--color-border);">
        <h2 style="text-align: center; margin-bottom: 20px; color: var(--color-text);">Create New Password</h2>
        
        <?php if (!empty($message)): ?>
            <p style="text-align: center; margin-bottom: 20px; color: <?= $status === 'error' ? '#ff6b6b' : '#4cd137' ?>; font-weight: 600;">
                <?= htmlspecialchars($message) ?>
            </p>
        <?php endif; ?>

        <?php if ($validToken): ?>
            <form action="" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div style="margin-bottom: 15px;">
                    <input type="password" name="password" placeholder="New Password" required style="width:100%; padding:12px; background:var(--color-bg); border:1px solid var(--color-primary); color:var(--color-text); border-radius:var(--radius-sm);">
                </div>
                <div style="margin-bottom: 20px;">
                    <input type="password" name="confirm_password" placeholder="Confirm New Password" required style="width:100%; padding:12px; background:var(--color-bg); border:1px solid var(--color-primary); color:var(--color-text); border-radius:var(--radius-sm);">
                </div>
                <button type="submit" class="btn btn-primary btn-block" style="width:100%;">Update Password</button>
            </form>
        <?php else: ?>
            <div style="text-align: center; margin-top: 20px;">
                <a href="index.php" class="btn btn-primary" style="display: inline-block; padding: 10px 20px; text-decoration: none;">Return to Home</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>