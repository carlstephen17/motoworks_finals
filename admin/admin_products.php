<?php
// /admin/admin_products.php
require_once __DIR__ . '/../includes/auth.php';
require_once '../includes/config.php';
/** @var PDO $pdo */
require_once '../includes/auth.php';

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

// Fetch all products ordered by ID in ascending order
$stmt = $pdo->query("SELECT productId as id, productName as name, productPrice as price, productImage as image, productActive as active, productStock as stock, productId, productName, productPrice, productImage, productActive, productStock FROM product ORDER BY productId ASC");
$products = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Products Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
</head>

<body>

    <div class="admin-layout">
        <?php include 'includes/admin_sidebar.php'; ?>

        <!-- MAIN CONTENT AREA -->
        <main class="admin-main">
            <header class="main-header">
                <h2>Products Management</h2>
                <button class="btn-primary" onclick="openModal('addProductModal')">
                    <i class="fas fa-plus"></i> Add Product
                </button>
            </header>

            <div class="admin-content-body">
                <?php if (isset($_GET['msg'])): ?>
                    <div class="alert-toast"><?= htmlspecialchars($_GET['msg']) ?></div>
                <?php endif; ?>

                <!-- LIVE SEARCH & STATUS FILTER TOOLBAR -->
                <div class="table-filters" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px; gap: 15px; flex-wrap: wrap;">
                    <input type="text" id="liveSearch" class="form-control" placeholder="Search products by name..." style="flex: 1;">
                    <select id="statusFilter" class="form-control" style="width: 200px; flex-shrink: 0;">
                        <option value="">All Statuses</option>
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>

                <div class="table-responsive">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Image</th>
                                <th>Product Name</th>
                                <th>Price</th>
                                <th>Status</th>
                                <th>Stock</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($products)): ?>
                                <tr id="noProductsRow">
                                    <td colspan="7" class="text-center">No products found.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($products as $p): ?>
                                    <tr data-name="<?= htmlspecialchars(strtolower($p['name'])) ?>" data-status="<?= $p['active'] ?>">
                                        <td><?= $p['id'] ?></td>
                                        <td>
                                            <?php if (!empty($p['image'])): ?>
                                                <img src="../<?= htmlspecialchars($p['image']) ?>" alt="" style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php else: ?>
                                                <span class="text-muted">No Image</span>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= htmlspecialchars($p['name']) ?></td>
                                        <td>₱<?= number_format($p['price'] ?? 0, 2) ?></td>
                                        <td>
                                            <span class="badge <?= ($p['active'] == 1) ? 'badge-success' : 'badge-danger' ?>">
                                                <?= ($p['active'] == 1) ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="badge" style="background: #e2e8f0; color: #475569;">
                                                <?= intval($p['stock'] ?? 0) ?>
                                            </span>
                                        </td>
                                        <td class="table-row-actions">
                                            <button class="btn-sm btn-secondary" onclick="openViewModal(<?= htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)" title="View"><i class="fas fa-eye"></i> View</button>
                                            <button class="btn-sm btn-info" onclick="openEditModal(<?= htmlspecialchars(json_encode($p, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP), ENT_QUOTES, 'UTF-8') ?>)" title="Edit"><i class="fas fa-edit"></i> Edit</button>
                                            <form method="POST" action="actions/admin_product_delete.php" style="display:inline" onsubmit="return confirm('Delete this product? Order history is retained; products with orders are deactivated.');">
                                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                                <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                                                <button type="submit" class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i> Delete</button>
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

    <!-- VIEW PRODUCT MODAL -->
    <div class="modal-overlay" id="viewProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>View Product Details</h3>
                <button class="close-btn" onclick="closeModal('viewProductModal')">&times;</button>
            </div>
            <div style="text-align: center; margin-bottom: 15px;">
                <img id="view_image" src="" alt="Product Image" style="width: 100px; height: 100px; object-fit: cover; border-radius: 6px; display: none; margin: 0 auto;">
                <p id="view_no_image" class="text-muted" style="display: none; margin: 0;">No Image Available</p>
            </div>
            <div class="form-group">
                <label><strong>ID:</strong></label>
                <p id="view_id" class="form-control-static" style="margin: 0; color: #1e293b;"></p>
            </div>
            <div class="form-group">
                <label><strong>Product Name:</strong></label>
                <p id="view_name" class="form-control-static" style="margin: 0; color: #1e293b;"></p>
            </div>
            <div class="form-group">
                <label><strong>Price:</strong></label>
                <p id="view_price" class="form-control-static" style="margin: 0; color: #1e293b;"></p>
            </div>
            <div class="form-group">
                <label><strong>Active Status:</strong></label>
                <p id="view_active" class="form-control-static" style="margin: 0; color: #1e293b;"></p>
            </div>
            <div class="form-group">
                <label><strong>Stock:</strong></label>
                <p id="view_stock" class="form-control-static" style="margin: 0; color: #1e293b;"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('viewProductModal')">Close</button>
            </div>
        </div>
    </div>

    <!-- ADD PRODUCT MODAL -->
    <div class="modal-overlay" id="addProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Add Product</h3>
                <button class="close-btn" onclick="closeModal('addProductModal')">&times;</button>
            </div>
            <form action="actions/admin_product_create.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">

                <div class="form-group">
                    <label>Product Name:</label>
                    <input type="text" name="name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Price:</label>
                    <input type="number" step="0.01" name="price" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Stock Quantity:</label>
                    <input type="number" name="stock" value="0" min="0" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Image Path / File:</label>
                    <input type="file" name="image" class="form-control">
                </div>
                <div class="form-group">
                    <label>Active Status:</label>
                    <select name="active" class="form-control">
                        <option value="1" selected>Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('addProductModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>

    <!-- EDIT PRODUCT MODAL -->
    <div class="modal-overlay" id="editProductModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Edit Product</h3>
                <button class="close-btn" onclick="closeModal('editProductModal')">&times;</button>
            </div>
            <form action="actions/admin_product_update.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                <input type="hidden" name="id" id="edit_id">

                <div class="form-group">
                    <label>Product Name:</label>
                    <input type="text" name="name" id="edit_name" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Price:</label>
                    <input type="number" step="0.01" name="price" id="edit_price" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Stock Quantity:</label>
                    <input type="number" name="stock" id="edit_stock" min="0" required class="form-control">
                </div>
                <div class="form-group">
                    <label>Image (Leave blank to keep current):</label>
                    <input type="file" name="image" class="form-control">
                </div>
                <div class="form-group">
                    <label>Active Status:</label>
                    <select name="active" id="edit_active" class="form-control">
                        <option value="1">Active</option>
                        <option value="0">Inactive</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn-secondary" onclick="closeModal('editProductModal')">Cancel</button>
                    <button type="submit" class="btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Modal Control Helper Functions
        function openModal(modalId) {
            document.getElementById(modalId).style.display = 'flex';
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        function openViewModal(product) {
            document.getElementById('view_id').innerText = product.id;
            document.getElementById('view_name').innerText = product.name;
            document.getElementById('view_price').innerText = '₱' + parseFloat(product.price).toFixed(2);
            document.getElementById('view_active').innerText = product.active == 1 ? 'Active' : 'Inactive';
            document.getElementById('view_stock').innerText = product.stock ?? 0;

            const imgElement = document.getElementById('view_image');
            const noImgElement = document.getElementById('view_no_image');

            if (product.image && product.image.trim() !== '') {
                imgElement.src = '../' + product.image;
                imgElement.style.display = 'block';
                noImgElement.style.display = 'none';
            } else {
                imgElement.style.display = 'none';
                noImgElement.style.display = 'block';
            }

            openModal('viewProductModal');
        }

        function openEditModal(product) {
            document.getElementById('edit_id').value = product.id;
            document.getElementById('edit_name').value = product.name;
            document.getElementById('edit_price').value = product.price;
            document.getElementById('edit_active').value = product.active;
            document.getElementById('edit_stock').value = product.stock ?? 0;
            openModal('editProductModal');
        }

        // Live Search & Status Filter Logic
        const liveSearch = document.getElementById('liveSearch');
        const statusFilter = document.getElementById('statusFilter');
        const tableRows = document.querySelectorAll('.admin-table tbody tr[data-name]');

        function filterProducts() {
            const searchTerm = liveSearch.value.toLowerCase().trim();
            const statusValue = statusFilter.value;

            tableRows.forEach(row => {
                const name = row.getAttribute('data-name');
                const status = row.getAttribute('data-status');

                const matchesSearch = name.includes(searchTerm);
                const matchesStatus = (statusValue === "" || status === statusValue);

                if (matchesSearch && matchesStatus) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }

        liveSearch.addEventListener('keyup', filterProducts);
        statusFilter.addEventListener('change', filterProducts);
    </script>
</body>

</html>