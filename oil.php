<?php
require_once __DIR__ . '/includes/auth.php';
$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}
$base_path = ''; // Empty because this file is in the root directory
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Importance of Oil Changes - Bencalo MotoWorks</title>
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
            <h1>Importance of Regular Oil Changes</h1>
            <p>Protect your engine, improve fuel economy, and extend vehicle lifespan.</p>
        </div>
        <div class="guide-content">
            <p>Engine oil is the lifeblood of your vehicle. Over time, heat, friction, and debris break down engine oil, reducing its ability to lubricate vital components.</p>
            
            <h3>1. Reduces Friction and Wear</h3>
            <p>Clean oil forms a protective film between moving metal parts, preventing internal components from grinding against each other and causing catastrophic engine wear.</p>

            <h3>2. Keeps Engine Cool</h3>
            <p>Friction within an engine generates massive amounts of heat. Fresh oil circulates properly to disperse heat away from combustion chambers and tight tolerances.</p>

            <h3>3. Improves Fuel Efficiency</h3>
            <p>Old, sludgy oil increases engine drag, forcing your engine to work harder and burn more fuel. Fresh oil keeps performance smooth and maximizes kilometers per liter.</p>

            <h3>4. Removes Engine Debris</h3>
            <p>As oil flows through your engine, it collects dirt, carbon particles, and metal shavings, trapping them safely in the oil filter for clean circulation.</p>
        </div>
    </main>
    <?php 
    require_once 'components/footer.php'; 
    require_once 'components/modals.php'; 
    ?>
</body>
</html>