<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/config.php';
/** @var PDO $pdo */

if (!isset($_SESSION['user_id']) || !isAdmin($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit();
}

$promos = $pdo->query(
    'SELECT promoId AS id, promoSlug AS slug, promoTitle AS title, promoBadge AS badge,
            promoDescription AS description, promoItemLabel AS item_label,
            promoItemOptions AS item_options, promoPerks AS perks,
            promoProductImage AS product_image, promoServiceImage AS service_image,
            promoProductIcon AS product_icon, promoServiceIcon AS service_icon,
            promoActive AS active
     FROM promo
     ORDER BY promoId DESC'
)->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Promo Management</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/admin_layout.css">
    <link rel="stylesheet" href="css/admin_products.css">
</head>
<body>
<div class="admin-layout">
    <?php include 'includes/admin_sidebar.php'; ?>
    <main class="admin-main">
        <header class="main-header">
            <h2>Promo Management</h2>
            <button type="button" class="btn-primary" onclick="openAddModal()"><i class="fas fa-plus"></i> Add Promo</button>
        </header>
        <div class="admin-content-body">
            <?php if (isset($_GET['msg'])): ?>
                <div class="alert-toast"><?= htmlspecialchars($_GET['msg']) ?></div>
            <?php endif; ?>
            <div class="table-filters" style="display:flex; gap:15px; margin-bottom:15px; flex-wrap:wrap;">
                <input type="search" id="promoSearch" class="form-control" placeholder="Search promos..." style="flex:1; min-width:220px;">
                <select id="promoStatus" class="form-control" style="width:200px;">
                    <option value="">All statuses</option>
                    <option value="1">Active</option>
                    <option value="0">Inactive</option>
                </select>
            </div>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                    <tr><th>ID</th><th>Promo</th><th>Discount</th><th>Active / Inactive</th><th>Actions</th></tr>
                    </thead>
                    <tbody id="promoTableBody">
                    <?php if (!$promos): ?>
                        <tr class="empty-row"><td colspan="5" class="text-center">No promos found. Add a promo to display it to customers.</td></tr>
                    <?php else: ?>
                        <?php foreach ($promos as $promo): ?>
                            <tr class="promo-row" data-search="<?= htmlspecialchars(strtolower($promo['title'] . ' ' . $promo['slug'] . ' ' . $promo['description'])) ?>" data-status="<?= (int) $promo['active'] ?>">
                                <td><?= (int) $promo['id'] ?></td>
                                <td>
                                    <strong><?= htmlspecialchars($promo['title']) ?></strong>
                                    <br><small><?= htmlspecialchars($promo['slug']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($promo['badge']) ?></td>
                                <td>
                                    <span class="badge <?= $promo['active'] ? 'badge-success' : 'badge-danger' ?>"><?= $promo['active'] ? 'Active' : 'Inactive' ?></span>
                                    <form method="POST" action="actions/admin_promo_status_update.php" style="display:flex; align-items:center; gap:6px; margin-top:8px;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                                        <select name="active" aria-label="Set promo status" class="form-control" style="min-width:110px; padding:5px 8px;">
                                            <option value="1" <?= (int) $promo['active'] === 1 ? 'selected' : '' ?>>Active</option>
                                            <option value="0" <?= (int) $promo['active'] === 0 ? 'selected' : '' ?>>Inactive</option>
                                        </select>
                                        <button type="submit" class="btn-sm btn-info">Update</button>
                                    </form>
                                </td>
                                <td class="table-row-actions">
                                    <button type="button" class="btn-sm btn-secondary" title="View" onclick='openViewModal(<?= json_encode($promo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="fas fa-eye"></i> View</button>
                                    <button type="button" class="btn-sm btn-info" title="Edit" onclick='openEditModal(<?= json_encode($promo, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'><i class="fas fa-edit"></i> Edit</button>
                                    <form method="POST" action="actions/admin_promo_delete.php" style="display:inline" onsubmit="return confirm('Delete this promo from the customer promo catalog? Existing promo history will be retained.');">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input type="hidden" name="id" value="<?= (int) $promo['id'] ?>">
                                        <button type="submit" class="btn-sm btn-danger" title="Delete"><i class="fas fa-trash"></i> Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="noPromoMatch" style="display:none"><td colspan="5" class="text-center">No promos match your search.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </main>
</div>

<div class="modal-overlay" id="promoFormModal">
    <div class="modal-content" style="max-width:760px; max-height:90vh; overflow:auto;">
        <div class="modal-header">
            <h3 id="promoFormTitle">Add Promo</h3>
            <button type="button" class="close-btn" onclick="closeModal('promoFormModal')">&times;</button>
        </div>
        <form id="promoForm" action="actions/admin_promo_create.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="id" id="promo_id">
            <div class="form-group">
                <label>Promo title</label><input type="text" name="title" id="promo_title" maxlength="255" required class="form-control">
            </div>
            <div class="form-group">
                <label>Slug (used in promo claims/history)</label><input type="text" name="slug" id="promo_slug" maxlength="100" pattern="[A-Za-z0-9_-]+" required class="form-control">
            </div>
            <div class="form-group">
                <label>Discount badge</label><input type="text" name="badge" id="promo_badge" maxlength="50" placeholder="15% OFF" required class="form-control">
            </div>
            <div class="form-group">
                <label>Description</label><textarea name="description" id="promo_description" rows="3" required class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Option label</label><input type="text" name="item_label" id="promo_item_label" maxlength="255" required class="form-control">
            </div>
            <div class="form-group">
                <label>Selectable options (one per line)</label><textarea name="item_options" id="promo_item_options" rows="4" required class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Promo benefits (one per line)</label><textarea name="perks" id="promo_perks" rows="3" class="form-control"></textarea>
            </div>
            <div class="form-group">
                <label>Product image path</label><input type="text" name="product_image" id="promo_product_image" maxlength="255" class="form-control" placeholder="images/products/example.png">
            </div>
            <div class="form-group">
                <label>Service image path</label><input type="text" name="service_image" id="promo_service_image" maxlength="255" class="form-control" placeholder="images/services/example.png">
            </div>
            <div class="form-group">
                <label>Product icon class</label><input type="text" name="product_icon" id="promo_product_icon" maxlength="100" class="form-control" placeholder="fa-solid fa-circle-dot">
            </div>
            <div class="form-group">
                <label>Service icon class</label><input type="text" name="service_icon" id="promo_service_icon" maxlength="100" class="form-control" placeholder="fa-solid fa-screwdriver-wrench">
            </div>
            <div class="form-group">
                <label>Status</label>
                <select name="active" id="promo_active" class="form-control">
                    <option value="1">Active</option><option value="0">Inactive</option>
                </select>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-secondary" onclick="closeModal('promoFormModal')">Cancel</button>
                <button type="submit" class="btn-primary" id="promoSubmit">Save Promo</button>
            </div>
        </form>
    </div>
</div>

<div class="modal-overlay" id="promoViewModal">
    <div class="modal-content" style="max-width:600px; max-height:90vh; overflow:auto;">
        <div class="modal-header"><h3>Promo Details</h3><button type="button" class="close-btn" onclick="closeModal('promoViewModal')">&times;</button></div>
        <div class="form-group"><label>Title</label><p id="view_title"></p></div>
        <div class="form-group"><label>Slug</label><p id="view_slug"></p></div>
        <div class="form-group"><label>Discount badge</label><p id="view_badge"></p></div>
        <div class="form-group"><label>Description</label><p id="view_description"></p></div>
        <div class="form-group"><label>Option label</label><p id="view_item_label"></p></div>
        <div class="form-group"><label>Selectable options</label><p id="view_item_options" style="white-space:pre-line"></p></div>
        <div class="form-group"><label>Benefits</label><p id="view_perks" style="white-space:pre-line"></p></div>
        <div class="form-group"><label>Status</label><p id="view_active"></p></div>
        <div class="modal-footer"><button type="button" class="btn-secondary" onclick="closeModal('promoViewModal')">Close</button></div>
    </div>
</div>

<script>
    const promoFields = {
        id: 'promo_id', title: 'promo_title', slug: 'promo_slug', badge: 'promo_badge',
        description: 'promo_description', item_label: 'promo_item_label', item_options: 'promo_item_options',
        perks: 'promo_perks', product_image: 'promo_product_image', service_image: 'promo_service_image',
        product_icon: 'promo_product_icon', service_icon: 'promo_service_icon', active: 'promo_active'
    };

    function openModal(id) { document.getElementById(id).style.display = 'flex'; }
    function closeModal(id) { document.getElementById(id).style.display = 'none'; }

    function setPromoForm(promo) {
        Object.entries(promoFields).forEach(([key, fieldId]) => {
            document.getElementById(fieldId).value = promo[key] ?? '';
        });
    }

    function openAddModal() {
        document.getElementById('promoForm').action = 'actions/admin_promo_create.php';
        document.getElementById('promoFormTitle').textContent = 'Add Promo';
        document.getElementById('promoSubmit').textContent = 'Save Promo';
        setPromoForm({active: '1'});
        openModal('promoFormModal');
    }

    function openEditModal(promo) {
        document.getElementById('promoForm').action = 'actions/admin_promo_update.php';
        document.getElementById('promoFormTitle').textContent = 'Edit Promo';
        document.getElementById('promoSubmit').textContent = 'Update Promo';
        setPromoForm(promo);
        openModal('promoFormModal');
    }

    function openViewModal(promo) {
        const fields = ['title', 'slug', 'badge', 'description', 'item_label', 'item_options', 'perks'];
        fields.forEach(key => document.getElementById('view_' + key).textContent = promo[key] || '—');
        document.getElementById('view_active').textContent = Number(promo.active) === 1 ? 'Active' : 'Inactive';
        openModal('promoViewModal');
    }

    const searchInput = document.getElementById('promoSearch');
    const statusFilter = document.getElementById('promoStatus');
    function filterPromos() {
        const query = searchInput.value.trim().toLowerCase();
        let visibleCount = 0;
        document.querySelectorAll('.promo-row').forEach(row => {
            const matches = row.dataset.search.includes(query)
                && (statusFilter.value === '' || row.dataset.status === statusFilter.value);
            row.style.display = matches ? '' : 'none';
            if (matches) visibleCount++;
        });
        const noMatch = document.getElementById('noPromoMatch');
        if (noMatch) noMatch.style.display = visibleCount === 0 ? '' : 'none';
    }
    searchInput.addEventListener('input', filterPromos);
    statusFilter.addEventListener('change', filterPromos);
</script>
</body>
</html>
