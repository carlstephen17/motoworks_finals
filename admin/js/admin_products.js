/* /admin/js/admin_products.js */
function openModal(modalId) {
    document.getElementById(modalId).classList.add('active');
}

function closeModal(modalId) {
    document.getElementById(modalId).classList.remove('active');
}

function openEditModal(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_description').value = product.description || '';
    document.getElementById('edit_price').value = product.price;
    document.getElementById('edit_category').value = product.category || '';
    document.getElementById('edit_stock').value = product.stock;
    document.getElementById('edit_status').value = product.status;
    openModal('editProductModal');
}

function openDeleteModal(id) {
    document.getElementById('delete_id').value = id;
    openModal('deleteProductModal');
}

function openManageStockModal(item) {
    document.getElementById('stock_product_id').value = item.id;
    document.getElementById('stock_product_name').textContent = item.name;
    document.getElementById('stock_current_value').textContent = item.stock;
    openModal('manageStockModal');
}

function openEditServiceModal(service) {
    document.getElementById('edit_service_id').value = service.id;
    document.getElementById('edit_service_name').value = service.name;
    document.getElementById('edit_service_desc').value = service.description || '';
    document.getElementById('edit_service_price').value = service.price;
    openModal('editServiceModal');
}

function openDeleteServiceModal(id) {
    document.getElementById('delete_service_id').value = id;
    openModal('deleteServiceModal');
}