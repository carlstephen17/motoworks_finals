<?php
// /admin/admin_promo_history.php
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/config.php';
/** @var PDO $pdo */
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Fetch all records for fast, responsive client-side live search and filtering
$query = "SELECT pc.claimId as id, pc.userId as users_id, pc.claimFullName as fullname, pc.claimPhone as phone, pc.claimBundleSlug as bundle_slug, pc.claimSelectedOption as selected_option, pc.claimAppointmentDate as appointment_date, pc.claimAppointmentTime as appointment_time, pc.claimStatus as status, pc.claimCreatedAt as created_at, u.username as user_username
          FROM promo_claim pc
          LEFT JOIN user u ON pc.userId = u.userId
          ORDER BY pc.claimId DESC";
$stmt = $pdo->prepare($query);
$stmt->execute();
$promoHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Promo Claim History</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
    <style>
        .badge-pending {
            background: #fff3e0;
            color: #f57c00;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
            font-weight: 600;
            display: inline-block;
        }
    </style>
</head>

<body>

    <div class="admin-layout">
        <?php include 'includes/admin_sidebar.php'; ?>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-main">
            <header class="main-header">
                <h2>Promo Claim History</h2>
            </header>

            <div class="admin-content-body">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert-toast" style="background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>

                <!-- Live Search & Status Filter Bar (No Filter/Reset buttons needed) -->
                <div style="background: #fff; padding: 15px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.1); display: flex; gap: 10px; flex-wrap: wrap; align-items: center;">
                    <input type="text" id="liveSearchInput" placeholder="Search customer, phone, bundle, or username..." class="form-control" style="flex: 1; min-width: 220px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                    <select id="statusFilterSelect" class="form-control" style="width: 180px; padding: 8px 12px; border: 1px solid #cbd5e1; border-radius: 4px; background: #fff;">
                        <option value="">All Statuses</option>
                        <option value="Pending">Pending</option>
                        <option value="Confirmed">Confirmed</option>
                        <option value="Used">Used</option>
                        <option value="Completed">Completed</option>
                        <option value="Cancelled">Cancelled</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Customer Name</th>
                                <th>Promo / Bundle</th>
                                <th>Appointment Date & Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($promoHistory)): ?>
                                <tr id="noRecordsRow">
                                    <td colspan="6" class="text-center" style="padding: 30px; color: #64748b;">No promo claims found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($promoHistory as $row): ?>
                                    <?php
                                    $status = $row['status'] ?? 'Pending';
                                    switch ($status) {
                                        case 'Confirmed':
                                            $badge_class = 'badge-info';
                                            break;
                                        case 'Used':
                                            $badge_class = 'badge-secondary';
                                            break;
                                        case 'Completed':
                                            $badge_class = 'badge-success';
                                            break;
                                        case 'Cancelled':
                                            $badge_class = 'badge-danger';
                                            break;
                                        case 'Pending':
                                            $badge_class = 'badge-pending';
                                            break;
                                        default:
                                            $badge_class = 'badge-warning';
                                            break;
                                    }
                                    ?>
                                    <tr class="history-row">
                                        <td><strong>#<?= $row['id'] ?></strong></td>
                                        <td>
                                            <?= htmlspecialchars($row['fullname'] ?? $row['user_username'] ?? 'Guest') ?>
                                            <br><small style="color: #64748b;"><?= htmlspecialchars($row['phone'] ?? 'N/A') ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['bundle_slug'] ?? 'N/A') ?></td>
                                        <td>
                                            <?= htmlspecialchars($row['appointment_date'] ?? 'N/A') ?> 
                                            <br><small style="color: #64748b;"><?= htmlspecialchars($row['appointment_time'] ?? '') ?></small>
                                        </td>
                                        <td>
                                            <span class="badge status-badge <?= $badge_class ?>">
                                                <?= htmlspecialchars($status) ?>
                                            </span>
                                        </td>
                                        <td class="table-row-actions">
                                            <button class="btn-sm btn-secondary" title="View Details" onclick="openViewModal(<?= htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-eye"></i> View</button>
                                            <?php if (strtoupper(trim((string) $status)) !== 'COMPLETED'): ?>
                                                <button class="btn-sm btn-primary" title="Edit Record" onclick="openEditModal(<?= htmlspecialchars(json_encode($row, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)"><i class="fas fa-edit"></i> Edit</button>
                                            <?php endif; ?>
                                            <form method="POST" action="actions/admin_promo_history_delete.php" style="display:inline" onsubmit="return confirm('Delete this promo claim record? This cannot be undone.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $row['id'] ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete Record"><i class="fas fa-trash"></i> Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

    <!-- VIEW MODAL -->
    <div class="modal-overlay" id="viewModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: #fff; padding: 30px; border-radius: 8px; width: 450px; max-width: 90%;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Promo Claim & Appointment Details</h3>
                <button type="button" onclick="closeModal('viewModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Full Name:</label>
                    <p id="view_fullname" style="margin: 0; font-size: 1rem; color: #333; font-weight: 500;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Phone:</label>
                    <p id="view_phone" style="margin: 0; font-size: 1rem; color: #333; font-weight: 500;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Promo / Bundle Slug:</label>
                    <p id="view_slug" style="margin: 0; font-size: 1rem; color: #333; font-weight: 500;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Selected Options / Details:</label>
                    <p id="view_options" style="margin: 0; font-size: 0.95rem; color: #333; font-weight: 400; background: #f8fafc; padding: 8px; border-radius: 4px;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Appointment Date & Time:</label>
                    <p id="view_appointment" style="margin: 0; font-size: 0.95rem; color: #333; font-weight: 600;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Status:</label>
                    <p id="view_status" style="margin: 0; font-size: 0.95rem; color: #333; font-weight: 600;"></p>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; margin-top: 25px;">
                <button type="button" class="btn-secondary" onclick="closeModal('viewModal')" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div class="modal-overlay" id="editModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; max-width: 90%;">
            <form action="actions/admin_promo_history_update.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                    <h3>Edit Promo Claim Status</h3>
                    <button type="button" onclick="closeModal('editModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
                </div>
                <div class="modal-body" style="display: flex; flex-direction: column; gap: 15px;">
                    <input type="hidden" name="id" id="edit_id">
                    <div>
                        <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Promo / Bundle Slug:</label>
                        <input type="text" name="bundle_slug" id="edit_slug" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;" required>
                    </div>
                    <div>
                        <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Status:</label>
                        <select name="status" id="edit_status" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px;">
                            <option value="Pending">Pending</option>
                            <option value="Confirmed">Confirmed</option>
                            <option value="Used">Used</option>
                            <option value="Completed">Completed</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 25px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('editModal')" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;">Save Changes</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openViewModal(row) {
            document.getElementById('view_fullname').textContent = row.fullname || row.user_username || 'Guest';
            document.getElementById('view_phone').textContent = row.phone || 'N/A';
            document.getElementById('view_slug').textContent = row.bundle_slug || 'N/A';
            document.getElementById('view_options').textContent = row.selected_option || 'None';
            document.getElementById('view_appointment').textContent = (row.appointment_date || 'No Date') + ' at ' + (row.appointment_time || '');
            document.getElementById('view_status').textContent = row.status || 'Pending';
            openModal('viewModal');
        }

        function openEditModal(row) {
            document.getElementById('edit_id').value = row.id;
            document.getElementById('edit_slug').value = row.bundle_slug || '';
            
            const statusSelect = document.getElementById('edit_status');
            const rowStatus = row.status || 'Pending';
            for (let option of statusSelect.options) {
                if (option.value === rowStatus) {
                    option.selected = true;
                    break;
                }
            }
            openModal('editModal');
        }

        // Live Search and Instant Status Filter Script
        document.addEventListener('DOMContentLoaded', function() {
            const searchInput = document.getElementById('liveSearchInput');
            const statusSelect = document.getElementById('statusFilterSelect');
            const historyRows = document.querySelectorAll('.history-row');

            function liveFilter() {
                const query = searchInput.value.toLowerCase().trim();
                const selectedStatus = statusSelect.value.toLowerCase();

                historyRows.forEach(row => {
                    const textContent = row.textContent.toLowerCase();
                    const statusBadge = row.querySelector('.status-badge');
                    const statusText = statusBadge ? statusBadge.textContent.trim().toLowerCase() : '';

                    const matchesSearch = query === '' || textContent.includes(query);
                    const matchesStatus = selectedStatus === '' || statusText === selectedStatus;

                    if (matchesSearch && matchesStatus) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            }

            if (searchInput) {
                searchInput.addEventListener('input', liveFilter);
            }
            if (statusSelect) {
                statusSelect.addEventListener('change', liveFilter);
            }
        });
    </script>
</body>

</html>