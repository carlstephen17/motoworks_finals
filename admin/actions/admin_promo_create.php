<?php
require_once '../../includes/auth.php';
require_once '../../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit();
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../admin_promos.php');
    exit();
}
requireValidCSRFToken();

$title = trim((string) ($_POST['title'] ?? ''));
$slug = trim((string) ($_POST['slug'] ?? ''));
$badge = trim((string) ($_POST['badge'] ?? ''));
$description = trim((string) ($_POST['description'] ?? ''));
$itemLabel = trim((string) ($_POST['item_label'] ?? ''));
$itemOptions = trim((string) ($_POST['item_options'] ?? ''));
$perks = trim((string) ($_POST['perks'] ?? ''));
$productImage = trim((string) ($_POST['product_image'] ?? ''));
$serviceImage = trim((string) ($_POST['service_image'] ?? ''));
$productIcon = trim((string) ($_POST['product_icon'] ?? ''));
$serviceIcon = trim((string) ($_POST['service_icon'] ?? ''));
$active = $_POST['active'] ?? null;

if (
    $title === '' || mb_strlen($title) > 255 ||
    !preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) ||
    $badge === '' || mb_strlen($badge) > 50 ||
    $description === '' || strlen($description) > 65535 ||
    $itemLabel === '' || mb_strlen($itemLabel) > 255 ||
    $itemOptions === '' || strlen($itemOptions) > 65535 ||
    strlen($perks) > 65535 ||
    mb_strlen($productImage) > 255 || mb_strlen($serviceImage) > 255 ||
    mb_strlen($productIcon) > 100 || mb_strlen($serviceIcon) > 100 ||
    !in_array($active, ['0', '1', 0, 1], true)
) {
    header('Location: ../admin_promos.php?msg=' . urlencode('Please provide valid promo details.'));
    exit();
}

try {
    $stmt = $pdo->prepare(
        'INSERT INTO promo
            (promoSlug, promoTitle, promoBadge, promoDescription, promoItemLabel,
             promoItemOptions, promoPerks, promoProductImage, promoServiceImage,
             promoProductIcon, promoServiceIcon, promoActive)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $slug, $title, $badge, $description, $itemLabel, $itemOptions, $perks,
        $productImage !== '' ? $productImage : null,
        $serviceImage !== '' ? $serviceImage : null,
        $productIcon !== '' ? $productIcon : null,
        $serviceIcon !== '' ? $serviceIcon : null,
        (int) $active,
    ]);
} catch (PDOException $error) {
    error_log('Promo creation failed: ' . $error->getMessage());
    $message = $error->getCode() === '23000'
        ? 'That promo slug is already in use.'
        : 'Unable to create promo. Please try again.';
    header('Location: ../admin_promos.php?msg=' . urlencode($message));
    exit();
}

header('Location: ../admin_promos.php?msg=' . urlencode('Promo successfully created.'));
exit();
