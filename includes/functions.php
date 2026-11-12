<?php
/**
 * Fetch all available services mapped by their ID for quick lookup
 */
function getServicesMap(PDO $pdo): array {
    $services_map = [];
    try {
        $s_stmt = $pdo->query("SELECT serviceId as id, serviceName as name, servicePrice as price, serviceDescription as description, serviceImage as image, serviceStatus as status, serviceActive as active, serviceFeatured as featured, serviceId, serviceName, servicePrice, serviceDescription, serviceImage, serviceStatus, serviceActive, serviceFeatured FROM service");
        $services_list = $s_stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($services_list as $s) {
            $s_id = $s['service_id'] ?? $s['id'] ?? null;
            if ($s_id) {
                $services_map[$s_id] = [
                    'name'  => $s['service_name'] ?? $s['title'] ?? $s['name'] ?? ('Service #' . $s_id),
                    'price' => $s['price'] ?? $s['cost'] ?? $s['rate'] ?? 0
                ];
            }
        }
    } catch (Exception $e) {
        // Return empty map on error
    }
    return $services_map;
}

/**
 * Fetch all raw services list for populating form dropdowns
 */
function getAllServices(PDO $pdo): array {
    try {
        $s_stmt = $pdo->query("SELECT serviceId as id, serviceName as name, servicePrice as price, serviceDescription as description, serviceImage as image, serviceStatus as status, serviceActive as active, serviceFeatured as featured, serviceId, serviceName, servicePrice, serviceDescription, serviceImage, serviceStatus, serviceActive, serviceFeatured FROM service");
        return $s_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}

/**
 * Fetch all service appointments/bookings for a specific user, sorted oldest first
 */
function getUserAppointments(PDO $pdo, int|string $userId): array {
    try {
        $stmt = $pdo->prepare("
            SELECT sb.bookingId as id, sb.bookingId, sb.userId as user_id, sb.serviceId as service_id, sb.bookingVehicleModel as vehicle_model, sb.bookingDate as booking_date, sb.bookingTime as booking_time, sb.bookingBundleSlug as bundle_slug, sb.bookingBundleOption as bundle_option, sb.bookingStatus as status, sb.bookingNotes as notes, sb.bookingCreatedAt as created_at
            FROM service_booking sb
            WHERE sb.userId = ?
            ORDER BY sb.bookingDate ASC, sb.bookingTime ASC, sb.bookingId ASC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

 
/**
 * Fetch all promo claims for a specific user, sorted oldest first (starting from ID #1)
 */
function getUserPromoClaims(PDO $pdo, int|string $userId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT sb.bookingId as id, sb.bookingId, sb.userId as user_id, sb.serviceId as service_id, sb.bookingVehicleModel as vehicle_model, sb.bookingDate as booking_date, sb.bookingDate as claimed_date, sb.bookingTime as booking_time, sb.bookingBundleSlug as bundle_slug, sb.bookingBundleOption as bundle_option, sb.bookingStatus as status, sb.bookingNotes as notes, sb.bookingCreatedAt as created_at
            FROM service_booking sb
            WHERE sb.userId = ? AND sb.bookingBundleSlug IS NOT NULL
            ORDER BY sb.bookingDate DESC, sb.bookingId DESC
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Fetch all product orders for a specific user, sorted newest first
 */
function getUserOrders(PDO $pdo, int|string $userId): array
{
    try {
        $stmt = $pdo->prepare("
            SELECT o.orderId as id, co.orderGroupId, o.userId as user_id,
                   o.productId as product_id, o.orderQuantity as quantity,
                   o.orderTotalAmount as total_amount, co.orderPaymentMethod as payment_method,
                   co.orderPaymentReference as payment_reference,
                   co.orderPaymentStatus as payment_status, co.orderStatus as status,
                   co.orderCreatedAt as created_at, co.orderDeliveryAddress as delivery_address,
                   p.productName AS product_name, p.productImage AS product_image,
                   COALESCE(o.orderUnitPrice, p.productPrice) AS unit_price
            FROM `order` o
            INNER JOIN customer_order co ON co.orderGroupId = o.orderGroupId
            LEFT JOIN product p ON o.productId = p.productId
            WHERE o.userId = ?
            ORDER BY co.orderCreatedAt DESC, co.orderGroupId DESC, o.orderId
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        return [];
    }
}