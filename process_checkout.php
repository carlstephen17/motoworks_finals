<?php

// =====================================================
// PROCESS CHECKOUT
// =====================================================

require_once __DIR__ . '/includes/auth.php';

require_once 'includes/config.php';

// =====================================================
// ONLY POST REQUESTS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: cart.php');
    exit;
}

// =====================================================
// CHECK LOGIN
// =====================================================

if (!isset($_SESSION['user_id'])) {

    $_SESSION['checkout_error'] =
        'Please log in before checking out.';

    header('Location: cart.php');
    exit;
}

// =====================================================
// CSRF VALIDATION
// =====================================================

$csrfToken =
    $_POST['csrf_token'] ?? '';

if (
    empty($_SESSION['csrf_token']) ||
    empty($csrfToken) ||
    !hash_equals(
        $_SESSION['csrf_token'],
        $csrfToken
    )
) {

    $_SESSION['checkout_error'] =
        'Invalid security token. Please try again.';

    header('Location: cart.php');
    exit;
}

// =====================================================
// USER ID
// =====================================================

$userId =
    (int)$_SESSION['user_id'];


// =====================================================
// CHECKOUT TYPE
// =====================================================

$checkoutType =
    $_POST['checkout_type'] ?? 'cart';

$isBuyNowRequest =
    $checkoutType === 'buy_now';

function checkoutErrorResponse(string $message, bool $isBuyNowRequest): void
{
    if ($isBuyNowRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $message
        ]);
        exit;
    }

    $_SESSION['checkout_error'] = $message;
    header('Location: cart.php');
    exit;
}


// =====================================================
// ADDRESS
// =====================================================

$address =
    trim($_POST['address'] ?? '');


// =====================================================
// PAYMENT METHOD
// =====================================================

$paymentMethod =
    trim(
        $_POST['payment_method']
        ?? 'Cash on Delivery'
    );

$isOnlinePayment = in_array($paymentMethod, ['GCash', 'Card'], true);
$paymentReference = ($isOnlinePayment ? 'MWP-' : 'MWO-') .
    date('Ymd') . '-' . strtoupper(bin2hex(random_bytes(4)));


// =====================================================
// VALIDATE ADDRESS
// =====================================================

if (empty($address)) {
    checkoutErrorResponse('Please enter your delivery address.', $isBuyNowRequest);
}


// =====================================================
// VALIDATE PAYMENT METHOD
// =====================================================

if (!in_array($paymentMethod, ['Cash on Delivery', 'GCash', 'Card'], true)) {
    checkoutErrorResponse('Invalid payment method.', $isBuyNowRequest);
}


// =====================================================
// GET REGISTERED USER INFORMATION
// =====================================================

try {

    $userStmt = $pdo->prepare("
        SELECT userId as id, userFirstName as first_name, userLastName as last_name, userPhone as phone, userEmail as email FROM `user` WHERE userId = ? LIMIT 1
    ");

    $userStmt->execute([
        $userId
    ]);

    $user =
        $userStmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    checkoutErrorResponse('Unable to load your account information.', $isBuyNowRequest);
}


// =====================================================
// USER NOT FOUND
// =====================================================

if (!$user) {
    checkoutErrorResponse('User account could not be found.', $isBuyNowRequest);
}


// =====================================================
// FULL NAME
// =====================================================

$fullName = trim(
    ($user['first_name'] ?? '') .
    ' ' .
    ($user['last_name'] ?? '')
);


// =====================================================
// PHONE
// =====================================================

$phone =
    trim((string) ($user['phone'] ?? ''));


// =====================================================
// VALIDATE USER INFORMATION
// =====================================================

if (empty($fullName)) {
    checkoutErrorResponse('Your registered name is incomplete.', $isBuyNowRequest);
}


if (!preg_match('/^09[0-9]{9}$/', $phone)) {
    checkoutErrorResponse('Phone number must be 11 digits and start with 09.', $isBuyNowRequest);
}


// =====================================================
// PREPARE ORDER ITEMS
// =====================================================

$orderItems = [];


// =====================================================
// CART CHECKOUT
// =====================================================

if ($checkoutType === 'cart') {

    if (
        empty($_SESSION['cart']) ||
        !is_array($_SESSION['cart'])
    ) {

        $_SESSION['checkout_error'] =
            'Your cart is empty.';

        header('Location: cart.php');
        exit;
    }


    foreach (
        $_SESSION['cart']
        as $productId => $item
    ) {

        $productId =
            (int)$productId;

        if ($productId <= 0) {
            continue;
        }


        if (is_array($item)) {

            $quantity =
                (int)($item['quantity'] ?? 1);

        } else {

            $quantity = 1;
        }


        if ($quantity < 1) {
            continue;
        }


        $orderItems[] = [

            'product_id' =>
                $productId,

            'quantity' =>
                $quantity
        ];
    }


    if (empty($orderItems)) {

        $_SESSION['checkout_error'] =
            'Your cart is empty.';

        header('Location: cart.php');
        exit;
    }

}


// =====================================================
// BUY NOW CHECKOUT
// =====================================================

elseif ($checkoutType === 'buy_now') {

    $productId =
        filter_input(
            INPUT_POST,
            'product_id',
            FILTER_VALIDATE_INT
        );


    $quantity =
        filter_input(
            INPUT_POST,
            'quantity',
            FILTER_VALIDATE_INT
        );


    if (
        !$productId ||
        !$quantity ||
        $quantity < 1
    ) {

        if ($isBuyNowRequest) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'message' => 'Invalid product or quantity.'
            ]);
            exit;
        }

        $_SESSION['checkout_error'] =
            'Invalid product or quantity.';

        header('Location: index.php');
        exit;
    }


    $orderItems[] = [

        'product_id' =>
            (int)$productId,

        'quantity' =>
            (int)$quantity
    ];

}


// =====================================================
// INVALID CHECKOUT TYPE
// =====================================================

else {

    $_SESSION['checkout_error'] =
        'Invalid checkout request.';

    header('Location: cart.php');
    exit;
}


// =====================================================
// PROCESS ORDER
// =====================================================

try {

    $columnCheck = $pdo->query("SHOW COLUMNS FROM `order` LIKE 'orderPaymentReference'");

    if (!$columnCheck->fetch(PDO::FETCH_ASSOC)) {
        $pdo->exec(
            "ALTER TABLE `order` ADD orderPaymentReference VARCHAR(32) NULL AFTER orderPaymentMethod"
        );
    }

    $pdo->beginTransaction();

    $addressStmt = $pdo->prepare('UPDATE `user` SET userAddress = ? WHERE userId = ?');
    $addressStmt->execute([$address, $userId]);

    $groupStmt = $pdo->prepare("
        INSERT INTO customer_order
            (userId, orderDeliveryAddress, orderPaymentMethod, orderPaymentReference,
             orderPaymentStatus, orderStatus, orderCreatedAt)
        VALUES (?, ?, ?, ?, ?, 'Pending', NOW())
    ");
    $groupStmt->execute([
        $userId,
        $address,
        $paymentMethod,
        $paymentReference,
        $paymentMethod === 'Cash on Delivery' ? 'Due on delivery' : 'Pending',
    ]);
    $orderGroupId = (int) $pdo->lastInsertId();

    $orderIds = [];

    $grandTotal = 0.00;
    $confirmationItems = [];


    // =================================================
    // PROCESS EACH PRODUCT
    // =================================================

    foreach (
        $orderItems as $orderItem
    ) {

        $productId =
            (int)$orderItem['product_id'];

        $quantity =
            (int)$orderItem['quantity'];


        // ---------------------------------------------
        // GET PRODUCT
        // ---------------------------------------------

        $productStmt =
            $pdo->prepare("
                SELECT productId as id, productName as name, productPrice as price, productStock as stock FROM product WHERE productId = ? FOR UPDATE
            ");

        $productStmt->execute([
            $productId
        ]);

        $product =
            $productStmt->fetch(
                PDO::FETCH_ASSOC
            );


        // ---------------------------------------------
        // PRODUCT NOT FOUND
        // ---------------------------------------------

        if (!$product) {

            throw new Exception(
                'Product not found.'
            );
        }

        $confirmationItems[] = [
            'name' => $product['name'],
            'quantity' => $quantity
        ];


        // ---------------------------------------------
        // STOCK
        // ---------------------------------------------

        $stock =
            (int)$product['stock'];


        if ($stock <= 0) {

            throw new Exception(
                $product['name'] .
                ' is out of stock.'
            );
        }


        if ($quantity > $stock) {

            throw new Exception(
                'Not enough stock for ' .
                $product['name'] .
                '. Only ' .
                $stock .
                ' available.'
            );
        }


        // ---------------------------------------------
        // PRICE
        // ---------------------------------------------

        $price =
            (float)$product['price'];


        $totalAmount =
            $price * $quantity;


        $grandTotal +=
            $totalAmount;


        // ---------------------------------------------
        // DEDUCT STOCK
        // ---------------------------------------------

        $stockStmt =
            $pdo->prepare("
                UPDATE product SET productStock = productStock - ? WHERE productId = ? AND productStock >= ?
            ");

        $stockStmt->execute([

            $quantity,

            $productId,

            $quantity

        ]);


        if (
            $stockStmt->rowCount() !== 1
        ) {

            throw new Exception(
                'Unable to update stock for ' .
                $product['name'] .
                '.'
            );
        }


        // ---------------------------------------------
        // INSERT ORDER
        // ---------------------------------------------

        $orderStmt =
            $pdo->prepare("
                INSERT INTO `order`
                    (userId, productId, orderQuantity, orderTotalAmount, orderPaymentMethod,
                     orderPaymentReference, orderStatus, orderCreatedAt, orderGroupId, orderUnitPrice)
                VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?)
            ");


        $orderStmt->execute([

            $userId,

            $productId,

            // quantity
            $quantity,

            // total_amount
            $totalAmount,

            // payment_method
            $paymentMethod,

            // payment_reference
            $paymentReference,

            $orderGroupId,
            $price,

        ]);


        $orderIds[] =
            $pdo->lastInsertId();
    }


    // =================================================
    // COMMIT
    // =================================================

    $pdo->commit();


    // =================================================
    // CLEAR CART
    // =================================================

    if ($checkoutType === 'cart') {

        $_SESSION['cart'] = [];
    }


    // =================================================
    // SUCCESS MESSAGE
    // =================================================

    $_SESSION['checkout_success'] =
        'Order placed successfully!';


    $_SESSION['checkout_order_ids'] =
        $orderIds;

    $_SESSION['checkout_payment_method'] = $paymentMethod;
    $_SESSION['checkout_payment_reference'] = $paymentReference;
    $_SESSION['checkout_confirmation'] = [
        'customer_name' => $fullName,
        'phone' => $phone,
        'address' => $address,
        'payment_method' => $paymentMethod,
        'reference' => $paymentReference,
        'items' => $confirmationItems
    ];

    if ($isBuyNowRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'message' => 'Order placed successfully!',
            'payment_method' => $paymentMethod,
            'payment_reference' => $paymentReference,
            'customer_name' => $fullName,
            'phone' => $phone,
            'address' => $address,
            'items' => $confirmationItems
        ]);
        exit;
    }


    // =================================================
    // REDIRECT
    // =================================================

    if ($checkoutType === 'cart') {

        header('Location: cart.php');

    } else {

        header('Location: index.php');
    }

    exit;


} catch (Exception $e) {

    // =================================================
    // ROLLBACK
    // =================================================

    if (
        $pdo->inTransaction()
    ) {

        $pdo->rollBack();
    }


    // =================================================
    // ERROR
    // =================================================

    $_SESSION['checkout_error'] =
        $e->getMessage();

    if ($isBuyNowRequest) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $e->getMessage()
        ]);
        exit;
    }


    // =================================================
    // REDIRECT
    // =================================================

    if ($checkoutType === 'cart') {

        header('Location: cart.php');

    } else {

        header('Location: index.php');
    }

    exit;
}
?>