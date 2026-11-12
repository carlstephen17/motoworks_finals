<?php
// /admin/admin_services.php
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/config.php';
/** @var PDO $pdo */
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Search and status filter parameters
$search = trim($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';

// Build query with search and status filters
$query = "SELECT serviceId as id, serviceName as name, serviceDescription as description, servicePrice as price, serviceImage as image, serviceActive as active, serviceFeatured as featured, CASE WHEN serviceActive = 1 THEN 'Active' ELSE 'Inactive' END AS status, serviceId, serviceName, serviceDescription, servicePrice, serviceImage, serviceActive, serviceFeatured FROM service WHERE 1=1";
$params = [];

if (!empty($search)) {
    $query .= " AND (serviceId LIKE ? OR serviceName LIKE ? OR serviceDescription LIKE ?)";
    $term = "%$search%";
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
}

if (!empty($status_filter) && $status_filter !== 'all') {
    $query .= " AND serviceActive = ?";
    $params[] = $status_filter === 'Active' ? 1 : 0;
}

$query .= " ORDER BY id ASC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$services = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle AJAX Request for Live Search and Status Filtering without page reload
if (isset($_GET['ajax'])) {
    if (empty($services)) {
        echo '<tr><td colspan="7" class="text-center" style="padding: 30px; color: #64748b;">No services found matching your criteria.</td></tr>';
    } else {
        foreach ($services as $s) {
            $statusVal = $s['status'] ?? 'Active';
            $badgeStyle = 'background: #e8f5e9; color: #2e7d32;';
            if ($statusVal === 'Inactive') {
                $badgeStyle = 'background: #ffebee; color: #c62828;';
            }

            echo '<tr>';
            echo '<td>' . $s['id'] . '</td>';
            echo '<td>';
            if (!empty($s['image'])) {
                echo '<img src="../' . htmlspecialchars($s['image']) . '" alt="Service" width="40" height="40" style="object-fit: cover; border-radius: 4px;">';
            } else {
                echo '<span style="color: #999; font-size: 0.8rem;">No Image</span>';
            }
            echo '</td>';
            echo '<td>' . htmlspecialchars($s['name'] ?? $s['service_name'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($s['description'] ?? '') . '</td>';
            echo '<td>₱' . number_format($s['price'] ?? 0, 2) . '</td>';
            echo '<td><span class="badge" style="padding: 4px 8px; border-radius: 4px; ' . $badgeStyle . '">' . htmlspecialchars($statusVal) . '</span></td>';
            echo '<td>';
            echo '<div class="table-row-actions">';
            echo '<button type="button" class="btn-sm btn-secondary btn-view-service" data-service=\'' . htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') . '\' title="View Service"><i class="fas fa-eye"></i> View</button>';
            echo '<button type="button" class="btn-sm btn-info btn-edit-service" data-service=\'' . htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') . '\' title="Edit Service"><i class="fas fa-edit"></i> Edit</button>';
            echo '<form method="POST" action="actions/admin_service_delete.php" style="display:inline" onsubmit="return confirm(\'Are you sure you want to delete this service?\');"><input type="hidden" name="csrf_token" value="' . htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') . '"><input type="hidden" name="id" value="' . (int) $s['id'] . '"><button type="submit" class="btn-sm btn-danger" title="Delete Service"><i class="fas fa-trash"></i> Delete</button></form>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
    }
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Services Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
</head>

<body>

    <div class="admin-layout">
        <?php include 'includes/admin_sidebar.php'; ?>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-main">
            <header class="main-header" style="display: flex; justify-content: space-between; align-items: center;">
                <h2>Services Management</h2>
                <button class="btn-primary" onclick="openModal('addServiceModal')">
                    <i class="fas fa-plus"></i> Add Service
                </button>
            </header>

            <div class="admin-content-body">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert-toast" style="background: #d4edda; color: #155724; padding: 10px 15px; border-radius: 4px; margin-bottom: 20px;"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>

                <!-- Search and Status Filter Bar -->
                <div style="background: #fff; padding: 16px; border-radius: 8px; margin-bottom: 20px; box-shadow: 0 1px 3px rgba(0,0,0,0.05); border: 1px solid #e2e8f0;">
                    <form method="GET" action="admin_services.php" id="filterForm" onsubmit="return false;" style="display: flex; gap: 12px; flex-wrap: wrap; align-items: center; justify-content: space-between;">
                        <input type="text" name="search" id="searchInput" placeholder="Search by ID, Service Name, or Description..." value="<?= htmlspecialchars($search) ?>" class="form-control" style="flex: 1; min-width: 260px; padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem;" autocomplete="off">
                        
                        <div>
                            <?php 
                            $statuses = [
                                '' => 'All Statuses', 
                                'Active' => 'Active', 
                                'Inactive' => 'Inactive'
                            ];
                            ?>
                            <select name="status" id="statusSelect" class="form-control" style="padding: 9px 12px; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.9rem; background: #fff; cursor: pointer;">
                                <?php foreach ($statuses as $key => $label): ?>
                                    <option value="<?= $key ?>" <?= ($status_filter === $key) ? 'selected' : '' ?>><?= $label ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Service Name</th>
                                <th>Description</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="servicesTableBody">
                            <?php if (empty($services)): ?>
                                <tr>
                                    <td colspan="7" class="text-center" style="padding: 30px; color: #64748b;">No services found matching your criteria.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($services as $s): ?>
                                    <?php
                                    $statusVal = $s['status'] ?? 'Active';
                                    $badgeStyle = 'background: #e8f5e9; color: #2e7d32;';
                                    if ($statusVal === 'Inactive') {
                                        $badgeStyle = 'background: #ffebee; color: #c62828;';
                                    }
                                    ?>
                                    <tr>
                                        <td><?= $s['id'] ?></td>
                                        <td>
                                            <?php if (!empty($s['image'])): ?>
                                                <img src="../<?= htmlspecialchars($s['image']) ?>" alt="Service" width="40" height="40" style="object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <span style="color: #999; font-size: 0.8rem;">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($s['name'] ?? $s['service_name'] ?? '') ?></td>
                                        <td><?= htmlspecialchars($s['description'] ?? '') ?></td>
                                        <td>₱<?= number_format($s['price'] ?? 0, 2) ?></td>
                                        <td>
                                            <span class="badge" style="padding: 4px 8px; border-radius: 4px; <?= $badgeStyle ?>">
                                                <?= htmlspecialchars($statusVal) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <div class="table-row-actions">
                                                <button type="button" class="btn-sm btn-secondary btn-view-service" data-service='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>' title="View Service"><i class="fas fa-eye"></i> View</button>
                                                <button type="button" class="btn-sm btn-info btn-edit-service" data-service='<?= htmlspecialchars(json_encode($s), ENT_QUOTES, 'UTF-8') ?>' title="Edit Service"><i class="fas fa-edit"></i> Edit</button>
                                                <form method="POST" action="actions/admin_service_delete.php" style="display:inline" onsubmit="return confirm('Are you sure you want to delete this service?');">
                                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                                                    <button type="submit" class="btn-sm btn-danger" title="Delete Service"><i class="fas fa-trash"></i> Delete</button>
                                                </form>
                                            </div>
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

    <!-- VIEW SERVICE MODAL -->
    <div class="modal-overlay" id="viewServiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; max-width: 90%;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Service Details</h3>
                <button type="button" onclick="closeModal('viewServiceModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <div class="modal-body" style="display: flex; flex-direction: column; gap: 15px;">
                <div id="view_service_image_wrapper" style="text-align: center; margin-bottom: 5px; display: none;">
                    <img id="view_service_image" src="" alt="Service Image" style="max-width: 100%; height: 140px; object-fit: contain; border-radius: 4px; border: 1px solid #ddd; padding: 5px;">
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Service Name:</label>
                    <p id="view_service_name" style="margin: 0; font-size: 1rem; color: #333; font-weight: 500;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Description:</label>
                    <p id="view_service_desc" style="margin: 0; font-size: 0.95rem; color: #333; white-space: pre-wrap; line-height: 1.4;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Price:</label>
                    <p id="view_service_price" style="margin: 0; font-size: 1.1rem; font-weight: 700; color: #1976d2;"></p>
                </div>
                <div>
                    <label style="display: block; font-weight: 600; color: #555; font-size: 0.85rem; margin-bottom: 3px;">Status:</label>
                    <p id="view_service_status" style="margin: 0; font-size: 1rem; font-weight: 500;"></p>
                </div>
            </div>
            <div class="modal-footer" style="display: flex; justify-content: flex-end; margin-top: 25px;">
                <button type="button" class="btn-secondary" onclick="closeModal('viewServiceModal')" style="padding: 8px 20px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Close</button>
            </div>
        </div>
    </div>

    <!-- ADD SERVICE MODAL -->
    <div class="modal-overlay" id="addServiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; max-width: 90%;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Add Service</h3>
                <button type="button" onclick="closeModal('addServiceModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <form action="actions/admin_service_create.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Service Name:</label>
                    <input type="text" name="name" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
                    <textarea name="description" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" rows="3"></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Price:</label>
                    <input type="number" step="0.01" name="price" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Status:</label>
                    <select name="status" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Service Image:</label>
                    <input type="file" name="image" accept="image/*" class="form-control" style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('addServiceModal')" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 15px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer;">Save Service</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT SERVICE MODAL -->
    <div class="modal-overlay" id="editServiceModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); justify-content: center; align-items: center; z-index: 1000;">
        <div class="modal-content" style="background: #fff; padding: 30px; border-radius: 8px; width: 400px; max-width: 90%;">
            <div class="modal-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                <h3>Edit Service</h3>
                <button type="button" onclick="closeModal('editServiceModal')" style="background: none; border: none; font-size: 1.5rem; cursor: pointer;">&times;</button>
            </div>
            <form action="actions/admin_service_update.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="id" id="edit_service_id">

                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Service Name:</label>
                    <input type="text" name="name" id="edit_service_name" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Description:</label>
                    <textarea name="description" id="edit_service_desc" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" rows="3"></textarea>
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Price:</label>
                    <input type="number" step="0.01" name="price" id="edit_service_price" required class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="form-group" style="margin-bottom: 15px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Status:</label>
                    <select name="status" id="edit_service_status" class="form-control" style="width: 100%; padding: 8px; border: 1px solid #ced4da; border-radius: 4px;" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: block; margin-bottom: 5px; font-weight: 500;">Service Image (Leave blank to keep current):</label>
                    <input type="file" name="image" accept="image/*" class="form-control" style="width: 100%; padding: 6px; border: 1px solid #ced4da; border-radius: 4px;">
                </div>
                <div class="modal-footer" style="display: flex; justify-content: flex-end; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeModal('editServiceModal')" style="padding: 8px 15px; background: #6c757d; color: white; border: none; border-radius: 4px; cursor: pointer;">Cancel</button>
                    <button type="submit" class="btn-primary" style="padding: 8px 15px; background: #1976d2; color: white; border: none; border-radius: 4px; cursor: pointer;">Update Service</button>
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

        function openViewServiceModal(service) {
            document.getElementById('view_service_name').textContent = service.name || service.service_name || '';
            document.getElementById('view_service_desc').textContent = service.description || 'No description provided.';
            document.getElementById('view_service_price').textContent = '₱' + parseFloat(service.price || 0).toLocaleString('en-US', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
            document.getElementById('view_service_status').textContent = service.status || 'Active';

            const imgWrapper = document.getElementById('view_service_image_wrapper');
            const imgElement = document.getElementById('view_service_image');
            if (service.image) {
                imgElement.src = '../' + service.image;
                imgWrapper.style.display = 'block';
            } else {
                imgWrapper.style.display = 'none';
            }

            openModal('viewServiceModal');
        }

        function openEditServiceModal(service) {
            document.getElementById('edit_service_id').value = service.id;
            document.getElementById('edit_service_name').value = service.name || service.service_name || '';
            document.getElementById('edit_service_desc').value = service.description || '';
            document.getElementById('edit_service_price').value = service.price || '';
            document.getElementById('edit_service_status').value = service.status || 'Active';
            openModal('editServiceModal');
        }

        document.addEventListener('DOMContentLoaded', () => {
            const searchInput = document.getElementById('searchInput');
            const statusSelect = document.getElementById('statusSelect');
            const tableBody = document.getElementById('servicesTableBody');
            let searchTimeout;

            function fetchFilteredServices() {
                const searchValue = searchInput.value;
                const statusValue = statusSelect.value;
                
                const params = new URLSearchParams({
                    search: searchValue,
                    status: statusValue,
                    ajax: '1'
                });

                const endpoint = window.location.pathname;

                fetch(endpoint + '?' + params.toString())
                    .then(response => response.text())
                    .then(html => {
                        tableBody.innerHTML = html;
                        const newUrl = endpoint + '?' + new URLSearchParams({
                            search: searchValue,
                            status: statusValue
                        }).toString();
                        window.history.replaceState({}, '', newUrl);
                    });
            }

            if (searchInput) {
                searchInput.addEventListener('input', function() {
                    clearTimeout(searchTimeout);
                    searchTimeout = setTimeout(fetchFilteredServices, 100);
                });

                searchInput.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') {
                        e.preventDefault();
                        clearTimeout(searchTimeout);
                        fetchFilteredServices();
                    }
                });
            }

            if (statusSelect) {
                statusSelect.addEventListener('change', fetchFilteredServices);
            }

            // Event delegation for dynamically updated view/edit buttons
            document.addEventListener('click', function(e) {
                const viewBtn = e.target.closest('.btn-view-service');
                if (viewBtn) {
                    try {
                        const service = JSON.parse(viewBtn.dataset.service);
                        openViewServiceModal(service);
                    } catch (err) {
                        console.error(err);
                    }
                }

                const editBtn = e.target.closest('.btn-edit-service');
                if (editBtn) {
                    try {
                        const service = JSON.parse(editBtn.dataset.service);
                        openEditServiceModal(service);
                    } catch (err) {
                        console.error(err);
                    }
                }
            });
        });
    </script>
</body>

</html>