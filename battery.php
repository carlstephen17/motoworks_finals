<?php
require_once __DIR__ . '/includes/auth.php';
$configFile = __DIR__ . '/includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}
$base_path = ''; // Empty because this file is now in the root directory
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>5 Signs Your Battery is Failing - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/modals.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <style>
        body {
            background-color: var(--color-bg, #121212);
            color: var(--color-text, #ffffff);
            font-family: 'Poppins', sans-serif;
        }

        .guide-hero {
            background: linear-gradient(135deg, #181818 0%, #222222 100%);
            padding: 60px 20px;
            text-align: center;
            border-radius: 12px;
            margin-bottom: 40px;
            border: 1px solid var(--color-border, #2a2a2a);
        }

        .guide-hero h1 {
            font-size: 2.3rem;
            font-weight: 800;
            color: var(--color-border);
            margin-bottom: 10px;
        }

        .guide-content {
            background: var(--color-card-bg, #1e1e1e);
            padding: 40px;
            border-radius: 12px;
            border: 1px solid var(--color-border, #2a2a2a);
            margin-bottom: 60px;
            line-height: 1.8;
        }

        .guide-content h3 {
            color: var(--color-border);
            margin-top: 25px;
            margin-bottom: 10px;
            font-size: 1.3rem;
        }

        .guide-content p {
            color: var(--color-text-muted, #b0b0b0);
            margin-bottom: 20px;
        }
    </style>
</head>

<body>
    <?php require_once 'components/header.php'; ?>
    <main class="container" style="padding-top: 40px;">
        <div class="guide-hero">
            <h1>5 Signs Your Battery is Failing</h1>
            <p>Know the warning indicators before you get stranded on the road.</p>
        </div>
        <div class="guide-content">
            <p>Your vehicle's battery is the heart of its electrical system. Spotting issues early can save you from costly breakdowns. Here are the 5 major warning signs:</p>

            <h3>1. Slow Engine Crank</h3>
            <p>When you attempt to start your car, the engine takes longer than usual to turn over or sounds sluggish. This means the battery's charge capacity is degrading.</p>

            <h3>2. Dim Headlights</h3>
            <p>If your headlights lose brightness when idling or when accessories like the AC and radio are turned on, the battery isn't holding enough charge to power auxiliary electronics efficiently.</p>

            <h3>3. Click-Click Sound When Turning the Key</h3>
            <p>A clicking noise upon ignition typically indicates that the starter motor isn't getting enough electrical current from the battery to engage properly.</p>

            <h3>4. Check Engine or Battery Warning Light</h3>
            <p>Modern dashboard computers track voltage levels. If the dedicated battery icon or a general check engine light illuminates, have your charging system tested immediately.</p>

            <h3>5. Old Age (3 to 5 Years)</h3>
            <p>Most automotive batteries last between 3 to 5 years depending on climate conditions and driving habits. If yours is within or past this window, consider a preventive replacement at Bencalo MotoWorks.</p>
        </div>
    </main>
    <?php 
    require_once 'components/footer.php'; 
    require_once 'components/modals.php'; 
    ?>
</body>

</html> 