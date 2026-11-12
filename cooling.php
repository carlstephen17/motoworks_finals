<?php
require_once __DIR__ . '/includes/auth.php';
$configFile = __DIR__ . 'includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cooling System Maintenance - Bencalo MotoWorks</title>
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
            <h1>Cooling System Maintenance</h1>
            <p>Prevent overheating and protect your engine block from thermal damage.</p>
        </div>
        <div class="guide-content">
            <p>Your vehicle's cooling system regulates engine temperature through coolant circulation, water pumps, radiators, and thermostats.</p>
            
            <h3>1. Preventing Overheating</h3>
            <p>Maintaining coolant levels and flushing old fluid prevents your engine from overheating during heavy traffic or grueling road climbs.</p>

            <h3>2. Coolant Flushes</h3>
            <p>Over time, coolant loses its anti-corrosive properties and accumulates contaminants. Regular flushes keep your radiator and water pump running corrosion-free.</p>

            <h3>3. Inspecting Hoses and Clamps</h3>
            <p>Rubber hoses degrade, crack, or leak under high pressure and temperature. Routine checks prevent sudden coolant leaks and total pressure loss.</p>
        </div>
    </main>
    <?php require_once 'components/footer.php'; require_once 'components/modals.php'; ?>
</body>
</html>