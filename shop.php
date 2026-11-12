<?php

require_once 'includes/config.php';

require_once __DIR__ . '/includes/auth.php';


// =====================================================
// CSRF TOKEN
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// =====================================================
// REMEMBER ME LOGIN
// =====================================================

if (
    !isset($_SESSION['user_id']) &&
    isset($_COOKIE['remember_token']) &&
    isset($pdo)
) {

    $token = $_COOKIE['remember_token'];

    $stmt = $pdo->prepare("
        SELECT userId as id, username, userFirstName as first_name, userRole as role FROM `user` WHERE userRememberToken = ? LIMIT 1
    ");

    $stmt->execute([$token]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user) {

        $_SESSION['user_id'] =
            $user['id'];

        $_SESSION['username'] =
            $user['username'];

        $_SESSION['first_name'] =
            $user['first_name'];

        $_SESSION['role'] =
            $user['role'] ?? 'user';
    }
}


// =====================================================
// USER PHONE
// =====================================================

$user_phone = '';

if (
    isset($_SESSION['user_id']) &&
    isset($pdo)
) {

    try {

        $stmt = $pdo->prepare("
            SELECT userPhone as phone FROM `user` WHERE userId = ? LIMIT 1
        ");

        $stmt->execute([
            $_SESSION['user_id']
        ]);

        $userData =
            $stmt->fetch(PDO::FETCH_ASSOC);

        if (
            $userData &&
            !empty($userData['phone'])
        ) {

            $user_phone =
                $userData['phone'];
        }

    } catch (Exception $e) {

        $user_phone = '';
    }
}


// =====================================================
// CART COUNT
// =====================================================

$cart_count = 0;

if (
    isset($_SESSION['cart']) &&
    is_array($_SESSION['cart'])
) {

    foreach ($_SESSION['cart'] as $item) {

        if (is_array($item)) {

            $cart_count +=
                (int)($item['quantity'] ?? 1);

        } else {

            $cart_count++;
        }
    }
}


// =====================================================
// LOAD PRODUCTS
// =====================================================

$products = [];

if (isset($pdo)) {

    try {

        $stmt = $pdo->query("
            SELECT productId as id, productName as name, productPrice as price, productStock as stock, productImage as image, productActive as active, productId, productName, productPrice, productStock, productImage, productActive FROM product WHERE productActive = 1 ORDER BY productId ASC
        ");

        $products =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {

        error_log('Unable to load shop products: ' . $e->getMessage());
        $products = [];
    }
} else {
    $products = [];
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
        Shop All Products - Bencalo MotoWorks
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
        href="styles/index/modals.css"
    >

    <link
        rel="stylesheet"
        href="styles/services.css"
    >

    <link
        rel="stylesheet"
        href="styles/shop.css"
    >


    <style>

        .modal-alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
            display: none;
        }

        .modal-alert.error {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
            display: block;
        }

        .modal-alert.success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            display: block;
        }


        /* =================================================
           FLYING CART ANIMATION
           ================================================= */

        .flying-cart-clone {
            position: fixed;
            z-index: 99999;
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 50%;
            border: 2px solid #ffc107;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.3);
            pointer-events: none;
            transition:
                all 1.8s cubic-bezier(0.25, 1, 0.5, 1);
        }


        @keyframes cartBadgeBounce {

            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.4);
                background-color: #ffc107;
                color: #000;
            }

            100% {
                transform: scale(1);
            }
        }


        .cart-badge-bounce {
            animation:
                cartBadgeBounce 0.5s ease-in-out;
        }

    </style>

</head>


<body>


    <!-- =================================================
         SKIP LINK
         ================================================= -->

    <a
        href="#main-content"
        class="skip-link"
    >
        Skip to main content
    </a>


    <!-- =================================================
         HEADER
         ================================================= -->

    <?php
    require_once 'components/header.php';
    ?>


    <!-- =================================================
         MAIN CONTENT
         ================================================= -->

    <main
        id="main-content"
        class="container main-content-shop"
    >


        <div class="section-title">

            <h2>
                All Products & Genuine Parts
            </h2>

        </div>


        <!-- SEARCH -->

        <div class="search-bar-container">

            <input
                type="text"
                id="product-search-input"
                class="search-input-field"
                placeholder="Search catalog..."
                autocomplete="off"
            >

        </div>


        <!-- =================================================
             PRODUCT GRID
             ================================================= -->

        <div
            class="card-grid product-grid"
            id="product-grid-container"
        >


            <?php foreach ($products as $p): ?>

                <?php

                // -------------------------------------------------
                // PRICE
                // -------------------------------------------------

                $priceFormatted =
                    is_numeric($p['price'])
                    ? '₱' . number_format(
                        (float)$p['price'],
                        2
                    )
                    : $p['price'];


                // -------------------------------------------------
                // FEATURED
                // -------------------------------------------------

                $isFeatured =
                    !empty($p['featured']);


                $cardClass =
                    $isFeatured
                    ? 'product-card product-card-featured'
                    : 'product-card';


                $btnClass =
                    $isFeatured
                    ? 'btn btn-cyan btn-block'
                    : 'btn btn-primary btn-block';


                // -------------------------------------------------
                // STOCK
                // -------------------------------------------------

                $stockLevel = 0;

                foreach (
                    [
                        'stock_level',
                        'stock',
                        'quantity',
                        'qty'
                    ] as $col
                ) {

                    if (
                        isset($p[$col])
                    ) {

                        $stockLevel =
                            (int)$p[$col];

                        break;
                    }
                }


                // -------------------------------------------------
                // PRODUCT DATA
                // -------------------------------------------------

                $productId =
                    (int)($p['id'] ?? 0);


                $productPrice =
                    is_numeric($p['price'] ?? null)
                    ? (float)$p['price']
                    : 0;


                $productName =
                    htmlspecialchars(
                        $p['name'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    );


                $productImagePath = str_replace(
                    '\\',
                    '/',
                    trim((string)($p['image'] ?? ''))
                );

                if (
                    $productImagePath !== '' &&
                    basename($productImagePath) === $productImagePath
                ) {
                    $productImagePath = 'uploads/' . $productImagePath;
                }

                $productImageFile = str_replace(
                    '/',
                    DIRECTORY_SEPARATOR,
                    $productImagePath
                );

                if ($productImagePath === '' || !is_file(__DIR__ . DIRECTORY_SEPARATOR . $productImageFile)) {
                    $productImagePath = '';
                }

                $productImage = htmlspecialchars(
                    $productImagePath,
                    ENT_QUOTES,
                    'UTF-8'
                );

                ?>


                <!-- PRODUCT CARD -->

                <div
                    class="<?= $cardClass ?>"
                    data-name="<?=
                        strtolower(
                            htmlspecialchars(
                                $p['name'] ?? '',
                                ENT_QUOTES,
                                'UTF-8'
                            )
                        )
                    ?>"
                >


                    <!-- IMAGE -->

                    <div class="product-image">
                        <?php if ($productImagePath !== ''): ?>
                            <img src="<?= $productImage ?>" alt="<?= $productName ?>">
                        <?php else: ?>
                            <i class="fa-solid fa-box-open" aria-hidden="true"></i>
                        <?php endif; ?>
                    </div>


                    <!-- NAME -->

                    <h3 class="product-name">

                        <?= $productName ?>

                    </h3>


                    <!-- PRICE -->

                    <div class="product-price">

                        <?= $priceFormatted ?>

                    </div>


                    <!-- STOCK -->

                    <div
                        class="product-stock"
                        style="
                            font-size: 0.85rem;
                            margin: 5px 0 10px 0;
                            color:
                            <?= ($stockLevel > 0)
                                ? '#2e7d32'
                                : '#c62828'
                            ?>;
                        "
                    >

                        Stock:
                        <strong>
                            <?= $stockLevel ?>
                        </strong>
                        available

                    </div>


                    <!-- ACTIONS -->

                    <div class="product-card-actions">


                        <!-- ADD TO CART -->

                        <button
                            type="button"
                            onclick="openCartModal(
                                <?= $productId ?>,
                                '<?= addslashes(
                                    htmlspecialchars(
                                        $p['name'] ?? '',
                                        ENT_QUOTES,
                                        'UTF-8'
                                    )
                                ) ?>',
                                <?= $productPrice ?>,
                                <?= $stockLevel ?>
                            )"
                            class="
                                btn
                                btn-outline
                                btn-block
                                btn-cart-add
                            "
                            <?= ($stockLevel <= 0)
                                ? 'disabled
                                   style="
                                       opacity: 0.5;
                                       cursor: not-allowed;
                                   "'
                                : ''
                            ?>
                        >

                            <i
                                class="fa-solid fa-cart-plus"
                            ></i>

                        </button>


                        <!-- BUY NOW -->

                        <?php if ($stockLevel > 0): ?>

                            <button
                                type="button"
                                onclick="openBuyModal(
                                    <?= $productId ?>,
                                    '<?= addslashes(
                                        htmlspecialchars(
                                            $p['name'] ?? '',
                                            ENT_QUOTES,
                                            'UTF-8'
                                        )
                                    ) ?>',
                                    <?= $productPrice ?>,
                                    <?= $stockLevel ?>
                                )"
                                class="<?= $btnClass ?>"
                            >

                                Buy Now

                            </button>

                        <?php else: ?>

                            <button
                                type="button"
                                class="
                                    btn
                                    btn-secondary
                                    btn-block
                                "
                                disabled
                                style="
                                    background: #ccc;
                                    cursor: not-allowed;
                                    border-color: #ccc;
                                "
                            >

                                Out of Stock

                            </button>

                        <?php endif; ?>


                    </div>

                </div>

            <?php endforeach; ?>


        </div>

    </main>


    <!-- =================================================
         FOOTER
         ================================================= -->

    <?php
    require_once 'components/footer.php';
    ?>


    <!-- =================================================
         MODALS
         ================================================= -->

    <?php
    require_once 'components/modals.php';
    ?>


    <!-- =================================================
         SHOP JAVASCRIPT
         ================================================= -->

    <script>


        // =================================================
        // GLOBAL VARIABLES
        // =================================================

        if (
            typeof window.isLoggedIn === 'undefined'
        ) {

            window.isLoggedIn =
                <?= isset($_SESSION['user_id'])
                    ? 'true'
                    : 'false'
                ?>;
        }


        if (
            typeof window.maxStock === 'undefined'
        ) {

            window.maxStock = 1;
        }


        // =================================================
        // OPEN MODAL
        // =================================================

        function openModal(modalId) {

            const modal =
                document.getElementById(
                    modalId
                );

            if (modal) {
                modal.classList.add('active');
            }
        }


        // =================================================
        // CLOSE MODAL
        // =================================================

        function closeModal(modalId) {

            const modal =
                document.getElementById(
                    modalId
                );

            if (modal) {
                modal.classList.remove('active');
            }


            const alertBox =
                document.getElementById(
                    modalId + '-alert'
                );


            if (alertBox) {

                alertBox.className =
                    'modal-alert';

                alertBox.textContent =
                    '';
            }
        }


        // =================================================
        // SWITCH MODAL
        // =================================================

        function switchModal(
            closeId,
            openId
        ) {

            closeModal(closeId);
            openModal(openId);
        }


        // =================================================
        // SHOW MODAL ALERT
        // =================================================

        function showModalAlert(
            modalId,
            message,
            type = 'error'
        ) {

            const alertBox =
                document.getElementById(
                    modalId + '-alert'
                );


            if (alertBox) {

                alertBox.textContent =
                    message;

                alertBox.className =
                    'modal-alert ' + type;
            }
        }


        // =================================================
        // BUY MODAL
        // =================================================

        function openBuyModal(
            productId,
            productName,
            productPrice,
            stockLevel = 1
        ) {

            if (!window.isLoggedIn) {

                openModal('login-modal');

                showModalAlert(
                    'login-modal',
                    'Please log in to continue with your purchase.',
                    'error'
                );

                return;
            }


            const idField =
                document.getElementById(
                    'buy-product-id'
                );

            const nameField =
                document.getElementById(
                    'buy-product-name'
                );

            const priceField =
                document.getElementById(
                    'buy-product-price'
                );

            const quantityField =
                document.getElementById(
                    'buy-quantity'
                );

            const warning =
                document.getElementById(
                    'buy-stock-warning'
                );


            if (idField) {
                idField.value = productId;
            }


            if (nameField) {
                nameField.textContent =
                    productName;
            }


            if (priceField) {

                priceField.textContent =
                    typeof productPrice === 'number'
                    ? '₱' + productPrice.toFixed(2)
                    : productPrice;
            }


            window.maxStock =
                parseInt(stockLevel) || 1;


            if (quantityField) {

                quantityField.max =
                    window.maxStock;

                quantityField.min =
                    1;

                quantityField.value =
                    1;

                quantityField.dataset.productId =
                    productId;
            }


            if (warning) {

                warning.style.display =
                    'none';

                warning.textContent =
                    '';
            }


            openModal('buy-modal');
        }


        // =================================================
        // CART MODAL
        // =================================================

        function openCartModal(
            productId,
            productName,
            productPrice,
            stockLevel = 1
        ) {

            if (!window.isLoggedIn) {

                openModal('login-modal');

                showModalAlert(
                    'login-modal',
                    'Please log in to add items to your cart.',
                    'error'
                );

                return;
            }


            const idField =
                document.getElementById(
                    'cart-product-id'
                );

            const nameField =
                document.getElementById(
                    'cart-product-name'
                );

            const priceField =
                document.getElementById(
                    'cart-product-price'
                );

            const quantityField =
                document.getElementById(
                    'cart-quantity'
                );

            const warning =
                document.getElementById(
                    'cart-stock-warning'
                );


            if (idField) {
                idField.value =
                    productId;
            }


            if (nameField) {
                nameField.textContent =
                    productName;
            }


            if (priceField) {

                priceField.textContent =
                    typeof productPrice === 'number'
                    ? '₱' + productPrice.toFixed(2)
                    : productPrice;
            }


            if (quantityField) {

                quantityField.max =
                    parseInt(stockLevel) || 1;

                quantityField.min =
                    1;

                quantityField.value =
                    1;

                quantityField.dataset.maxStock =
                    parseInt(stockLevel) || 1;
            }


            if (warning) {

                warning.style.display =
                    'none';

                warning.textContent =
                    '';
            }


            openModal('cart-modal');
        }


        // =================================================
        // BUY STOCK VALIDATION
        // =================================================

        function validateStockLimit() {

            const quantityField =
                document.getElementById(
                    'buy-quantity'
                );

            const warning =
                document.getElementById(
                    'buy-stock-warning'
                );


            if (
                !quantityField ||
                !warning
            ) {
                return;
            }


            let currentValue =
                parseInt(
                    quantityField.value
                );


            if (isNaN(currentValue)) {
                currentValue = 1;
            }


            const maximum =
                parseInt(
                    window.maxStock
                ) || 1;


            if (
                currentValue > maximum
            ) {

                warning.innerText =
                    'Maximum available stock is ' +
                    maximum;

                warning.style.display =
                    'block';

                quantityField.value =
                    maximum;

            } else if (
                currentValue < 1
            ) {

                quantityField.value =
                    1;

                warning.style.display =
                    'none';

            } else {

                warning.style.display =
                    'none';
            }
        }


        // =================================================
        // CART STOCK VALIDATION
        // =================================================

        function validateCartStockLimit() {

            const quantityField =
                document.getElementById(
                    'cart-quantity'
                );

            const warning =
                document.getElementById(
                    'cart-stock-warning'
                );


            if (
                !quantityField ||
                !warning
            ) {
                return;
            }


            const maximum =
                parseInt(
                    quantityField.dataset.maxStock
                ) || 1;


            let currentValue =
                parseInt(
                    quantityField.value
                );


            if (isNaN(currentValue)) {
                currentValue = 1;
            }


            if (
                currentValue > maximum
            ) {

                warning.innerText =
                    'Maximum available stock is ' +
                    maximum;

                warning.style.display =
                    'block';

                quantityField.value =
                    maximum;

            } else if (
                currentValue < 1
            ) {

                quantityField.value =
                    1;

                warning.style.display =
                    'none';

            } else {

                warning.style.display =
                    'none';
            }
        }


        // =================================================
        // PASSWORD VISIBILITY
        // =================================================

        function togglePasswordVisibility(
            fieldId,
            button
        ) {

            const passwordField =
                document.getElementById(
                    fieldId
                );


            if (
                !passwordField ||
                !button
            ) {
                return;
            }


            const icon =
                button.querySelector('i');


            if (!icon) {
                return;
            }


            if (
                passwordField.type ===
                'password'
            ) {

                passwordField.type =
                    'text';

                icon.classList.replace(
                    'fa-eye',
                    'fa-eye-slash'
                );

            } else {

                passwordField.type =
                    'password';

                icon.classList.replace(
                    'fa-eye-slash',
                    'fa-eye'
                );
            }
        }


        // =================================================
        // PAGE INITIALIZATION
        // =================================================

        document.addEventListener(
            'DOMContentLoaded',
            function () {


                // -----------------------------------------
                // MOBILE NAVIGATION
                // -----------------------------------------

                const navToggleBtn =
                    document.getElementById(
                        'nav-toggle-btn'
                    );

                const mainNav =
                    document.getElementById(
                        'main-nav'
                    );


                if (
                    navToggleBtn &&
                    mainNav
                ) {

                    navToggleBtn.addEventListener(
                        'click',
                        function () {

                            const expanded =
                                navToggleBtn.getAttribute(
                                    'aria-expanded'
                                ) === 'true';


                            navToggleBtn.setAttribute(
                                'aria-expanded',
                                !expanded
                            );


                            mainNav.classList.toggle(
                                'active'
                            );
                        }
                    );
                }


                // -----------------------------------------
                // BUY QUANTITY
                // -----------------------------------------

                const buyQuantity =
                    document.getElementById(
                        'buy-quantity'
                    );


                if (
                    buyQuantity &&
                    !buyQuantity.dataset.stockListenerAttached
                ) {

                    buyQuantity.dataset.stockListenerAttached =
                        'true';

                    buyQuantity.addEventListener(
                        'input',
                        validateStockLimit
                    );
                }


                // -----------------------------------------
                // CART QUANTITY
                // -----------------------------------------

                const cartQuantity =
                    document.getElementById(
                        'cart-quantity'
                    );


                if (
                    cartQuantity &&
                    !cartQuantity.dataset.stockListenerAttached
                ) {

                    cartQuantity.dataset.stockListenerAttached =
                        'true';

                    cartQuantity.addEventListener(
                        'input',
                        validateCartStockLimit
                    );
                }


                // -----------------------------------------
                // SEARCH
                // -----------------------------------------

                const searchInput =
                    document.getElementById(
                        'product-search-input'
                    );


                if (searchInput) {

                    searchInput.addEventListener(
                        'input',
                        function (event) {

                            const query =
                                event.target.value
                                    .toLowerCase()
                                    .trim();


                            const cards =
                                document.querySelectorAll(
                                    '#product-grid-container .product-card'
                                );


                            cards.forEach(
                                function (card) {

                                    const name =
                                        card.getAttribute(
                                            'data-name'
                                        ) || '';


                                    card.style.display =
                                        name.includes(query)
                                        ? ''
                                        : 'none';
                                }
                            );
                        }
                    );
                }


                // -----------------------------------------
                // AUTH BUTTONS
                // -----------------------------------------

                document
                    .querySelectorAll(
                        '.requires-auth'
                    )
                    .forEach(
                        function (button) {

                            button.addEventListener(
                                'click',
                                function (event) {

                                    if (
                                        !window.isLoggedIn
                                    ) {

                                        event.preventDefault();

                                        openModal(
                                            'login-modal'
                                        );

                                        showModalAlert(
                                            'login-modal',
                                            'Please log in to continue.',
                                            'error'
                                        );
                                    }
                                }
                            );
                        }
                    );


                // -----------------------------------------
                // CLOSE MODAL OUTSIDE
                // -----------------------------------------

                window.addEventListener(
                    'click',
                    function (event) {

                        if (
                            event.target.classList
                                .contains('auth-modal')
                        ) {

                            event.target.classList
                                .remove('active');


                            const alertBox =
                                event.target.querySelector(
                                    '.modal-alert'
                                );


                            if (alertBox) {

                                alertBox.className =
                                    'modal-alert';

                                alertBox.textContent =
                                    '';
                            }
                        }
                    }
                );


                // -----------------------------------------
                // ACTIVE NAVIGATION
                // -----------------------------------------

                const currentPath =
                    window.location.pathname
                        .split('/')
                        .pop();


                const navLinks =
                    document.querySelectorAll(
                        '.main-nav a, .header-nav-link'
                    );


                navLinks.forEach(
                    function (link) {

                        const linkHref =
                            link.getAttribute(
                                'href'
                            );


                        if (!linkHref) {
                            return;
                        }


                        const linkPage =
                            linkHref
                                .split('/')
                                .pop();


                        if (
                            linkPage === currentPath ||
                            (
                                currentPath === '' &&
                                (
                                    linkPage === 'index.php' ||
                                    linkPage === ''
                                )
                            )
                        ) {

                            link.classList.add(
                                'active'
                            );

                        } else {

                            link.classList.remove(
                                'active'
                            );
                        }
                    }
                );
            }
        );

    </script>

</body>

</html>