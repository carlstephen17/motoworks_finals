<?php
require_once __DIR__ . '/includes/auth.php';
$configFile = __DIR__ . '../includes/config.php';
if (file_exists($configFile)) {
    require_once $configFile;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Our Team - Bencalo MotoWorks</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <style>
        body { background-color: var(--color-bg, #121212); color: var(--color-text, #ffffff); font-family: 'Poppins', sans-serif; }
        .team-hero { background: linear-gradient(135deg, #181818 0%, #222222 100%); padding: 60px 20px; text-align: center; border-radius: 12px; margin-bottom: 40px; border: 1px solid var(--color-border, #2a2a2a); }
        .team-hero h1 { font-size: 2.5rem; font-weight: 800; color: var(--color-border); margin-bottom: 10px; }
        .team-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 30px; margin-bottom: 60px; }
        .team-card { background: var(--color-card-bg, #1e1e1e); border-radius: 12px; padding: 30px; text-align: center; border: 1px solid var(--color-border, #2a2a2a); transition: transform 0.3s ease; }
        .team-card:hover { transform: translateY(-5px); border-color: var(--color-primary, #00bcd4); }
        .team-avatar { width: 100px; height: 100px; border-radius: 50%; object-fit: cover; margin: 0 auto 20px auto; border: 3px solid var(--color-primary, #00bcd4); }
        .team-card h3 { font-size: 1.2rem; font-weight: 700; margin-bottom: 5px; color: #fff; }
        .team-role { color: var(--color-border); font-size: 0.9rem; font-weight: 600; margin-bottom: 15px; }
        .team-bio { color: var(--color-text-muted, #a0a0a0); font-size: 0.85rem; line-height: 1.6; }
    </style>
</head>
<body>
    <?php require_once 'components/header.php'; ?>
    <main class="container" style="padding-top: 40px;">
        <div class="team-hero">
            <h1>Meet Our Expert Team</h1>
            <p>The dedicated professionals driving passion, precision, and performance at Bencalo MotoWorks.</p>
        </div>
        <div class="team-grid">
            <div class="team-card">
                <img src="images/team/team_1.jpg" alt="Team Member" class="team-avatar">
                <h3>Carl Stephen E. Bencalo</h3>
                <div class="team-role">Lead Developer & Founder</div>
                <p class="team-bio">Specializing in system architecture, performance tuning, and robust web solutions.</p>
            </div>
            <div class="team-card">
                <img src="images/team/team_2.jpg" alt="Team Member" class="team-avatar">
                <h3>Stephen Carl E. Bencalo</h3>
                <div class="team-role">Head Mechanic & Service Director</div>
                <p class="team-bio">Expert in advanced automotive diagnostics, precision engine repair, and maintenance schedules.</p>
            </div>
        </div>
    </main>
    <?php require_once 'components/footer.php'; require_once '../components/modals.php'; ?>
</body>
</html>