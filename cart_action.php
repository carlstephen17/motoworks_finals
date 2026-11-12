<?php
require_once __DIR__ . '/includes/auth.php';
require_once 'includes/config.php';

header('Content-Type: application/json');

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Please log in to add items to your cart.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Invalid request method.']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Refresh the page and try again.']);
    exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'add') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid cart action.']);
    exit;
}

$product_id = isset($_POST['product_id']) ? (int)$_POST['product_id'] : 0;
$quantity = isset($_POST['quantity']) ? (int)$_POST['quantity'] : 1;

if ($product_id <= 0 || $quantity <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid product or quantity.']);
    exit;
}

try {
    $stmt = $pdo->prepare("SELECT productId as id, productName as name, productPrice as price, productStock as stock, productImage as image, productActive as active, productId, productName, productPrice, productStock, productImage, productActive FROM product WHERE productId = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product) {
        echo json_encode(['success' => false, 'message' => 'Product not found.']);
        exit;
    }

    $currentStock = (int)$product['stock'];
    
    // Check existing cart quantity for this item
    $existingQty = isset($_SESSION['cart'][$product_id]) ? (is_array($_SESSION['cart'][$product_id]) ? $_SESSION['cart'][$product_id]['quantity'] : $_SESSION['cart'][$product_id]) : 0;
    
    if (($existingQty + $quantity) > $currentStock) {
        echo json_encode(['success' => false, 'message' => 'Not enough stock available. Maximum: ' . $currentStock]);
        exit;
    }

    // Initialize or update cart session structure
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }

    if (isset($_SESSION['cart'][$product_id]) && is_array($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] += $quantity;
    } else {
        $_SESSION['cart'][$product_id] = [
            'quantity' => ($existingQty + $quantity)
        ];
    }

    // Calculate total cart count badge
    $cart_count = 0;
    foreach ($_SESSION['cart'] as $item) {
        $cart_count += is_array($item) ? ($item['quantity'] ?? 1) : 1;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Product added to cart successfully!',
        'cart_count' => $cart_count
    ]);

} catch (Throwable $e) {
    error_log('Cart update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update the cart right now.']);
}
?>