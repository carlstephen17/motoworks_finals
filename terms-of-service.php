<?php
require_once __DIR__ . '/includes/auth.php';
$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}
$base_path = ''; 
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terms of Service - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <style>
        body { background-color: var(--color-bg, #121212); color: var(--color-text, #ffffff); font-family: 'Poppins', sans-serif; }
        .guide-hero { background: linear-gradient(135deg, #181818 0%, #222222 100%); padding: 60px 20px; text-align: center; border-radius: 12px; margin-bottom: 40px; border: 1px solid var(--color-border, #2a2a2a); }
        .guide-hero h1 { font-size: 2.3rem; font-weight: 800; color: var(--color-border); margin-bottom: 10px; }
        .guide-content { background: var(--color-card-bg, #1e1e1e); padding: 40px; border-radius: 12px; border: 1px solid var(--color-border, #2a2a2a); margin-bottom: 60px; line-height: 1.8; }
        .guide-content h3 { color: var(--color-border); margin-top: 25px; margin-bottom: 10px; font-size: 1.3rem; }
        .guide-content p { color: var(--color-text-muted, #b0b0b0); margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php require_once 'components/header.php'; ?>
    <main class="container" style="padding-top: 40px;">
        <div class="guide-hero">
            <h1>Terms of Service</h1>
            <p>Guidelines for using the Bencalo MotoWorks website and digital platform.</p>
        </div>
        <div class="guide-content">
            <h3>1. Acceptance of Website Terms</h3>
            <p>By accessing or using our website, you agree to comply with and be bound by these digital terms of service.</p>

            <h3>2. User Accounts</h3>
            <p>You are responsible for maintaining the confidentiality of your account credentials and password. Notify us immediately if unauthorized access is suspected.</p>

            <h3>3. Online Bookings and Purchases</h3>
            <p>All online bookings and orders are subject to availability and confirmation by our administrative team.</p>

            <h3>4. Modification of Platform</h3>
            <p>Bencalo MotoWorks reserves the right to modify, suspend, or discontinue any feature, service, or content on this web platform at any time without prior notice.</p>
        </div>
    </main>
    <?php 
    require_once 'components/footer.php'; 
    require_once 'components/modals.php'; 
    ?>
</body>
</html>