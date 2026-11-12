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
$statuses = ['Pending', 'Cancelled', 'Completed'];
$search = trim((string) ($_GET['search'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? '');
$dateFilter = (string) ($_GET['date'] ?? '');
$sourceFilter = (string) ($_GET['source'] ?? '');
$validDate = static function (string $value): bool {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date !== false && $date->format('Y-m-d') === $value;
};

if (!in_array($statusFilter, $statuses, true) && $statusFilter !== '') {
    $statusFilter = '';
}
if ($dateFilter !== '' && !$validDate($dateFilter)) {
    $dateFilter = '';
}
if (!in_array($sourceFilter, ['online', 'staff', 'admin'], true)) {
    $sourceFilter = '';
}

$stats = $pdo->query("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN bookingStatus IN ('Pending', 'Confirmed') THEN 1 ELSE 0 END) AS pending,
           SUM(CASE WHEN bookingStatus = 'Completed' THEN 1 ELSE 0 END) AS completed,
           SUM(CASE WHEN bookingStatus IN ('Cancelled', 'Rejected') THEN 1 ELSE 0 END) AS cancelled
    FROM service_booking
")->fetch(PDO::FETCH_ASSOC) ?: [];
$customers = $pdo->query("
    SELECT userId, userFirstName, userLastName, userEmail, userPhone
    FROM `user`
    WHERE userRole = 'customer'
    ORDER BY userFirstName, userLastName, userId
")->fetchAll(PDO::FETCH_ASSOC);
$services = $pdo->query("
    SELECT serviceId, serviceName
    FROM service
    WHERE serviceActive = 1
    ORDER BY serviceName
")->fetchAll(PDO::FETCH_ASSOC);
$motorcycles = $pdo->query("
    SELECT m.motorcycleId, m.userId, m.motorcycleModel, m.motorcyclePlateNumber
    FROM motorcycle m
    ORDER BY m.motorcycleModel, m.motorcyclePlateNumber
")->fetchAll(PDO::FETCH_ASSOC);

$conditions = [];
$params = [];
if ($search !== '') {
    $conditions[] = "(CAST(sb.bookingId AS CHAR) = ? OR CONCAT_WS(' ', u.userFirstName, u.userLastName) LIKE ? OR u.userEmail LIKE ? OR u.userPhone LIKE ? OR COALESCE(sb.bookingVehicleModel, m.motorcycleModel) LIKE ? OR m.motorcyclePlateNumber LIKE ? OR s.serviceName LIKE ?)";
    $params[] = $search;
    for ($i = 0; $i < 6; $i++) {
        $params[] = '%' . $search . '%';
    }
}
if ($statusFilter !== '') {
    if ($statusFilter === 'Pending') {
        $conditions[] = "sb.bookingStatus IN ('Pending', 'Confirmed')";
    } elseif ($statusFilter === 'Cancelled') {
        $conditions[] = "sb.bookingStatus IN ('Cancelled', 'Rejected')";
    } else {
        $conditions[] = 'sb.bookingStatus = ?';
        $params[] = $statusFilter;
    }
}
if ($dateFilter !== '') {
    $conditions[] = 'sb.bookingDate = ?';
    $params[] = $dateFilter;
}
if ($sourceFilter === 'online') {
    $conditions[] = "sb.bookingSource = 'customer_online'";
} elseif ($sourceFilter === 'staff') {
    $conditions[] = "sb.bookingSource = 'staff'";
} elseif ($sourceFilter === 'admin') {
    $conditions[] = "sb.bookingSource = 'admin'";
}

$sql = "
    SELECT sb.bookingId, sb.userId, sb.serviceId, sb.bookingDate, sb.bookingTime,
           CASE
               WHEN sb.bookingStatus = 'Confirmed' THEN 'Pending'
               WHEN sb.bookingStatus = 'Rejected' THEN 'Cancelled'
               ELSE sb.bookingStatus
           END AS bookingStatus,
           sb.bookingNotes, sb.bookingRejectionReason, sb.bookingCreatedAt,
           sb.bookingVehicleModel, sb.createdByStaffId, sb.bookingSource,
           CONCAT_WS(' ', u.userFirstName, u.userLastName) AS customerName,
           u.userEmail, u.userPhone,
           s.serviceName,
           COALESCE(sb.bookingVehicleModel, m.motorcycleModel) AS motorcycleModel,
           m.motorcyclePlateNumber,
           CONCAT_WS(' ', staff.userFirstName, staff.userLastName) AS staffCreatorName
    FROM service_booking sb
    INNER JOIN `user` u ON u.userId = sb.userId
    INNER JOIN service s ON s.serviceId = sb.serviceId
    LEFT JOIN motorcycle m ON m.motorcycleId = sb.bookingMotorcycleId
    LEFT JOIN `user` staff ON staff.userId = sb.createdByStaffId
";
if ($conditions) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' ORDER BY sb.bookingDate DESC, sb.bookingTime DESC, sb.bookingId DESC';
$appointmentsStmt = $pdo->prepare($sql);
$appointmentsStmt->execute($params);
$appointments = $appointmentsStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Appointments - MotoWorks Staff</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/staff_layout.css">
    <link rel="stylesheet" href="css/staff_dashboard.css">
    <link rel="stylesheet" href="css/staff_tables.css">
    <link rel="stylesheet" href="css/staff_forms.css">
    <link rel="stylesheet" href="css/staff_appointments.css">
</head>
<body>
    <div class="admin-layout">
        <?php include __DIR__ . '/includes/staff_nav.php'; ?>
        <main class="admin-main">
            <?php
            $staffPageTitle = 'Appointments';
            include __DIR__ . '/includes/staff_header.php';
            ?>
            <div class="admin-content-body">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert-toast" role="status"><?= htmlspecialchars((string) $_GET['msg'], ENT_QUOTES, 'UTF-8') ?></div>
                <?php endif; ?>

                <div class="dashboard-grid appointment-stats">
                    <?php foreach ([
                        'total' => ['All Appointments', 'fa-calendar-days'],
                        'pending' => ['Pending', 'fa-clock'],
                        'cancelled' => ['Cancelled', 'fa-calendar-xmark'],
                        'completed' => ['Completed', 'fa-circle-check'],
                    ] as $key => [$label, $icon]): ?>
                        <div class="stat-card">
                            <div class="stat-icon bg-orders"><i class="fas <?= $icon ?>"></i></div>
                            <div class="stat-info">
                                <h3><?= (int) ($stats[$key] ?? 0) ?></h3>
                                <p><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <section class="card-container appointment-create">
                    <div class="card-header-flex"><h3>+ Add Appointment</h3></div>
                    <div class="appointment-card-body">
                        <?php if (!$customers || !$services): ?>
                            <p>A walk-in appointment requires at least one existing customer and one active service.</p>
                        <?php else: ?>
                            <form method="POST" action="actions/appointment_create.php" class="appointment-form" data-motorcycle-form>
                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                <div class="appointment-grid">
                                    <div class="form-group">
                                        <label for="walkin-customer">Customer</label>
                                        <select class="form-control" id="walkin-customer" name="customer_id" required data-customer-select>
                                            <option value="">Select customer</option>
                                            <?php foreach ($customers as $customer): ?>
                                                <option value="<?= (int) $customer['userId'] ?>">
                                                    <?= htmlspecialchars($customer['userFirstName'] . ' ' . $customer['userLastName'] . ' — ' . $customer['userPhone'], ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="walkin-service">Requested service</label>
                                        <select class="form-control" id="walkin-service" name="service_id" required>
                                            <option value="">Select service</option>
                                            <?php foreach ($services as $service): ?>
                                                <option value="<?= (int) $service['serviceId'] ?>"><?= htmlspecialchars($service['serviceName'], ENT_QUOTES, 'UTF-8') ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="form-group">
                                        <label for="walkin-motorcycle-mode">Motorcycle</label>
                                        <select class="form-control" id="walkin-motorcycle-mode" name="motorcycle_mode" required data-motorcycle-mode>
                                            <option value="existing">Choose registered motorcycle</option>
                                            <option value="new">Register motorcycle for this customer</option>
                                        </select>
                                    </div>
                                    <div class="form-group" data-existing-motorcycle>
                                        <label for="walkin-motorcycle">Registered motorcycle</label>
                                        <select class="form-control" id="walkin-motorcycle" name="motorcycle_id" required data-motorcycle-select>
                                            <option value="">Select motorcycle</option>
                                            <?php foreach ($motorcycles as $motorcycle): ?>
                                                <option value="<?= (int) $motorcycle['motorcycleId'] ?>" data-customer="<?= (int) $motorcycle['userId'] ?>">
                                                    <?= htmlspecialchars($motorcycle['motorcycleModel'] . ($motorcycle['motorcyclePlateNumber'] ? ' — ' . $motorcycle['motorcyclePlateNumber'] : ''), ENT_QUOTES, 'UTF-8') ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <small class="form-help">The list is filtered to the selected customer.</small>
                                    </div>
                                    <div class="form-group" data-new-motorcycle hidden>
                                        <label for="walkin-motorcycle-model">Motorcycle model</label>
                                        <input class="form-control" id="walkin-motorcycle-model" name="motorcycle_model" maxlength="255" data-new-motorcycle-required>
                                    </div>
                                    <div class="form-group" data-new-motorcycle hidden>
                                        <label for="walkin-plate">Plate number <span class="optional-label">(optional)</span></label>
                                        <input class="form-control" id="walkin-plate" name="plate_number" maxlength="32" data-new-motorcycle-required>
                                    </div>
                                    <div class="form-group">
                                        <label for="walkin-date">Appointment date</label>
                                        <input class="form-control" id="walkin-date" type="date" name="booking_date" required>
                                    </div>
                                    <div class="form-group">
                                        <label for="walkin-time">Appointment time</label>
                                        <input class="form-control" id="walkin-time" type="time" name="booking_time" required>
                                    </div>
                                    <div class="form-group appointment-full-width">
                                        <label for="walkin-notes">Additional notes</label>
                                        <textarea class="form-control" id="walkin-notes" name="notes" rows="3" maxlength="2000"></textarea>
                                    </div>
                                </div>
                                <p class="form-help">New customer accounts are created by Admin. Walk-in bookings are saved in the shared appointment table and attributed to you.</p>
                                <button class="btn-primary" type="submit"><i class="fas fa-calendar-plus"></i> Save Walk-In Appointment</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="card-container">
                    <div class="card-header-flex"><h3>All Customer and Staff Appointments</h3></div>
                    <div class="appointment-card-body">
                        <form method="GET" class="appointment-filter">
                            <div class="form-group">
                                <label for="appointment-search">Search customer, appointment ID, contact, motorcycle, plate, or service</label>
                                <input class="form-control" id="appointment-search" type="search" name="search" value="<?= htmlspecialchars($search, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment-status-filter">Status</label>
                                <select class="form-control" id="appointment-status-filter" name="status">
                                    <option value="">All statuses</option>
                                    <?php foreach ($statuses as $status): ?>
                                        <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $statusFilter === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label for="appointment-date-filter">Date</label>
                                <input class="form-control" id="appointment-date-filter" type="date" name="date" value="<?= htmlspecialchars($dateFilter, ENT_QUOTES, 'UTF-8') ?>">
                            </div>
                            <div class="form-group">
                                <label for="appointment-source-filter">Booking source</label>
                                <select class="form-control" id="appointment-source-filter" name="source">
                                    <option value="">All sources</option>
                                    <option value="online" <?= $sourceFilter === 'online' ? 'selected' : '' ?>>Customer Online Booking</option>
                                    <option value="staff" <?= $sourceFilter === 'staff' ? 'selected' : '' ?>>Staff-Created Booking</option>
                                    <option value="admin" <?= $sourceFilter === 'admin' ? 'selected' : '' ?>>Admin-Created Booking</option>
                                </select>
                            </div>
                            <div class="filter-actions">
                                <button class="btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
                                <a class="btn-sm btn-info" href="staff_appointments.php">Clear</a>
                            </div>
                        </form>
                        <div class="table-responsive">
                            <table class="admin-table appointment-table">
                                <thead>
                                    <tr>
                                        <th>Appointment</th><th>Customer / Contact</th><th>Motorcycle</th><th>Service</th>
                                        <th>Schedule</th><th>Status</th><th>Booking Source</th><th>Created</th><th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$appointments): ?>
                                        <tr><td colspan="9" class="text-center">No appointments found for these filters.</td></tr>
                                    <?php else: ?>
                                        <?php foreach ($appointments as $appointment): ?>
                                            <?php
                                            $statusClass = match ($appointment['bookingStatus']) {
                                                'Completed' => 'badge-success',
                                                'Cancelled' => 'badge-danger',
                                                default => 'badge-secondary',
                                            };
                                            $manageId = 'appointment-manage-' . (int) $appointment['bookingId'];
                                            $canEditAppointment = strtolower(trim((string) $appointment['bookingStatus'])) !== 'completed';
                                            ?>
                                            <tr>
                                                <td><strong>#<?= (int) $appointment['bookingId'] ?></strong></td>
                                                <td>
                                                    <?= htmlspecialchars($appointment['customerName'], ENT_QUOTES, 'UTF-8') ?><br>
                                                    <small><?= htmlspecialchars($appointment['userEmail'], ENT_QUOTES, 'UTF-8') ?></small><br>
                                                    <small><?= htmlspecialchars($appointment['userPhone'] ?? 'No phone', ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td>
                                                    <?= htmlspecialchars($appointment['motorcycleModel'] ?? 'Not specified', ENT_QUOTES, 'UTF-8') ?><br>
                                                    <small><?= htmlspecialchars($appointment['motorcyclePlateNumber'] ?? 'No plate on file', ENT_QUOTES, 'UTF-8') ?></small>
                                                </td>
                                                <td><?= htmlspecialchars($appointment['serviceName'], ENT_QUOTES, 'UTF-8') ?></td>
                                                <td><?= htmlspecialchars($appointment['bookingDate'] . ' ' . substr($appointment['bookingTime'], 0, 5), ENT_QUOTES, 'UTF-8') ?></td>
                                                <td>
                                                    <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($appointment['bookingStatus'], ENT_QUOTES, 'UTF-8') ?></span>
                                                    <?php if ($appointment['bookingStatus'] === 'Cancelled' && $appointment['bookingRejectionReason']): ?>
                                                        <small class="rejection-reason"><?= htmlspecialchars($appointment['bookingRejectionReason'], ENT_QUOTES, 'UTF-8') ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($appointment['bookingSource'] === 'staff'): ?>
                                                        Staff-Created Booking
                                                        <?php if ($appointment['staffCreatorName']): ?><br><small><?= htmlspecialchars($appointment['staffCreatorName'], ENT_QUOTES, 'UTF-8') ?></small><?php endif; ?>
                                                    <?php elseif ($appointment['bookingSource'] === 'admin'): ?>
                                                        Admin-Created Booking
                                                    <?php else: ?>
                                                        Customer Online Booking
                                                    <?php endif; ?>
                                                </td>
                                                <td><?= htmlspecialchars($appointment['bookingCreatedAt'] ?? 'N/A', ENT_QUOTES, 'UTF-8') ?></td>
                                                <td class="appointment-manage-cell">
                                                    <div class="table-row-actions">
                                                        <button class="btn-sm btn-secondary" type="button" data-open-staff-dialog="<?= $manageId ?>-dialog"><i class="fas fa-eye"></i> View</button>
                                                        <?php if ($canEditAppointment): ?>
                                                            <button class="btn-sm btn-info" type="button" data-edit-staff-dialog="<?= $manageId ?>-dialog"><i class="fas fa-edit"></i> Edit</button>
                                                        <?php endif; ?>
                                                        <form method="POST" action="actions/appointment_delete.php" onsubmit="return confirm('Permanently delete appointment #<?= (int) $appointment['bookingId'] ?>?');">
                                                            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                            <input type="hidden" name="booking_id" value="<?= (int) $appointment['bookingId'] ?>">
                                                            <button class="btn-sm btn-danger" type="submit"><i class="fas fa-trash"></i> Delete</button>
                                                        </form>
                                                    </div>
                                                    <dialog class="staff-record-dialog" id="<?= $manageId ?>-dialog" aria-labelledby="<?= $manageId ?>-title">
                                                        <div class="staff-record-dialog-header">
                                                            <h3 id="<?= $manageId ?>-title">Appointment #<?= (int) $appointment['bookingId'] ?></h3>
                                                            <button class="btn-sm btn-danger" type="button" data-close-staff-dialog aria-label="Close"><i class="fas fa-times"></i></button>
                                                        </div>
                                                        <div class="staff-record-dialog-body">
                                                            <p><strong>Customer:</strong> <?= htmlspecialchars($appointment['customerName'], ENT_QUOTES, 'UTF-8') ?> (<?= htmlspecialchars($appointment['userEmail'], ENT_QUOTES, 'UTF-8') ?>)</p>
                                                            <p><strong>Notes:</strong> <?= nl2br(htmlspecialchars($appointment['bookingNotes'] ?? 'None', ENT_QUOTES, 'UTF-8')) ?></p>
                                                            <?php if ($canEditAppointment): ?>
                                                    <form id="<?= $manageId ?>" class="appointment-edit-form" method="POST" action="actions/appointment_update.php">
                                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                        <input type="hidden" name="booking_id" value="<?= (int) $appointment['bookingId'] ?>">
                                                        <label>Date <input class="form-control" type="date" name="booking_date" required value="<?= htmlspecialchars($appointment['bookingDate'], ENT_QUOTES, 'UTF-8') ?>"></label>
                                                        <label>Time <input class="form-control" type="time" name="booking_time" required value="<?= htmlspecialchars(substr($appointment['bookingTime'], 0, 5), ENT_QUOTES, 'UTF-8') ?>"></label>
                                                        <label>Service
                                                            <select class="form-control" name="service_id" required>
                                                                <?php foreach ($services as $service): ?>
                                                                    <option value="<?= (int) $service['serviceId'] ?>" <?= (int) $appointment['serviceId'] === (int) $service['serviceId'] ? 'selected' : '' ?>><?= htmlspecialchars($service['serviceName'], ENT_QUOTES, 'UTF-8') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Status
                                                            <select class="form-control" name="status" required>
                                                                <?php foreach ($statuses as $status): ?>
                                                                    <option value="<?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?>" <?= $appointment['bookingStatus'] === $status ? 'selected' : '' ?>><?= htmlspecialchars($status, ENT_QUOTES, 'UTF-8') ?></option>
                                                                <?php endforeach; ?>
                                                            </select>
                                                        </label>
                                                        <label>Cancellation reason
                                                            <textarea class="form-control" name="cancellation_reason" rows="2" maxlength="1000"><?= htmlspecialchars($appointment['bookingRejectionReason'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        </label>
                                                        <label>Appointment notes
                                                            <textarea class="form-control" name="notes" rows="2" maxlength="2000"><?= htmlspecialchars($appointment['bookingNotes'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                                                        </label>
                                                        <button class="btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
                                                    </form>
                                                            <?php endif; ?>
                                                        </div>
                                                    </dialog>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>
    <script src="js/staff.js" defer></script>
    <script src="js/staff_appointments.js" defer></script>
</body>
</html>
