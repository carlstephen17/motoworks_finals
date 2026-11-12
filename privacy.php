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
    <title>Privacy Policy - Bencalo MotoWorks</title>
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
            <h1>Privacy Policy</h1>
            <p>How we handle and protect your personal information.</p>
        </div>
        <div class="guide-content">
            <h3>1. Information We Collect</h3>
            <p>We collect personal information when you register an account, book services, or purchase parts, including your name, contact details, and vehicle specifications.</p>

            <h3>2. How We Use Your Data</h3>
            <p>Your information is used strictly to process transactions, manage appointment schedules, maintain service histories, and communicate updates regarding your account.</p>

            <h3>3. Data Security</h3>
            <p>We implement robust administrative and technical safeguards to secure your personal data against unauthorized access, disclosure, or alteration.</p>

            <h3>4. Third-Party Disclosure</h3>
            <p>We do not sell, trade, or transfer your personally identifiable information to external third parties without your explicit consent.</p>
        </div>
    </main>
    <?php 
    require_once 'components/footer.php'; 
    require_once 'components/modals.php'; 
    ?>
</body>
</html>