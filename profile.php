<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/image_upload.php';
generateCSRFToken();

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}

$success_message = '';
$error_message = '';
$userId = $_SESSION['user_id'];

// Default user array ensuring all keys exist to prevent undefined index/key warnings
$user = [
    'username' => '',
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'avatar' => ''
];

if (isset($pdo)) {
    try {
        $stmt = $pdo->prepare("SELECT username, userFirstName AS first_name, userLastName AS last_name, userEmail AS email, userPhone AS phone, userAvatar AS avatar FROM `user` WHERE userId = ?");
        $stmt->execute([$userId]);
        $fetchedUser = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($fetchedUser) {
            $user = array_merge($user, $fetchedUser);
        }
    } catch (Exception $e) {
        $error_message = 'Database error: ' . $e->getMessage();
    }
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        $error_message = 'Invalid security token. Please refresh and try again.';
    } else {
        $firstName = trim((string) ($_POST['first_name'] ?? ''));
        $lastName = trim((string) ($_POST['last_name'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $phone = trim((string) ($_POST['phone'] ?? ''));
        $avatarPath = $user['avatar']; // Retain existing avatar by default

        if ($firstName === '' || $lastName === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($firstName) > 50 || mb_strlen($lastName) > 50 || mb_strlen($email) > 100 || ($phone !== '' && !preg_match('/^09[0-9]{9}$/', $phone))) {
            $error_message = 'First name, last name, and email are required fields.';
        } else {
            try {
                $newAvatar = saveSiteImageUpload($_FILES['avatar'] ?? [], 'avatars');
                if ($newAvatar !== null) {
                    $avatarPath = $newAvatar;
                }
            } catch (RuntimeException $uploadError) {
                $error_message = $uploadError->getMessage();
            }

            if (empty($error_message) && isset($pdo)) {
                try {
                    $stmt = $pdo->prepare("UPDATE `user` SET userFirstName = ?, userLastName = ?, userEmail = ?, userPhone = ?, userAvatar = ? WHERE userId = ?");
                    $stmt->execute([$firstName, $lastName, $email, $phone, $avatarPath, $userId]);

                    if ($avatarPath !== $user['avatar'] && !empty($user['avatar']) && !removeSiteAvatarUpload($user['avatar'])) {
                        error_log('Unable to remove replaced profile avatar: ' . $user['avatar']);
                    }

                    // Update session variables
                    $_SESSION['user_name'] = $firstName . ' ' . $lastName;
                    $_SESSION['user_email'] = $email;

                    $user['first_name'] = $firstName;
                    $user['last_name'] = $lastName;
                    $user['email'] = $email;
                    $user['phone'] = $phone;
                    $user['avatar'] = $avatarPath;

                    $success_message = 'Profile updated successfully!';
                } catch (PDOException $e) {
                    if ($avatarPath !== $user['avatar'] && !removeSiteAvatarUpload($avatarPath)) {
                        error_log('Unable to remove uploaded avatar after profile update failed: ' . $avatarPath);
                    }
                    error_log('Profile update failed: ' . $e->getMessage());
                    $error_message = $e->getCode() === '23000'
                        ? 'That email address is already associated with an account.'
                        : 'Unable to update your profile right now. Please try again.';
                }
            } elseif (!isset($pdo)) {
                $error_message = 'Database connection not available.';
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <style>
        body {
            background-color: var(--color-bg, #121212);
            color: var(--color-text, #ffffff);
            font-family: 'Poppins', sans-serif;
        }

        .profile-hero {
            background: linear-gradient(135deg, #181818 0%, #222222 100%);
            color: #fff;
            padding: 50px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            border: 1px solid var(--color-border, #2a2a2a);
        }

        .profile-hero h1 {
            font-size: 2.2rem;
            font-weight: 800;
            margin-bottom: 10px;
            color: var(--color-border);
        }

        .profile-wrapper {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 40px;
            margin-bottom: 60px;
        }

        @media (max-width: 900px) {
            .profile-wrapper {
                grid-template-columns: 1fr;
            }
        }

        .profile-sidebar,
        .profile-form-card {
            background: var(--color-card-bg, #1e1e1e);
            padding: 40px;
            border-radius: 12px;
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.2);
            border-top: 4px solid var(--color-primary, #00bcd4);
            border-left: 1px solid var(--color-border, #2a2a2a);
            border-right: 1px solid var(--color-border, #2a2a2a);
            border-bottom: 1px solid var(--color-border, #2a2a2a);
            text-align: center;
        }

        .profile-form-card {
            text-align: left;
        }

        .profile-avatar-container {
            position: relative;
            width: 120px;
            height: 120px;
            margin: 0 auto 20px auto;
            border-radius: 50%;
            overflow: hidden;
            border: 3px solid var(--color-primary, #00bcd4);
            box-shadow: 0 5px 15px rgba(0, 188, 212, 0.3);
        }

        .profile-avatar-container img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .profile-sidebar h3 {
            font-size: 1.3rem;
            font-weight: 700;
            color: #fff;
            margin-bottom: 5px;
        }

        .profile-sidebar p {
            color: var(--color-text-muted, #a0a0a0);
            font-size: 0.9rem;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }

        @media (max-width: 600px) {
            .form-row {
                grid-template-columns: 1fr;
            }
        }

        .form-group label {
            display: block;
            font-weight: 600;
            color: var(--color-text, #e0e0e0);
            margin-bottom: 8px;
            font-size: 0.9rem;
        }

        .form-control {
            width: 100%;
            padding: 12px 16px;
            background: #252525;
            border: 1px solid var(--color-border, #333);
            color: #fff;
            border-radius: 6px;
            font-family: 'Poppins', sans-serif;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--color-primary, #00bcd4);
            box-shadow: 0 0 0 3px rgba(0, 188, 212, 0.15);
            background: #2a2a2a;
        }

        .btn-submit {
            background: var(--color-primary);
            color: var(--color-text);
            font-weight: 700;
            padding: 14px 28px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            width: 100%;
            font-size: 1rem;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 188, 212, 0.2);
        }

        .btn-submit:hover {
            background: var(--color-border);
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 188, 212, 0.3);
        }

        .alert-box {
            padding: 15px 20px;
            border-radius: 6px;
            margin-bottom: 25px;
            font-size: 0.95rem;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            color: #75b798;
            border: 1px solid rgba(40, 167, 69, 0.4);
        }

        .alert-error {
            background: rgba(220, 53, 69, 0.2);
            color: #ea868f;
            border: 1px solid rgba(220, 53, 69, 0.4);
        }
    </style>
</head>

<body>

    <?php require_once 'components/header.php'; ?>

    <main class="container" style="padding-top: 40px;">
        <div class="profile-hero">
            <h1>Account Settings</h1>
            <p>Manage your profile information and update your personal credentials.</p>
        </div>

        <?php if (!empty($success_message)): ?>
            <div class="alert-box alert-success"><i class="fa-solid fa-circle-check" style="margin-right: 8px;"></i> <?= htmlspecialchars($success_message) ?></div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert-box alert-error"><i class="fa-solid fa-circle-exclamation" style="margin-right: 8px;"></i> <?= htmlspecialchars($error_message) ?></div>
        <?php endif; ?>

        <div class="profile-wrapper">
            <!-- Sidebar Profile Card -->
            <div class="profile-sidebar">
                <div class="profile-avatar-container">
                    <img src="<?= !empty($user['avatar']) ? htmlspecialchars($user['avatar']) : 'images/default-avatar.png' ?>" alt="User Avatar">
                </div>
                <h3><?= htmlspecialchars(trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?: 'System User') ?></h3>
                <p>@<?= htmlspecialchars($user['username'] ?? '') ?></p>
                <form method="POST" action="logout.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                    <button type="submit" class="btn-submit" style="background: #dc3545; color: #fff;">Logout</button>
                </form>
            </div>

            <!-- Profile Edit Form Card -->
            <div class="profile-form-card">
                <h3>Edit Profile Information</h3>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'] ?? '') ?>">

                    <div class="form-row">
                        <div class="form-group">
                            <label for="first_name">First Name</label>
                            <input type="text" id="first_name" name="first_name" class="form-control"
                                value="<?= htmlspecialchars($user['first_name'] ?? '') ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="last_name">Last Name</label>
                            <input type="text" id="last_name" name="last_name" class="form-control"
                                value="<?= htmlspecialchars($user['last_name'] ?? '') ?>" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" class="form-control"
                            value="<?= htmlspecialchars($user['email'] ?? '') ?>" required>
                    </div>

                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="text" id="phone" name="phone" class="form-control"
                            value="<?= htmlspecialchars($user['phone'] ?? '') ?>" placeholder="Enter phone number">
                    </div>

                    <div class="form-group">
                        <label for="avatar">Profile Picture <span style="color: #888; font-weight: normal;">(Optional)</span></label>
                        <input type="file" id="avatar" name="avatar" class="file-upload-input" accept="image/png, image/jpeg, image/webp">
                        <small style="color: #888; display: block; margin-top: 5px;">Accepted formats: JPG, PNG, WEBP</small>
                    </div>

                    <button type="submit" class="btn-submit">Save Changes</button>
                </form>
            </div>
        </div>
    </main>

    <?php
    require_once 'components/footer.php';
    ?>

</body>

</html>