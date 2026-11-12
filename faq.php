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
    <title>FAQ - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <style>
        body { background-color: var(--color-bg, #121212); color: var(--color-text, #ffffff); font-family: 'Poppins', sans-serif; }
        .guide-hero { background: linear-gradient(135deg, #181818 0%, #222222 100%); padding: 60px 20px; text-align: center; border-radius: 12px; margin-bottom: 40px; border: 1px solid var(--color-border, #2a2a2a); }
        .guide-hero h1 { font-size: 2.3rem; font-weight: 800; color: var(--color-primary, #00bcd4); margin-bottom: 10px; }
        .guide-content { background: var(--color-card-bg, #1e1e1e); padding: 40px; border-radius: 12px; border: 1px solid var(--color-border, #2a2a2a); margin-bottom: 60px; line-height: 1.8; }
        .guide-content h3 { color: var(--color-primary, #00bcd4); margin-top: 25px; margin-bottom: 10px; font-size: 1.3rem; }
        .guide-content p { color: var(--color-text-muted, #b0b0b0); margin-bottom: 20px; }
    </style>
</head>
<body>
    <?php require_once 'components/header.php'; ?>
    <main class="container" style="padding-top: 40px;">
        <div class="guide-hero">
            <h1>Frequently Asked Questions</h1>
            <p>Find quick answers to common questions about our services and parts.</p>
        </div>
        <div class="guide-content">
            <h3>1. Do I need an appointment for servicing?</h3>
            <p>While walk-ins are welcome depending on bay availability, booking an appointment through our platform ensures your slot is prioritized.</p>

            <h3>2. What types of vehicles do you service?</h3>
            <p>We specialize in motorcycles, scooters, and light commercial motor vehicles with comprehensive diagnostic and repair capabilities.</p>

            <h3>3. Are replacement parts covered by a warranty?</h3>
            <p>Yes, all brand-new parts purchased and installed at Bencalo MotoWorks come with a standard shop warranty against manufacturer defects.</p>

            <h3>4. How can I check the status of my order or repair?</h3>
            <p>Log in to your account profile and navigate to your dashboard or orders section to track live updates on your service status.</p>
        </div>
    </main>
    <?php 
    require_once 'components/footer.php'; 
    require_once 'components/modals.php'; 
    ?>
</body>
</html>