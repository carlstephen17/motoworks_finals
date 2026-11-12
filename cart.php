<?php
// =====================================================
// CART PAGE
// =====================================================

require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/config.php';

// =====================================================
// CSRF TOKEN
// =====================================================

generateCSRFToken();

// =====================================================
// CHECKOUT SUCCESS / ERROR MESSAGE
// =====================================================

$checkoutSuccess = $_SESSION['checkout_success'] ?? '';
$checkoutError = $_SESSION['checkout_error'] ?? '';
$checkoutPaymentMethod = $_SESSION['checkout_payment_method'] ?? '';
$checkoutPaymentReference = $_SESSION['checkout_payment_reference'] ?? '';
$checkoutConfirmation = $_SESSION['checkout_confirmation'] ?? [];

unset($_SESSION['checkout_success']);
unset($_SESSION['checkout_error']);
unset($_SESSION['checkout_payment_method']);
unset($_SESSION['checkout_payment_reference']);
unset($_SESSION['checkout_confirmation']);

// =====================================================
// CHECKOUT USER INFORMATION
// =====================================================

$checkoutFullName = '';
$checkoutPhone = '';

if (isset($_SESSION['user_id']) && isset($pdo)) {

    try {

        $stmt = $pdo->prepare("
            SELECT userFirstName as first_name, userLastName as last_name, userPhone as phone FROM `user` WHERE userId = ? LIMIT 1
        ");

        $stmt->execute([
            $_SESSION['user_id']
        ]);

        $checkoutUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($checkoutUser) {

            $checkoutFullName = trim(
                ($checkoutUser['first_name'] ?? '') .
                ' ' .
                ($checkoutUser['last_name'] ?? '')
            );

            $checkoutPhone = $checkoutUser['phone'] ?? '';
        }

    } catch (Exception $e) {

        $checkoutFullName = '';
        $checkoutPhone = '';
    }
}

// =====================================================
// HANDLE CART ACTIONS
// =====================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }

    $action = $_POST['action'];
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

    if (
        $id &&
        $action === 'remove' &&
        isset($_SESSION['cart'][$id])
    ) {
        unset($_SESSION['cart'][$id]);
    } elseif (
        $id &&
        $action === 'update' &&
        isset($_SESSION['cart'][$id])
    ) {
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        $productStmt = $pdo->prepare('SELECT productStock, productActive FROM product WHERE productId = ?');
        $productStmt->execute([$id]);
        $product = $productStmt->fetch(PDO::FETCH_ASSOC);

        if (!$product || (int) $product['productActive'] !== 1) {
            unset($_SESSION['cart'][$id]);
            $_SESSION['cart_error'] = 'This product is no longer available and was removed from your cart.';
        } elseif ($qty === false || $qty === null || $qty < 1 || $qty > (int) $product['productStock']) {
            $_SESSION['cart_error'] = 'The requested quantity is invalid or exceeds available stock.';
        } else {
            $_SESSION['cart'][$id] = ['quantity' => $qty];
        }
    }

    header('Location: cart.php');
    exit;
}

$cartError = $_SESSION['cart_error'] ?? '';
unset($_SESSION['cart_error']);

// =====================================================
// LOAD CART ITEMS
// =====================================================

$cart_items = [];
$subtotal = 0.00;

if (
    !empty($_SESSION['cart']) &&
    is_array($_SESSION['cart']) &&
    isset($pdo)
) {

    foreach (
        $_SESSION['cart']
        as $product_id => $item
    ) {

        $qty = is_array($item)
            ? (int)($item['quantity'] ?? 1)
            : 1;

        if ($qty < 1) {
            $qty = 1;
        }

        $stmt = $pdo->prepare("
            SELECT productId as id, productName as name, productPrice as price, productStock as stock, productImage as image, productActive as active, productId, productName, productPrice, productStock, productImage, productActive FROM product WHERE productId = ? LIMIT 1
        ");

        $stmt->execute([
            $product_id
        ]);

        $product =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if ($product) {

            $price =
                (float)$product['price'];

            $stock =
                (int)$product['stock'];

            $total_item_price =
                $price * $qty;

            $subtotal +=
                $total_item_price;

            $cart_items[] = [

                'id' =>
                    (int)$product['id'],

                'name' =>
                    $product['name'],

                'price' =>
                    $price,

                'image' =>
                    $product['image'],

                'quantity' =>
                    $qty,

                'stock' =>
                    $stock,

                'total' =>
                    $total_item_price
            ];
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Your Shopping Cart - Bencalo MotoWorks
    </title>

    <!-- GOOGLE FONT -->

    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <!-- FONT AWESOME -->

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <!-- STYLES -->

    <link
        rel="stylesheet"
        href="styles/index/style.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/layout.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/components.css"
    >

    <link
        rel="stylesheet"
        href="styles/shop.css"
    >

    <style>

        /* ================================
           CART PAGE
        ================================= */

        .cart-container {

            max-width: 1100px;

            margin: 40px auto 70px;

            padding: 0 20px;
        }


        /* ================================
           PAGE TITLE
        ================================= */

        .cart-container > h2 {

            color: #ffffff;

            font-size: 2rem;

            font-weight: 700;

            margin-bottom: 20px;
        }


        /* ================================
           CART TABLE
        ================================= */

        .cart-table-wrapper {

            width: 100%;

            overflow-x: auto;

            border-radius: 10px;

            background: #ffffff;
        }


        .cart-table {

            width: 100%;

            min-width: 760px;

            border-collapse: collapse;

            background: #ffffff;

            border-radius: 10px;

            overflow: hidden;
        }


        .cart-table th {

            background: #f5f6f8;

            color: #172033 !important;

            font-size: 0.95rem;

            font-weight: 700;

            padding: 18px 16px;

            text-align: left;

            border-bottom:
                1px solid #e5e7eb;

            white-space: nowrap;
        }


        .cart-table td {

            color: #333333 !important;

            background: #ffffff;

            padding: 18px 16px;

            border-bottom:
                1px solid #eeeeee;

            vertical-align: middle;
        }


        .cart-table tbody tr:last-child td {

            border-bottom: none;
        }


        .cart-table tbody tr:hover {

            background: #fafafa;
        }


        /* ================================
           PRODUCT
        ================================= */

        .cart-product {

            display: flex;

            align-items: center;

            gap: 16px;

            min-width: 300px;
        }


        .cart-item-img {

            width: 70px;

            height: 70px;

            object-fit: contain;

            border-radius: 8px;

            background: #ffffff;

            flex-shrink: 0;
        }


        .cart-product-name {

            color: #252525 !important;

            font-size: 0.95rem;

            font-weight: 600;

            line-height: 1.4;
        }


        /* ================================
           PRICE
        ================================= */

        .cart-price {

            color: #333333 !important;

            font-weight: 600;

            white-space: nowrap;
        }


        .cart-subtotal {

            color: #333333 !important;

            font-weight: 700;

            white-space: nowrap;
        }


        /* ================================
           QUANTITY
        ================================= */

        .qty-input {

            width: 62px;

            height: 38px;

            padding: 6px 8px;

            text-align: center;

            color: #222222 !important;

            background: #ffffff !important;

            border: 1px solid #d1d5db;

            border-radius: 6px;

            font-family: 'Poppins', sans-serif;

            font-size: 0.9rem;

            font-weight: 500;

            outline: none;
        }


        .qty-input:focus {

            border-color: #0b2d5c;

            box-shadow:
                0 0 0 2px
                rgba(11, 45, 92, 0.1);
        }


        /* ================================
           REMOVE BUTTON
        ================================= */

        .btn-remove {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            width: 38px;

            height: 38px;

            color: #dc3545 !important;

            background: transparent;

            border: none;

            border-radius: 6px;

            cursor: pointer;

            text-decoration: none;

            transition: all 0.2s ease;
        }


        .btn-remove:hover {

            background: #fff0f1;

            color: #b02a37 !important;
        }


        .btn-remove i {

            font-size: 1rem;
        }


        /* ================================
           CART SUMMARY
        ================================= */

        .cart-actions-row {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-top: 20px;

            padding: 24px 28px;

            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.05);
        }


        .cart-continue {

            color: #0b2d5c !important;

            font-weight: 600;

            text-decoration: none;
        }


        .cart-continue:hover {

            text-decoration: underline;
        }


        .cart-summary {

            text-align: right;
        }


        .cart-summary-label {

            color: #555555 !important;

            font-size: 1rem;

            font-weight: 500;

            margin-right: 5px;
        }


        .cart-summary-price {

            color: #e54848 !important;

            font-size: 1.25rem;

            font-weight: 700;
        }


        /* ================================
           CHECKOUT BUTTON
        ================================= */

        .checkout-btn {

            display: inline-flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            min-width: 220px;

            padding: 13px 24px;

            margin-top: 8px;

            background: #0b2d5c !important;

            color: #ffffff !important;

            border: none;

            border-radius: 30px;

            cursor: pointer;

            font-family: 'Poppins', sans-serif;

            font-size: 0.95rem;

            font-weight: 700;

            transition: all 0.2s ease;
        }


        .checkout-btn:hover {

            background: #123d78 !important;

            transform: translateY(-1px);
        }


        /* ================================
           EMPTY CART
        ================================= */

        .empty-cart {

            text-align: center;

            padding: 70px 20px;

            background: #ffffff;

            border-radius: 10px;

            box-shadow:
                0 2px 8px
                rgba(0, 0, 0, 0.05);
        }


        .empty-cart i {

            color: #c7cbd1 !important;

            font-size: 3.5rem;

            margin-bottom: 20px;
        }


        .empty-cart p {

            color: #555555 !important;

            font-size: 1rem;

            margin-bottom: 25px;
        }


        .empty-cart .btn {

            color: #ffffff !important;
        }


        /* ================================
           CHECKOUT MODAL
        ================================= */

        #checkout-modal {

            display: none;

            position: fixed;

            inset: 0;

            z-index: 9999;

            align-items: center;

            justify-content: center;

            padding: 20px;

            background:
                rgba(0, 0, 0, 0.65);
        }


        #checkout-modal.active {

            display: flex;
        }

        .payment-receipt-modal {
            display: none;
            position: fixed;
            inset: 0;
            z-index: 10000;
            align-items: center;
            justify-content: center;
            padding: 20px;
            background: rgba(0, 0, 0, 0.72);
        }

        .payment-receipt-modal.active {
            display: flex;
        }

        .payment-receipt-card {
            position: relative;
            width: 100%;
            max-width: 440px;
            padding: 32px 28px;
            border: 1px solid #2dd4bf;
            border-radius: 10px;
            background: #102a2d;
            color: #f8fafc;
            text-align: center;
            box-shadow: 0 18px 50px rgba(0, 0, 0, 0.45);
        }

        .payment-receipt-close {
            position: absolute;
            top: 10px;
            right: 14px;
            border: 0;
            background: transparent;
            color: #cbd5e1;
            font-size: 1.7rem;
            cursor: pointer;
        }

        .payment-receipt-icon {
            color: #2dd4bf;
            font-size: 3rem;
        }

        .payment-receipt-card h2 {
            margin: 10px 0 8px;
        }

        .payment-receipt-card p {
            color: #cbd5e1;
        }

        .receipt-detail-list {
            display: grid;
            gap: 8px;
            margin-top: 18px;
            text-align: left;
        }

        .receipt-detail-list div {
            display: flex;
            justify-content: space-between;
            gap: 16px;
            padding-bottom: 8px;
            border-bottom: 1px solid rgba(203, 213, 225, 0.16);
        }

        .receipt-detail-list span {
            color: #99f6e4;
            font-size: 0.8rem;
        }

        .receipt-detail-list strong {
            max-width: 68%;
            color: #f8fafc;
            text-align: right;
            overflow-wrap: anywhere;
        }

        .payment-reference-box {
            margin: 24px 0 12px;
            padding: 15px;
            border: 1px dashed #5eead4;
            background: rgba(15, 118, 110, 0.2);
        }

        .payment-reference-box span {
            display: block;
            margin-bottom: 6px;
            color: #99f6e4;
            font-size: 0.8rem;
            text-transform: uppercase;
        }

        .payment-reference-box strong {
            color: #ffffff;
            font-size: 1.15rem;
            letter-spacing: 1px;
        }

        .payment-receipt-method {
            font-size: 0.9rem;
        }

        .payment-receipt-history {
            display: inline-flex;
            gap: 8px;
            align-items: center;
            margin-top: 12px;
            text-decoration: none;
        }


        .checkout-modal-content {

            position: relative;

            width: 100%;

            max-width: 550px;

            max-height: 90vh;

            overflow-y: auto;

            padding: 30px;

            background: #172033;

            border-radius: 10px;

            box-shadow:
                0 10px 40px
                rgba(0, 0, 0, 0.4);
        }


        .checkout-close {

            position: absolute;

            top: 12px;

            right: 16px;

            width: 35px;

            height: 35px;

            border: none;

            background: transparent;

            color: #ffffff;

            font-size: 28px;

            line-height: 1;

            cursor: pointer;
        }


        .checkout-modal-header {

            margin-bottom: 25px;

            padding-right: 30px;
        }


        .checkout-modal-header h2 {

            margin: 0 0 5px;

            color: #ffffff;

            font-size: 1.6rem;
        }


        .checkout-modal-header p {

            margin: 0;

            color: #bfc5cf;

            font-size: 0.9rem;
        }


        .checkout-form-group {

            margin-bottom: 18px;
        }


        .checkout-form-group label {

            display: block;

            margin-bottom: 7px;

            color: #ffffff;

            font-size: 0.9rem;

            font-weight: 600;
        }


        .checkout-form-group input,
        .checkout-form-group textarea,
        .checkout-form-group select {

            width: 100%;

            box-sizing: border-box;

            padding: 12px 14px;

            border:
                1px solid #d1d5db;

            border-radius: 6px;

            background: #ffffff;

            color: #222222;

            font-family: 'Poppins', sans-serif;

            font-size: 0.9rem;

            outline: none;
        }


        .checkout-form-group input:focus,
        .checkout-form-group textarea:focus,
        .checkout-form-group select:focus {

            border-color: #0b2d5c;

            box-shadow:
                0 0 0 2px
                rgba(11, 45, 92, 0.15);
        }

            #checkout-phone {
                background: #172033;
                color: #ffffff;
            }


        .checkout-form-group input[readonly] {

            background: #f1f3f5;

            color: #333333;

            cursor: not-allowed;
        }


        .checkout-form-group textarea {

            resize: vertical;
        }


        .checkout-total-box {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin: 20px 0;

            padding: 16px 18px;

            background:
                rgba(255, 255, 255, 0.06);

            border-radius: 8px;

            color: #ffffff;
        }


        .checkout-total-box strong {

            color: #ffc107;

            font-size: 1.2rem;
        }


        .checkout-submit-btn {

            width: 100%;

            padding: 13px 20px;

            border: none;

            cursor: pointer;
        }


        /* ================================
           MOBILE
        ================================= */

        @media (max-width: 768px) {

            .cart-container {

                margin-top: 25px;

                padding: 0 12px;
            }


            .cart-container > h2 {

                font-size: 1.5rem;
            }


            .cart-actions-row {

                flex-direction: column;

                align-items: stretch;

                gap: 20px;

                padding: 20px;
            }


            .cart-summary {

                text-align: left;
            }


            .checkout-btn {

                width: 100%;
            }


            .cart-continue {

                display: inline-block;
            }


            .checkout-modal-content {

                padding: 25px 20px;
            }
        }


        @media (max-width: 480px) {

            .cart-container {

                padding: 0 10px;
            }


            .cart-container > h2 {

                font-size: 1.35rem;
            }


            .cart-table {

                min-width: 700px;
            }


            .cart-actions-row {

                padding: 18px;
            }


            .cart-summary-price {

                font-size: 1.15rem;
            }
        }

    </style>

</head>


<body>

    <!-- HEADER -->

    <?php require_once 'components/header.php'; ?>


    <!-- MAIN -->

    <main class="container cart-container">

        <h2>
            Your Shopping Cart
        </h2>

        <?php if ($cartError !== ''): ?>
            <p class="alert alert-danger" role="alert"><?= htmlspecialchars($cartError, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>

        <?php if (empty($cart_items)): ?>

            <!-- EMPTY CART -->

            <div class="empty-cart">

                <i class="fa-solid fa-cart-shopping"></i>

                <p>
                    Your cart is currently empty.
                </p>

                <a
                    href="shop.php"
                    class="btn btn-primary"
                >
                    Browse Shop
                </a>

            </div>


        <?php else: ?>

            <!-- CART TABLE -->

            <div class="cart-table-wrapper">

                <table class="cart-table">

                    <thead>

                        <tr>

                            <th>
                                Product
                            </th>

                            <th>
                                Price
                            </th>

                            <th>
                                Quantity
                            </th>

                            <th>
                                Subtotal
                            </th>

                            <th>
                                Action
                            </th>

                        </tr>

                    </thead>


                    <tbody>

                        <?php foreach ($cart_items as $item): ?>

                            <tr>

                                <!-- PRODUCT -->

                                <td>

                                    <div class="cart-product">

                                        <img
                                            src="<?= htmlspecialchars($item['image']) ?>"
                                            alt="<?= htmlspecialchars($item['name']) ?>"
                                            class="cart-item-img"
                                        >

                                        <span class="cart-product-name">

                                            <?= htmlspecialchars($item['name']) ?>

                                        </span>

                                    </div>

                                </td>


                                <!-- PRICE -->

                                <td class="cart-price">

                                    ₱<?= number_format(
                                        $item['price'],
                                        2
                                    ) ?>

                                </td>


                                <!-- QUANTITY -->

                                <td>
                                    <form method="POST" action="cart.php">
                                        <input type="hidden" name="action" value="update">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <input
                                            type="number"
                                            name="quantity"
                                            class="qty-input"
                                            value="<?= (int) $item['quantity'] ?>"
                                            min="1"
                                            max="<?= (int) $item['stock'] ?>"
                                            onchange="this.form.submit()"
                                        >
                                    </form>
                                </td>


                                <!-- SUBTOTAL -->

                                <td class="cart-subtotal">

                                    ₱<?= number_format(
                                        $item['total'],
                                        2
                                    ) ?>

                                </td>


                                <!-- REMOVE -->

                                <td>
                                    <form method="POST" action="cart.php" onsubmit="return confirm('Remove this product from your cart?');">
                                        <input type="hidden" name="action" value="remove">
                                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>">
                                        <button type="submit" class="btn-remove" title="Remove item"><i class="fa-solid fa-trash"></i></button>
                                    </form>
                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>


            <!-- CART SUMMARY -->

            <div class="cart-actions-row">

                <div>

                    <a
                        href="shop.php"
                        class="cart-continue"
                    >

                        <i
                            class="fa-solid fa-arrow-left"
                        ></i>

                        Continue Shopping

                    </a>

                </div>


                <div class="cart-summary">

                    <div>

                        <span class="cart-summary-label">
                            Subtotal:
                        </span>

                        <span class="cart-summary-price">

                            ₱<?= number_format(
                                $subtotal,
                                2
                            ) ?>

                        </span>

                    </div>


                    <!-- OPEN CHECKOUT MODAL -->

                    <button
                        type="button"
                        class="checkout-btn"
                        onclick="openCheckoutModal()"
                    >

                        Proceed to Checkout

                        <i
                            class="fa-solid fa-arrow-right"
                        ></i>

                    </button>

                </div>

            </div>

        <?php endif; ?>

    </main>


    <!-- FOOTER -->

    <?php require_once 'components/footer.php'; ?>


    <!-- =====================================================
         CHECKOUT MODAL
    ====================================================== -->

    <div
        id="checkout-modal"
        class="auth-modal"
    >

        <div class="checkout-modal-content">

            <!-- CLOSE -->

            <button
                type="button"
                class="checkout-close"
                onclick="closeCheckoutModal()"
                aria-label="Close checkout"
            >
                &times;
            </button>


            <!-- HEADER -->

            <div class="checkout-modal-header">

                <h2>
                    Checkout
                </h2>

                <p>
                    Review your information and enter your delivery address.
                </p>

            </div>


            <!-- FORM -->

            <form
                action="process_checkout.php"
                method="POST"
                id="checkout-form"
            >

                <!-- CSRF -->

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= htmlspecialchars(
                        $_SESSION['csrf_token']
                    ) ?>"
                >


                <!-- CHECKOUT TYPE -->

                <input
                    type="hidden"
                    name="checkout_type"
                    value="cart"
                >


                <!-- FULL NAME -->

                <div class="checkout-form-group">

                    <label for="checkout-full-name">
                        Full Name
                    </label>

                    <input
                        type="text"
                        id="checkout-full-name"
                        value="<?= htmlspecialchars(
                            $checkoutFullName
                        ) ?>"
                        readonly
                    >

                </div>


                <!-- PHONE -->

                <div class="checkout-form-group">

                    <label for="checkout-phone">
                        Phone Number
                    </label>

                    <input
                        type="tel"
                        id="checkout-phone"
                        name="phone"
                        inputmode="numeric"
                        pattern="09[0-9]{9}"
                        minlength="11"
                        maxlength="11"
                        title="Enter an 11-digit Philippine mobile number starting with 09"
                        value="<?= htmlspecialchars(
                            $checkoutPhone,
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        required
                    >

                </div>


                <!-- ADDRESS -->

                <div class="checkout-form-group">

                    <label for="checkout-address">
                        Delivery Address
                    </label>

                    <textarea
                        id="checkout-address"
                        name="address"
                        rows="4"
                        placeholder="Enter your complete delivery address"
                        required
                    ></textarea>

                </div>


                <!-- PAYMENT -->

                <div class="checkout-form-group">

                    <label for="checkout-payment">
                        Payment Method
                    </label>

                    <select
                        id="checkout-payment"
                        name="payment_method"
                        required
                    >
                        <option value="Cash on Delivery">Cash on Delivery (COD)</option>
                        <option value="GCash">GCash</option>
                        <option value="Card">Credit/Debit Card</option>
                    </select>

                </div>


                <!-- TOTAL -->

                <div class="checkout-total-box">

                    <span>
                        Total:
                    </span>

                    <strong>

                        ₱<?= number_format(
                            $subtotal,
                            2
                        ) ?>

                    </strong>

                </div>


                <!-- SUBMIT -->

                <button
                    type="submit"
                    class="btn btn-primary checkout-submit-btn"
                >

                    <i
                        class="fa-solid fa-check"
                    ></i>

                    Confirm Checkout

                </button>

            </form>

        </div>

    </div>

    <div
        id="payment-receipt-modal"
        class="payment-receipt-modal"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-receipt-title"
    >
        <div class="payment-receipt-card">
            <button
                type="button"
                class="payment-receipt-close"
                onclick="closePaymentReceiptModal()"
                aria-label="Close payment receipt"
            >&times;</button>

            <div class="payment-receipt-icon">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h2 id="payment-receipt-title">Order Receipt</h2>
            <p>Your order was recorded. Review the receipt details below.</p>
            <div class="receipt-detail-list">
                <div><span>Customer</span><strong><?= htmlspecialchars($checkoutConfirmation['customer_name'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Order</span><strong><?php foreach (($checkoutConfirmation['items'] ?? []) as $itemIndex => $item): ?><?= htmlspecialchars($item['name'] ?? '', ENT_QUOTES, 'UTF-8') ?> (x<?= (int)($item['quantity'] ?? 0) ?>)<?php if ($itemIndex < count($checkoutConfirmation['items']) - 1): ?>, <?php endif; ?><?php endforeach; ?></strong></div>
                <div><span>Phone</span><strong><?= htmlspecialchars($checkoutConfirmation['phone'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Address</span><strong><?= htmlspecialchars($checkoutConfirmation['address'] ?? '', ENT_QUOTES, 'UTF-8') ?></strong></div>
                <div><span>Payment method</span><strong><?= htmlspecialchars($checkoutConfirmation['payment_method'] ?? $checkoutPaymentMethod, ENT_QUOTES, 'UTF-8') ?></strong></div>
            </div>
            <div class="payment-reference-box">
                <span>Reference number</span>
                <strong><?= htmlspecialchars($checkoutPaymentReference, ENT_QUOTES, 'UTF-8') ?></strong>
            </div>
            <p class="payment-receipt-method">
                Method: <?= htmlspecialchars($checkoutPaymentMethod, ENT_QUOTES, 'UTF-8') ?>
            </p>
            <a href="order_history.php" class="btn btn-primary payment-receipt-history">
                <i class="fa-solid fa-receipt"></i> View receipt history
            </a>
        </div>
    </div>


    <!-- JAVASCRIPT -->

    <script>

        // =====================================================
        // OPEN CHECKOUT MODAL
        // =====================================================

        function openCheckoutModal() {

            const modal =
                document.getElementById(
                    'checkout-modal'
                );

            if (!modal) {
                return;
            }

            modal.classList.add('active');

            document.body.style.overflow =
                'hidden';
        }


        // =====================================================
        // CLOSE CHECKOUT MODAL
        // =====================================================

        function closeCheckoutModal() {

            const modal =
                document.getElementById(
                    'checkout-modal'
                );

            if (!modal) {
                return;
            }

            modal.classList.remove('active');

            document.body.style.overflow =
                '';
        }

        function closePaymentReceiptModal() {
            const modal = document.getElementById('payment-receipt-modal');

            if (modal) {
                modal.classList.remove('active');
                document.body.style.overflow = '';
            }
        }


        // =====================================================
        // CLOSE MODAL WHEN CLICKING OUTSIDE
        // =====================================================

        window.addEventListener(
            'click',
            function (event) {

                const modal =
                    document.getElementById(
                        'checkout-modal'
                    );

                const receiptModal = document.getElementById(
                    'payment-receipt-modal'
                );

                if (
                    modal &&
                    event.target === modal
                ) {

                    closeCheckoutModal();
                }

                if (receiptModal && event.target === receiptModal) {
                    closePaymentReceiptModal();
                }
            }
        );


        // =====================================================
        // SUCCESS ALERT
        // =====================================================

        <?php if (!empty($checkoutSuccess)): ?>

        window.addEventListener(
            'load',
            function () {

                <?php if ($checkoutPaymentReference): ?>
                const receiptModal = document.getElementById('payment-receipt-modal');

                if (receiptModal) {
                    receiptModal.classList.add('active');
                    document.body.style.overflow = 'hidden';
                }
                <?php else: ?>
                alert(<?= json_encode($checkoutSuccess) ?>);
                <?php endif; ?>

            }
        );

        <?php endif; ?>


        // =====================================================
        // ERROR ALERT
        // =====================================================

        <?php if (!empty($checkoutError)): ?>

        window.addEventListener(
            'load',
            function () {

                alert(
                    <?= json_encode($checkoutError) ?>
                );

            }
        );

        <?php endif; ?>

    </script>

</body>

</html>