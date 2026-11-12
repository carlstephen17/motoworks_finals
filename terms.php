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
    <title>Terms & Conditions - Bencalo MotoWorks</title>
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
            <h1>Terms & Conditions</h1>
            <p>Please review our general shop policies and conditions of trade.</p>
        </div>
        <div class="guide-content">
            <h3>1. Service Agreement</h3>
            <p>By bringing your vehicle to Bencalo MotoWorks or booking online, you authorize our technicians to perform diagnostic tests and agreed-upon repairs.</p>

            <h3>2. Quotations and Estimates</h3>
            <p>All service quotes are estimates based on initial inspections. If additional hidden issues are found during teardown, customers will be notified before work continues.</p>

            <h3>3. Unclaimed Vehicles & Parts</h3>
            <p>Vehicles left unclaimed after 30 days following service completion without prior arrangement may incur daily storage fees.</p>

            <h3>4. Limitation of Liability</h3>
            <p>Bencalo MotoWorks is not responsible for personal items left inside vehicles or preexisting mechanical failures unrelated to performed services.</p>
        </div>
    </main>
    <?php require_once 'components/footer.php'; ?>
    <?php require_once 'components/modals.php'; ?>
    ?>
</body>
</html>