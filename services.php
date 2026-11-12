<?php

require_once 'includes/config.php';

require_once __DIR__ . '/includes/auth.php';

/*
|--------------------------------------------------------------------------
| CSRF TOKEN
|--------------------------------------------------------------------------
*/

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


/*
|--------------------------------------------------------------------------
| REMEMBER ME LOGIN
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['user_id']) &&
    isset($_COOKIE['remember_token']) &&
    isset($pdo)
) {

    $token = $_COOKIE['remember_token'];

    try {

        $stmt = $pdo->prepare("
            SELECT userId as id, username, userFirstName as first_name, userRole as role FROM `user` WHERE userRememberToken = ?
        ");

        $stmt->execute([$token]);

        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {

            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['first_name'] = $user['first_name'];
            $_SESSION['role'] = $user['role'] ?? 'user';
        }

    } catch (Exception $e) {
        // Ignore remember-me lookup failure
    }
}


/*
|--------------------------------------------------------------------------
| LOGGED-IN USER INFORMATION
|--------------------------------------------------------------------------
|
| Used by the service-booking modal for autofill.
|--------------------------------------------------------------------------
*/

$user_fullname = '';
$user_phone = '';

if (
    isset($_SESSION['user_id']) &&
    isset($pdo)
) {

    try {

        $stmt = $pdo->prepare("
            SELECT userFirstName as first_name, userLastName as last_name, userPhone as phone FROM `user` WHERE userId = ?
        ");

        $stmt->execute([
            $_SESSION['user_id']
        ]);

        $userData = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($userData) {

            $firstName =
                $userData['first_name'] ?? '';

            $lastName =
                $userData['last_name'] ?? '';

            $user_fullname =
                trim($firstName . ' ' . $lastName);

            if (empty($user_fullname)) {

                $user_fullname =
                    $_SESSION['first_name'] ?? '';
            }

            if (!empty($userData['phone'])) {

                $user_phone =
                    $userData['phone'];
            }
        }

    } catch (Exception $e) {

        // Fallback silently
    }
}


/*
|--------------------------------------------------------------------------
| CART COUNT
|--------------------------------------------------------------------------
*/

$cart_count = 0;

if (
    isset($_SESSION['cart']) &&
    is_array($_SESSION['cart'])
) {

    foreach ($_SESSION['cart'] as $item) {

        $cart_count +=
            is_array($item)
                ? ($item['quantity'] ?? 1)
                : 1;
    }
}


$services = [];

if (isset($pdo)) {

    try {
        $stmt = $pdo->query("
            SELECT serviceId as id, serviceName as name, serviceDescription as description, servicePrice as price, serviceImage as image, serviceActive as active, serviceFeatured as featured, serviceId, serviceName, serviceDescription, servicePrice, serviceImage, serviceActive, serviceFeatured FROM service WHERE serviceActive = 1 ORDER BY serviceId ASC
        ");

        $services =
            $stmt->fetchAll(PDO::FETCH_ASSOC);

    } catch (Exception $e) {

        error_log('Unable to load services: ' . $e->getMessage());
        $services = [];
    }

} else {

    $services = [];
}

$seenServiceNames = [];
$services = array_values(array_filter(
    $services,
    static function ($service) use (&$seenServiceNames) {
        $name = strtolower(trim((string)($service['name'] ?? '')));

        if ($name !== '' && isset($seenServiceNames[$name])) {
            return false;
        }

        if ($name !== '') {
            $seenServiceNames[$name] = true;
        }

        return true;
    }
));

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
        Our Services - Bencalo MotoWorks
    </title>


    <link
        href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

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

    <style>

        /*
        |--------------------------------------------------------------------------
        | Service booking modal sizing
        |--------------------------------------------------------------------------
        */

        #service-booking-modal .auth-modal-content {

            max-height: 90vh;
            overflow-y: auto;
        }

    </style>

</head>


<body>


<a
    href="#main-content"
    class="skip-link"
>
    Skip to main content
</a>


<!-- =========================================================
     SITE HEADER
========================================================= -->

<?php require_once 'components/header.php'; ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<main
    id="main-content"
    class="container main-content-shop"
>

    <div class="section-title">

        <h2>
            Professional Services & Tune-Ups
        </h2>

    </div>


    <!-- =====================================================
         SEARCH
    ====================================================== -->

    <div class="search-bar-container">

        <input
            type="text"
            id="product-search-input"
            class="search-input-field"
            placeholder="Search services..."
            autocomplete="off"
        >

    </div>


    <!-- =====================================================
         SERVICE GRID
    ====================================================== -->

    <div
        class="card-grid product-grid"
        id="product-grid-container"
    >

        <?php foreach ($services as $s): ?>

            <?php

            $priceFormatted =
                is_numeric($s['price'])
                    ? '₱' . number_format(
                        (float)$s['price'],
                        2
                    )
                    : $s['price'];

            $isFeatured =
                !empty($s['featured']);

            $cardClass =
                $isFeatured
                    ? 'product-card product-card-featured'
                    : 'product-card';

            $btnClass =
                $isFeatured
                    ? 'btn btn-cyan'
                    : 'btn btn-primary';

            ?>

            <div
                class="<?= $cardClass ?>"
                data-name="<?= htmlspecialchars(
                    strtolower($s['name']),
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>"
            >


                <!-- IMAGE -->

                <div class="product-image">

                    <img
                        src="<?= htmlspecialchars(
                            $s['image'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                        alt="<?= htmlspecialchars(
                            $s['name'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>"
                    >

                </div>


                <!-- NAME -->

                <h3 class="product-name">

                    <?= htmlspecialchars(
                        $s['name'],
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </h3>


                <!-- DESCRIPTION -->

                <p
                    class="product-description"
                    style="
                        font-size: 13px;
                        opacity: 0.8;
                        margin-bottom: 10px;
                    "
                >

                    <?= htmlspecialchars(
                        $s['description'] ?? '',
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </p>


                <!-- PRICE -->

                <div class="product-price">

                    <?= htmlspecialchars(
                        $priceFormatted,
                        ENT_QUOTES,
                        'UTF-8'
                    ) ?>

                </div>


                <!-- ACTIONS -->

                <div class="product-card-actions">


                    <!-- ADD TO CART -->

                    <button
                        type="button"
                        onclick="addToCart(<?= (int)$s['id'] ?>)"
                        class="btn btn-outline btn-cart-add"
                        aria-label="Add to cart"
                    >

                        <i class="fa-solid fa-cart-plus"></i>

                    </button>


                    <!-- BOOK APPOINTMENT -->

                    <button
                        type="button"
                        onclick="openServiceBookingModal(
                            <?= (int)$s['id'] ?>,
                            <?= htmlspecialchars(
                                json_encode($s['name']),
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        )"
                        class="<?= $btnClass ?>"
                    >

                        Book Appointment

                    </button>

                </div>

            </div>

        <?php endforeach; ?>

    </div>

</main>


<!-- =========================================================
     SITE FOOTER
========================================================= -->

<?php require_once 'components/footer.php'; ?>


<!-- =========================================================
     SITE MODALS
========================================================= -->
<!--
    IMPORTANT:
    The service-booking modal is already inside
    components/modals.php.

    Do NOT create another #service-booking-modal here.
-->

<?php require_once 'components/modals.php'; ?>


<!-- =========================================================
     SERVICES JAVASCRIPT
========================================================= -->

<script>

/*
|--------------------------------------------------------------------------
| ADD SERVICE TO CART
|--------------------------------------------------------------------------
*/

function addToCart(serviceId) {

    fetch('cart_action.php', {

        method: 'POST',

        headers: {
            'Content-Type':
                'application/x-www-form-urlencoded'
        },

        body:
            'action=add' +
            '&product_id=' +
            encodeURIComponent(serviceId) +
            '&csrf_token=' +
            encodeURIComponent(
                '<?= htmlspecialchars(
                    $_SESSION['csrf_token'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>'
            )

    })

    .then(response => {

        if (!response.ok) {
            throw new Error(
                'Network response was not OK.'
            );
        }

        return response.json();

    })

    .then(data => {

        if (data.success) {

            const badge =
                document.getElementById(
                    'cart-count-badge'
                );

            if (badge) {

                badge.textContent =
                    data.cart_count;
            }
        }

    })

    .catch(error => {

        console.error(
            'Add to cart error:',
            error
        );

    });
}


/*
|--------------------------------------------------------------------------
| SEARCH SERVICES
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'DOMContentLoaded',
    function() {


        const searchInput =
            document.getElementById(
                'product-search-input'
            );


        if (searchInput) {

            searchInput.addEventListener(
                'input',
                function(event) {

                    const query =
                        event.target.value
                            .toLowerCase()
                            .trim();


                    const cards =
                        document.querySelectorAll(
                            '#product-grid-container .product-card'
                        );


                    cards.forEach(
                        function(card) {

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


        /*
        |--------------------------------------------------------------------------
        | MOBILE NAVIGATION
        |--------------------------------------------------------------------------
        */

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
                function() {

                    const expanded =
                        navToggleBtn.getAttribute(
                            'aria-expanded'
                        ) === 'true';


                    navToggleBtn.setAttribute(
                        'aria-expanded',
                        String(!expanded)
                    );


                    mainNav.classList.toggle(
                        'active'
                    );

                }
            );

        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVE NAVIGATION LINK
        |--------------------------------------------------------------------------
        */

        const currentPath =
            window.location.pathname
                .split('/')
                .pop();


        const navLinks =
            document.querySelectorAll(
                '.main-nav a, .header-nav-link'
            );


        navLinks.forEach(
            function(link) {

                const linkHref =
                    link.getAttribute('href');


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