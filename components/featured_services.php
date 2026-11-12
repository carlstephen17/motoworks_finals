<?php
// Ensure $services is available
$services = [];
if (isset($pdo)) {
    try {
        $stmt = $pdo->query("SELECT serviceId as id, serviceName as name, serviceDescription as description, servicePrice as price, serviceImage as image, serviceActive as active, serviceFeatured as featured, serviceId, serviceName, serviceDescription, servicePrice, serviceImage, serviceActive, serviceFeatured FROM service WHERE serviceActive = 1 LIMIT 4");
        $services = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        error_log('Unable to load featured services: ' . $e->getMessage());
    }
}

?>

<!-- ==================== FEATURED SERVICES SECTION ==================== -->
<section id="services" class="container section-padding-top">
    <div class="section-title">
        <h2>Featured Services</h2>
    </div>

    <?php if (!empty($services)): ?>
    <div class="card-grid service-grid" id="service-grid-container">
        <?php foreach ($services as $index => $s):
            $id = $s['service_id'] ?? $s['id'] ?? ($index + 1);
            $name = $s['service_name'] ?? $s['name'] ?? 'Service';
            $desc = $s['description'] ?? $s['service_description'] ?? $s['desc'] ?? '';
            $raw_icon = $s['image_url'] ?? $s['image'] ?? $s['icon'] ?? $s['icon_path'] ?? '';
            $image = ltrim(str_replace(['\\', '../'], ['/', ''], (string) $raw_icon), '/');
            if ($image !== '' && !is_file(__DIR__ . '/../' . $image)) {
                $image = '';
            }

            $serviceJson = htmlspecialchars(json_encode([
                'id' => $id,
                'name' => $name,
                'desc' => $desc,
                'icon' => $image
            ]), ENT_QUOTES, 'UTF-8');
        ?>
            <div class="product-card service-card" style="background: rgba(255, 255, 255, 0.03); border: 1px solid rgba(255, 255, 255, 0.08); border-radius: 8px; padding: 25px; display: flex; flex-direction: column; justify-content: space-between;">
                <div class="product-image" style="margin-bottom: 15px; text-align: center; height: 140px; display: flex; align-items: center; justify-content: center;">
                    <?php if ($image !== ''): ?>
                        <img src="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($name, ENT_QUOTES, 'UTF-8') ?>" style="max-height: 100%; max-width: 100%; object-fit: contain;">
                    <?php else: ?>
                        <i class="fa-solid fa-screwdriver-wrench" aria-hidden="true" style="font-size: 3rem; color: var(--color-text-muted, #a0a0a0);"></i>
                    <?php endif; ?>
                </div>
                <h3 class="product-name" style="font-size: 18px; font-weight: 600; color: var(--text-main, #ffffff); margin-bottom: 10px;"><?= htmlspecialchars($name) ?></h3>
                <?php if (!empty($desc)): ?>
                    <p class="product-desc" style="font-size: 14px; color: var(--text-muted, #a0a0a0); line-height: 1.5; margin-bottom: 20px;"><?= htmlspecialchars($desc) ?></p>
                <?php endif; ?>
                <div class="product-card-actions" style="display: flex; gap: 10px;">
                    <button type="button" onclick='openServiceModal(<?= $serviceJson ?>)' class="btn btn-outline btn-block" style="flex: 1; padding: 10px; cursor: pointer;">View Details</button>
                    <button type="button" onclick="handleServiceBooking(<?= $id ?>, '<?= addslashes(htmlspecialchars($name, ENT_QUOTES)) ?>')" class="btn btn-primary btn-block requires-auth" style="flex: 1; padding: 10px; font-weight: 500; cursor: pointer;">Book Now</button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
        <p class="text-center">No services are currently available.</p>
    <?php endif; ?>
</section>

<!-- Service Details Modal Structure -->
<div id="serviceDetailModal" class="modal-overlay" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.7); justify-content:center; align-items:center; z-index:1000;">
    <div class="modal-content" style="background:#1e2124; padding:30px; border-radius:8px; width:450px; color:#fff; position:relative; text-align:center;">
        <div id="modalServiceImageWrapper" style="margin-bottom:15px; height:120px; display:flex; align-items:center; justify-content:center;">
            <img id="modalServiceImg" src="" alt="" style="max-height:100%; max-width:100%; object-fit:contain;">
        </div>
        <h3 id="modalServiceName" style="color:#00FFFF; margin-bottom:10px;"></h3>
        <p id="modalServiceDesc" style="color:#ccc; font-size:0.9rem; margin-bottom:20px;"></p>
        <div style="display:flex; justify-content:flex-end; gap:10px;">
            <button type="button" onclick="closeServiceDetailModal()" style="padding:10px 20px; background:#333; border:none; color:#fff; border-radius:4px; cursor:pointer;">Close</button>
        </div>
    </div>
</div>

<script>
    function openServiceModal(service) {
        document.getElementById('modalServiceName').textContent = service.name;
        document.getElementById('modalServiceDesc').textContent = service.desc || 'No description available for this service.';
        document.getElementById('modalServiceImg').src = service.icon;
        document.getElementById('serviceDetailModal').style.display = 'flex';
    }

    function closeServiceDetailModal() {
        document.getElementById('serviceDetailModal').style.display = 'none';
    }

    function handleServiceBooking(serviceId, serviceName) {
        alert("Successfully selected booking for: " + serviceName);
        if (typeof openServiceBookingModal === 'function') {
            openServiceBookingModal(serviceId, serviceName);
        } else {
            window.location.href = 'services.php?service_id=' + encodeURIComponent(serviceId);
        }
    }

    // Close modal on outside click
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('serviceDetailModal');
        if (event.target === modal) {
            closeServiceDetailModal();
        }
    });
</script>