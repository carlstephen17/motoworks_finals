<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}
if (!isStaff($_SESSION['user_id'])) {
    header('Location: ' . (isAdmin($_SESSION['user_id']) ? '../admin/admin_index.php' : '../index.php'));
    exit();
}

$staffId = (int) $_SESSION['user_id'];
$csrfToken = htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8');
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$validStatuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
if (!in_array($statusFilter, ['', ...$validStatuses], true)) {
    $statusFilter = '';
}

$customers = $pdo->query("
    SELECT userId, userFirstName, userLastName, userPhone
    FROM `user`
    WHERE userRole = 'customer'
    ORDER BY userFirstName, userLastName, userId
")->fetchAll(PDO::FETCH_ASSOC);
$promos = $pdo->query("
    SELECT promoSlug, promoTitle, promoItemOptions, promoActive
    FROM promo
    ORDER BY promoTitle
")->fetchAll(PDO::FETCH_ASSOC);
$activePromos = array_values(array_filter($promos, static fn(array $promo): bool => (int) $promo['promoActive'] === 1));

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(COALESCE(claimStatus, 'Pending') = 'Pending') AS pending,
           SUM(claimStatus = 'Confirmed') AS confirmed,
           SUM(claimStatus = 'Completed') AS completed,
           SUM(claimStatus = 'Cancelled') AS cancelled
    FROM promo_claim
")->fetch(PDO::FETCH_ASSOC) ?: [];

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = "(pc.claimFullName LIKE ? OR pc.claimPhone LIKE ? OR pc.claimBundleSlug LIKE ? OR pc.claimSelectedOption LIKE ? OR u.username LIKE ?)";
    $term = '%' . $search . '%';
    array_push($params, $term, $term, $term, $term, $term);
}
if ($statusFilter !== '') {
    if ($statusFilter === 'Pending') {
        $conditions[] = "(pc.claimStatus = 'Pending' OR pc.claimStatus IS NULL)";
    } else {
        $conditions[] = 'pc.claimStatus = ?';
        $params[] = $statusFilter;
    }
}
$sql = "
    SELECT pc.claimId, pc.userId, pc.claimFullName, pc.claimPhone, pc.claimBundleSlug,
           pc.claimSelectedOption, pc.claimAppointmentDate, pc.claimAppointmentTime,
           COALESCE(pc.claimStatus, 'Pending') AS claimStatus, pc.claimCreatedAt,
           pc.claimCreatedByStaffId, u.username
    FROM promo_claim pc
    LEFT JOIN `user` u ON u.userId = pc.userId
";
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY pc.claimId DESC';
$historyStmt = $pdo->prepare($sql);
$historyStmt->execute($params);
$history = $historyStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Promo History - MotoWorks Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/staff_layout.css">
    <link rel="stylesheet" href="css/staff_dashboard.css">
    <link rel="stylesheet" href="css/staff_tables.css">
    <link rel="stylesheet" href="css/staff_forms.css">
    <link rel="stylesheet" href="css/staff_promo_history.css">
</head>
<body>
<div class="admin-layout">
    <?php include __DIR__ . '/includes/staff_nav.php'; ?>
    <main class="admin-main">
        <?php
        $staffPageTitle = 'Promo History';
        include __DIR__ . '/includes/staff_header.php';
        ?>
        <div class="admin-content-body">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert-toast" role="status"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <div class="dashboard-grid promo-claim-stats">
                <?php foreach ([
                    'total' => ['All Claims', 'fa-tags', 'badge-secondary'],
                    'pending' => ['Pending', 'fa-clock', 'badge-warning'],
                    'confirmed' => ['Confirmed', 'fa-calendar-check', 'badge-info'],
                    'completed' => ['Completed', 'fa-circle-check', 'badge-success'],
                    'cancelled' => ['Cancelled', 'fa-ban', 'badge-danger'],
                ] as $key => [$label, $icon, $badgeClass]): ?>
                    <div class="stat-card">
                        <div class="stat-icon bg-orders"><i class="fas <?= $icon ?>"></i></div>
                        <div class="stat-info"><h3><?= (int) ($stats[$key] ?? 0) ?></h3><p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p></div>
                    </div>
                <?php endforeach; ?>
            </div>

            <details class="card-container promo-claim-create">
                <summary class="card-header-flex"><h3><i class="fas fa-plus"></i> Create Promo Claim</h3></summary>
                <div class="promo-claim-form-body">
                    <?php if (!$customers || !$activePromos): ?>
                        <p>A promo claim requires an existing customer and an active promo with at least one option.</p>
                    <?php else: ?>
                        <form method="POST" action="actions/promo_claim_create.php" class="promo-claim-form">
                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                            <div class="promo-claim-form-grid">
                                <label>Customer
                                    <select class="form-control" name="customer_id" required>
                                        <option value="">Select customer</option>
                                        <?php foreach ($customers as $customer): ?>
                                            <option value="<?= (int) $customer['userId'] ?>">
                                                <?= htmlspecialchars(trim($customer['userFirstName'] . ' ' . $customer['userLastName']) . ' — ' . ($customer['userPhone'] ?? 'No phone'), ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Promo
                                    <select class="form-control" name="promo_slug" required data-claim-promo>
                                        <option value="">Select promo</option>
                                        <?php foreach ($activePromos as $promo): ?>
                                            <option value="<?= htmlspecialchars($promo['promoSlug'], ENT_QUOTES, 'UTF-8') ?>" data-options="<?= htmlspecialchars((string) json_encode(array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promo['promoItemOptions']) ?: [])))), ENT_QUOTES, 'UTF-8') ?>">
                                                <?= htmlspecialchars($promo['promoTitle'], ENT_QUOTES, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>Selected option
                                    <select class="form-control" name="selected_option" required data-claim-option disabled>
                                        <option value="">Select a promo first</option>
                                    </select>
                                </label>
                                <label>Appointment date
                                    <input class="form-control" type="date" name="appointment_date" min="<?= date('Y-m-d') ?>" required>
                                </label>
                                <label>Appointment time
                                    <input class="form-control" type="time" name="appointment_time" required>
                                </label>
                            </div>
                            <button class="btn-primary" type="submit"><i class="fas fa-save"></i> Save Claim</button>
                        </form>
                    <?php endif; ?>
                </div>
            </details>

            <section class="card-container staff-promo-list">
                <form method="GET" class="staff-promo-filters">
                    <label>Search claims
                        <input class="form-control" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>" placeholder="Customer, phone, promo, option, username">
                    </label>
                    <label>Claim status
                        <select class="form-control" name="status">
                            <option value="">All claim statuses</option>
                            <?php foreach ($validStatuses as $status): ?>
                                <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <div class="staff-promo-filter-actions">
                        <button class="btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                        <a class="btn-sm btn-info" href="staff_promo_history.php">Clear</a>
                    </div>
                </form>
                <div class="table-responsive">
                    <table class="admin-table">
                        <thead><tr><th>ID</th><th>Customer</th><th>Promo / option</th><th>Appointment</th><th>Status</th><th>Actions</th></tr></thead>
                        <tbody>
                        <?php if (!$history): ?>
                            <tr><td class="text-center" colspan="6">No promo claims match these filters.</td></tr>
                        <?php endif; ?>
                        <?php foreach ($history as $claim): ?>
                            <?php
                            $claimStatus = (string) $claim['claimStatus'];
                            $badgeClass = match ($claimStatus) {
                                'Completed' => 'badge-success',
                                'Cancelled' => 'badge-danger',
                                'Confirmed' => 'badge-info',
                                default => 'badge-warning',
                            };
                            $canEdit = strtolower(trim($claimStatus)) !== 'completed';
                            $claimFormId = 'promo-claim-edit-' . (int) $claim['claimId'];
                            ?>
                            <tr>
                                <td><strong>#<?= (int) $claim['claimId'] ?></strong></td>
                                <td><?= htmlspecialchars($claim['claimFullName'] ?: ($claim['username'] ?? 'Guest'), ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($claim['claimPhone'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><?= htmlspecialchars($claim['claimBundleSlug'], ENT_QUOTES, 'UTF-8') ?><br><small><?= htmlspecialchars($claim['claimSelectedOption'], ENT_QUOTES, 'UTF-8') ?></small></td>
                                <td><?= htmlspecialchars($claim['claimAppointmentDate'] . ' ' . $claim['claimAppointmentTime'], ENT_QUOTES, 'UTF-8') ?></td>
                                <td><span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($claimStatus, ENT_QUOTES, 'UTF-8') ?></span></td>
                                <td>
                                    <div class="table-row-actions">
                                        <button class="btn-sm btn-secondary" type="button" data-open-claim-dialog="<?= $claimFormId ?>"><i class="fas fa-eye"></i> View</button>
                                        <?php if ($canEdit): ?>
                                            <button class="btn-sm btn-info" type="button" data-edit-claim-dialog="<?= $claimFormId ?>"><i class="fas fa-edit"></i> Edit</button>
                                        <?php endif; ?>
                                        <form method="POST" action="actions/promo_claim_delete.php" onsubmit="return confirm('Permanently delete promo claim #<?= (int) $claim['claimId'] ?>?');">
                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                            <input type="hidden" name="claim_id" value="<?= (int) $claim['claimId'] ?>">
                                            <button class="btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                        </form>
                                    </div>
                                    <dialog class="promo-claim-dialog" id="<?= $claimFormId ?>">
                                        <div class="promo-claim-dialog-header">
                                            <h3>Promo Claim #<?= (int) $claim['claimId'] ?></h3>
                                            <button class="btn-sm btn-danger" type="button" data-close-claim-dialog aria-label="Close"><i class="fas fa-times"></i></button>
                                        </div>
                                        <div class="promo-claim-detail-body">
                                            <p><strong>Customer:</strong> <?= htmlspecialchars($claim['claimFullName'], ENT_QUOTES, 'UTF-8') ?> · <?= htmlspecialchars($claim['claimPhone'], ENT_QUOTES, 'UTF-8') ?></p>
                                            <p><strong>Claimed:</strong> <?= htmlspecialchars($claim['claimCreatedAt'] ?? '', ENT_QUOTES, 'UTF-8') ?></p>
                                            <?php if ($canEdit): ?>
                                                <form id="<?= $claimFormId ?>-form" method="POST" action="actions/promo_claim_update.php" class="promo-claim-form">
                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['claimId'] ?>">
                                                    <div class="promo-claim-form-grid">
                                                        <label>Promo
                                                            <select class="form-control" name="promo_slug" required data-claim-promo>
                                                                <?php foreach ($promos as $promo): ?>
                                                                    <option value="<?= htmlspecialchars($promo['promoSlug'], ENT_QUOTES, 'UTF-8') ?>" data-options="<?= htmlspecialchars((string) json_encode(array_values(array_filter(array_map('trim', preg_split('/\R/u', (string) $promo['promoItemOptions']) ?: [])))), ENT_QUOTES, 'UTF-8') ?>" <?= $claim['claimBundleSlug'] === $promo['promoSlug'] ? 'selected' : '' ?>>
                                                                        <?= htmlspecialchars($promo['promoTitle'] . ((int) $promo['promoActive'] === 1 ? '' : ' (Inactive)'), ENT_QUOTES, 'UTF-8') ?>
                                                                    </option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Selected option
                                                            <select class="form-control" name="selected_option" required data-claim-option data-current-option="<?= htmlspecialchars($claim['claimSelectedOption'], ENT_QUOTES, 'UTF-8') ?>" data-current-promo="<?= htmlspecialchars($claim['claimBundleSlug'], ENT_QUOTES, 'UTF-8') ?>">
                                                                <option value="<?= htmlspecialchars($claim['claimSelectedOption'], ENT_QUOTES, 'UTF-8') ?>" selected><?= htmlspecialchars($claim['claimSelectedOption'], ENT_QUOTES, 'UTF-8') ?></option>
                                                            </select>
                                                        </label>
                                                        <label>Appointment date<input class="form-control" type="date" name="appointment_date" required value="<?= htmlspecialchars($claim['claimAppointmentDate'], ENT_QUOTES, 'UTF-8') ?>"></label>
                                                        <label>Appointment time<input class="form-control" type="time" name="appointment_time" required value="<?= htmlspecialchars(substr($claim['claimAppointmentTime'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"></label>
                                                        <label>Status
                                                            <select class="form-control" name="status" required>
                                                                <?php foreach ($validStatuses as $status): ?>
                                                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $claimStatus === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                    </div>
                                                    <button class="btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>
                                    </dialog>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>
<script src="js/staff.js" defer></script>
<script src="js/staff_promo_history.js" defer></script>
</body>
</html>
