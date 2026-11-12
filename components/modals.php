<?php
require_once __DIR__ . '/../includes/auth.php';

if (!isset($pdo)) {
    require_once __DIR__ . '/../includes/config.php';
}

// =====================================================
// SESSION / CSRF SETUP
// =====================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$user_phone = $user_phone ?? '';
$modal_full_name = '';
$bookingServices = $pdo->query(
    "SELECT serviceId as id, serviceName as name, serviceId, serviceName FROM service WHERE serviceActive = 1 ORDER BY serviceName ASC"
)->fetchAll(PDO::FETCH_ASSOC);


// =====================================================
// GET LOGGED-IN USER INFORMATION
// =====================================================

if (isset($_SESSION['user_id'])) {

    try {

        $modalUserStmt = $pdo->prepare("
            SELECT userFirstName as first_name, userLastName as last_name, userPhone as phone FROM `user` WHERE userId = ? LIMIT 1
        ");

        $modalUserStmt->execute([
            $_SESSION['user_id']
        ]);

        $modalUser = $modalUserStmt->fetch(PDO::FETCH_ASSOC);

        if ($modalUser) {

            $modal_full_name = trim(
                ($modalUser['first_name'] ?? '') .
                ' ' .
                ($modalUser['last_name'] ?? '')
            );

            $user_phone = $modalUser['phone'] ?? '';
        }

    } catch (PDOException $e) {

        $modal_full_name = '';
    }
}

?>


<!-- =====================================================
     BASE MODAL CSS
     ===================================================== -->

<style>

    .auth-modal,
    .modal-overlay {

        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        height: 100vh;

        background: rgba(0, 0, 0, 0.75);

        z-index: 99999;

        display: none;

        align-items: center;
        justify-content: center;
    }


    .auth-modal.active,
    .modal-overlay.active {

        display: flex !important;
    }

    .confirmation-detail-list {
        display: grid;
        gap: 8px;
        margin: 18px 0;
        text-align: left;
    }

    .confirmation-detail-list div {
        display: flex;
        justify-content: space-between;
        gap: 16px;
        padding-bottom: 8px;
        border-bottom: 1px solid rgba(203, 213, 225, 0.16);
    }

    .confirmation-detail-list span {
        color: #99f6e4;
        font-size: 0.8rem;
    }

    .confirmation-detail-list strong {
        max-width: 68%;
        color: #f8fafc;
        text-align: right;
        overflow-wrap: anywhere;
    }

</style>


<!-- =====================================================
     1. VIEW MODAL
     ===================================================== -->

<div
    id="viewModal"
    class="modal-overlay"
    style="display: none;">

    <div class="modal-content">

        <div class="modal-header">

            <h3>
                View Promo Claim Details
            </h3>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('viewModal')">

                &times;

            </button>

        </div>


        <div
            class="modal-body"
            id="viewModalBody">

            <p>
                Loading details...
            </p>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="btn-row-action btn-view"
                onclick="closeModal('viewModal')">

                Close

            </button>

        </div>

    </div>

</div>


<!-- =====================================================
     2. EDIT MODAL
     ===================================================== -->

<div
    id="editModal"
    class="modal-overlay"
    style="display: none;">

    <div class="modal-content">

        <div class="modal-header">

            <h3>
                Edit Promo Claim / Appointment
            </h3>

            <button
                type="button"
                class="modal-close"
                onclick="closeModal('editModal')">

                &times;

            </button>

        </div>


        <div
            class="modal-body"
            id="editModalBody">

            <p>
                Loading form...
            </p>

        </div>

    </div>

</div>


<!-- =====================================================
     3. BUY NOW / CHECKOUT MODAL
     ===================================================== -->

<div
    id="buy-modal"
    class="auth-modal"
    style="display: none;">

    <div class="auth-modal-content modal-sm">

        <button
            type="button"
            class="auth-modal-close"
            onclick="closeModal('buy-modal')">

            &times;

        </button>


        <h2 class="modal-heading">
            Complete Your Order
        </h2>


        <div
            id="buy-modal-alert"
            class="modal-alert">
        </div>


        <!-- PRODUCT SUMMARY -->

        <div
            id="buy-modal-product-summary"
            class="product-summary-box"
            style="margin-bottom: 15px;">

            <div
                class="buy-product-name"
                id="buy-product-name">

                Product Name

            </div>


            <div
                class="buy-product-price"
                id="buy-product-price">

                ₱0.00

            </div>

        </div>


        <!-- CHECKOUT FORM -->

        <form
            id="ajax-buy-form"
            class="auth-form"
            action="process_checkout.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="hidden"
                name="product_id"
                id="buy-product-id"
                value="">

            <input
                type="hidden"
                name="checkout_type"
                value="buy_now">


            <!-- QUANTITY -->

            <div class="form-group">

                <label for="buy-quantity">
                    Quantity
                </label>


                <input
                    type="number"
                    id="buy-quantity"
                    name="quantity"
                    min="1"
                    value="1"
                    required>


                <div
                    id="buy-stock-warning"
                    class="stock-warning"
                    style="display: none;">
                </div>

            </div>


            <!-- REGISTERED USER INFORMATION -->

            <div class="form-group">

                <label>
                    Full Name & Phone Number
                </label>


                <div
                    style="
                        display: grid;
                        grid-template-columns: 1fr 1fr;
                        gap: 10px;
                    ">


                    <input
                        type="text"
                        name="full_name"
                        id="buy-full-name"
                        placeholder="Full Name"
                        value="<?= htmlspecialchars(
                                    $modal_full_name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        readonly
                        required>


                    <input
                        type="tel"
                        name="phone"
                        id="buy-phone"
                        placeholder="Phone Number"
                        inputmode="numeric"
                        pattern="09[0-9]{9}"
                        minlength="11"
                        maxlength="11"
                        title="Enter an 11-digit Philippine mobile number starting with 09"
                        style="
                            background: var(--color-bg-alt, #1e272e);
                            color: var(--color-text, #fff);
                            border: 1px solid var(--color-primary, #00d2d3);
                        "
                        value="<?= htmlspecialchars(
                                    $user_phone,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        required>

                </div>

            </div>


            <!-- SHIPPING ADDRESS -->

            <div class="form-group">

                <label for="buy-address">
                    Shipping Address
                </label>


                <textarea
                    id="buy-address"
                    name="address"
                    rows="3"
                    placeholder="Shipping Address"
                    required></textarea>

            </div>


            <!-- PAYMENT METHOD -->

            <div class="form-group">

                <label for="buy-payment-method">
                    Payment Method:
                </label>


                <select
                    id="buy-payment-method"
                    name="payment_method"
                    required
                    style="
                        width: 100%;
                        padding: 10px;
                        background: var(--color-bg-alt, #1e272e);
                        border: 1px solid var(--color-primary, #00d2d3);
                        color: var(--color-text, #fff);
                        border-radius: 4px;
                    ">

                    <option value="Cash on Delivery">
                        Cash on Delivery (COD)
                    </option>

                    <option value="GCash">
                        GCash
                    </option>

                    <option value="Card">
                        Credit/Debit Card
                    </option>

                </select>

            </div>


            <!-- PLACE ORDER -->

            <button
                type="submit"
                class="btn btn-primary btn-block">

                Place Order

            </button>

        </form>

        <div
            id="order-confirmation-panel"
            style="display: none; text-align: center; padding: 12px 4px 4px;"
        >
            <div style="font-size: 3rem; color: #2ed573;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3 style="margin: 12px 0 8px;">Order Confirmed</h3>
            <p style="color: var(--color-text-muted, #aaa); margin-bottom: 18px;">
                Your order was recorded. Keep this reference number for your receipt.
            </p>
            <div class="confirmation-detail-list">
                <div><span>Customer</span><strong id="order-confirmation-customer"></strong></div>
                <div><span>Order</span><strong id="order-confirmation-item"></strong></div>
                <div><span>Phone</span><strong id="order-confirmation-phone"></strong></div>
                <div><span>Address</span><strong id="order-confirmation-address"></strong></div>
                <div><span>Payment method</span><strong id="order-confirmation-payment"></strong></div>
            </div>
            <div style="padding: 14px; border: 1px dashed #2ed573; border-radius: 6px; background: rgba(46, 213, 115, 0.08);">
                <span style="display: block; color: #9ae6b4; font-size: 12px; text-transform: uppercase;">
                    Order Reference
                </span>
                <strong id="order-confirmation-reference" style="display: block; margin-top: 5px; font-size: 1.15rem; letter-spacing: 1px;"></strong>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                <a href="order_history.php" class="btn btn-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-receipt"></i> View order history
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeModal('buy-modal')">
                    Done
                </button>
            </div>
        </div>

    </div>

</div>


<!-- =====================================================
     4. SERVICES BOOKING MODAL
     ===================================================== -->

<div
    id="service-booking-modal"
    class="auth-modal"
    style="display: none;">

    <div
        class="auth-modal-content"
        style="max-width: 550px;">


        <span
            class="auth-close-btn"
            onclick="closeModal('service-booking-modal')">

            &times;

        </span>


        <div class="auth-modal-header">

            <h2>
                Book Service Appointment
            </h2>

            <p>
                Schedule your vehicle maintenance with Bencalo MotoWorks
            </p>

        </div>


        <div
            id="service-booking-modal-alert"
            class="modal-alert">
        </div>


        <form
            id="ajax-service-booking-form"
            action="process_booking.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="hidden"
                id="booking-service-id"
                name="services_id">


            <div
                style="
                    background: rgba(255,255,255,0.05);
                    padding: 12px 15px;
                    border-radius: 6px;
                    margin-bottom: 20px;
                    border: 1px solid rgba(255,255,255,0.1);
                ">

                <span
                    style="
                        font-size: 12px;
                        color: var(--text-muted);
                        display: block;
                        text-transform: uppercase;
                        letter-spacing: 0.5px;
                    ">

                    Selected Service

                </span>


                <span
                    id="booking-service-name"
                    style="
                        font-size: 16px;
                        font-weight: 600;
                        color: var(--primary-accent);
                    ">

                    Select a service

                </span>

            </div>


            <div
                style="
                    font-size: 14px;
                    font-weight: 600;
                    color: var(--text-main);
                    margin-bottom: 12px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    padding-bottom: 6px;
                ">

                1. Personal Information

            </div>


            <div class="auth-form-group">

                <label for="booking-fullname">
                    Full Name
                </label>


                <div class="auth-input-wrapper">

                    <i class="fa-solid fa-user"></i>


                    <input
                        type="text"
                        id="booking-fullname"
                        name="fullname"
                        placeholder="Enter your full name"
                        value="<?= htmlspecialchars(
                                    $modal_full_name,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        readonly
                        required>

                </div>

            </div>


            <div class="auth-form-group">

                <label for="booking-contact">
                    Contact Number
                </label>


                <div class="auth-input-wrapper">

                    <i class="fa-solid fa-phone"></i>


                    <input
                        type="tel"
                        id="booking-contact"
                        name="contact"
                        placeholder="e.g., 09123456789"
                        value="<?= htmlspecialchars(
                                    $user_phone,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                        readonly
                        required>

                </div>

            </div>


            <div
                style="
                    font-size: 14px;
                    font-weight: 600;
                    color: var(--text-main);
                    margin: 20px 0 12px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    padding-bottom: 6px;
                ">

                2. Vehicle Details

            </div>


            <div class="auth-form-group">

                <label for="booking-vehicle">
                    Vehicle Model / Type
                </label>


                <div class="auth-input-wrapper">

                    <i class="fa-solid fa-motorcycle"></i>


                    <input
                        type="text"
                        id="booking-vehicle"
                        name="vehicle"
                        placeholder="e.g., Yamaha NMAX / Honda Click"
                        required>

                </div>

            </div>


            <div
                style="
                    font-size: 14px;
                    font-weight: 600;
                    color: var(--text-main);
                    margin: 20px 0 12px;
                    border-bottom: 1px solid rgba(255,255,255,0.1);
                    padding-bottom: 6px;
                ">

                3. Schedule Preference

            </div>


            <div
                style="
                    display: grid;
                    grid-template-columns: 1fr 1fr;
                    gap: 15px;
                ">


                <div class="auth-form-group">

                    <label for="booking-date">
                        Preferred Date
                    </label>


                    <div class="auth-input-wrapper">

                        <i class="fa-solid fa-calendar-days"></i>


                        <input
                            type="date"
                            id="booking-date"
                            name="booking_date"
                            required
                            min="<?= date('Y-m-d') ?>">

                    </div>

                </div>


                <div class="auth-form-group">

                    <label for="booking-time">
                        Preferred Time Slot
                    </label>


                    <div class="auth-input-wrapper">

                        <i class="fa-solid fa-clock"></i>


                        <select
                            id="booking-time"
                            name="booking_time"
                            required
                            style="
                                width: 100%;
                                background: transparent;
                                border: none;
                                color: inherit;
                                outline: none;
                                padding-left: 35px;
                                font-family: inherit;
                                font-size: 14px;
                            ">


                            <option
                                value=""
                                disabled
                                selected
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                Select time slot

                            </option>


                            <option
                                value="09:00 AM - 10:30 AM"
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                09:00 AM - 10:30 AM

                            </option>


                            <option
                                value="10:30 AM - 12:00 PM"
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                10:30 AM - 12:00 PM

                            </option>


                            <option
                                value="01:00 PM - 02:30 PM"
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                01:00 PM - 02:30 PM

                            </option>


                            <option
                                value="02:30 PM - 04:00 PM"
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                02:30 PM - 04:00 PM

                            </option>


                            <option
                                value="04:00 PM - 05:30 PM"
                                style="
                                    background: #1e1e1e;
                                    color: #fff;
                                ">

                                04:00 PM - 05:30 PM

                            </option>


                        </select>

                    </div>

                </div>

            </div>


            <div
                class="auth-form-group"
                style="margin-top: 15px;">

                <label for="booking-notes">
                    Additional Notes / Special Requests (Optional)
                </label>


                <textarea
                    id="booking-notes"
                    name="notes"
                    placeholder="Describe any specific issues or custom requests..."
                    style="
                        width: 100%;
                        background: rgba(255,255,255,0.05);
                        border: 1px solid rgba(255,255,255,0.1);
                        border-radius: 6px;
                        padding: 10px;
                        color: inherit;
                        font-family: inherit;
                        font-size: 14px;
                        resize: vertical;
                        min-height: 80px;
                    "></textarea>

            </div>


            <button
                type="submit"
                class="auth-submit-btn"
                style="
                    width: 100%;
                    margin-top: 10px;
                ">

                Confirm Service Appointment

            </button>


        </form>

        <div
            id="booking-confirmation-panel"
            style="
                display: none;
                text-align: center;
                padding: 12px 4px 4px;
            "
        >
            <div style="font-size: 3rem; color: #2ed573;">
                <i class="fa-solid fa-circle-check"></i>
            </div>
            <h3 style="margin: 12px 0 8px;">Booking Confirmed</h3>
            <p style="color: var(--text-muted, #aaa); margin-bottom: 18px;">
                Your booking has been recorded. Keep this reference number for your appointment.
            </p>
            <div class="confirmation-detail-list">
                <div><span>Customer</span><strong id="booking-confirmation-customer"></strong></div>
                <div><span>Service</span><strong id="booking-confirmation-service"></strong></div>
                <div><span>Vehicle</span><strong id="booking-confirmation-vehicle"></strong></div>
                <div><span>Date & time</span><strong id="booking-confirmation-schedule"></strong></div>
                <div><span>Phone</span><strong id="booking-confirmation-phone"></strong></div>
                <div><span>Notes</span><strong id="booking-confirmation-notes"></strong></div>
            </div>
            <div style="padding: 14px; border: 1px dashed #2ed573; border-radius: 6px; background: rgba(46, 213, 115, 0.08);">
                <span style="display: block; color: #9ae6b4; font-size: 12px; text-transform: uppercase;">
                    Booking Reference
                </span>
                <strong id="booking-confirmation-reference" style="display: block; margin-top: 5px; font-size: 1.15rem; letter-spacing: 1px;"></strong>
            </div>
            <div style="display: flex; gap: 10px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
                <a href="appointment_history.php" class="btn btn-primary" style="text-decoration: none;">
                    <i class="fa-solid fa-calendar-check"></i> View bookings
                </a>
                <button type="button" class="btn btn-secondary" onclick="closeModal('service-booking-modal')">
                    Done
                </button>
            </div>
        </div>

    </div>

</div>


<!-- =====================================================
     5. LOGIN MODAL
     ===================================================== -->

<div
    id="login-modal"
    class="auth-modal"
    style="display: none;">

    <div class="auth-modal-content">


        <button
            type="button"
            class="auth-modal-close"
            onclick="closeModal('login-modal')">

            &times;

        </button>


        <h2 class="modal-heading-lg">
            Log In
        </h2>


        <div
            id="login-modal-alert"
            class="modal-alert">
        </div>


        <form
            id="ajax-login-form"
            class="auth-form"
            action="login.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="text"
                name="username"
                placeholder="Username or Email"
                required
                autocomplete="username"
                class="auth-input auth-input-padded">


            <div class="password-container mb-sm">


                <input
                    type="password"
                    id="login-password"
                    name="password"
                    placeholder="Password"
                    required
                    autocomplete="current-password"
                    class="auth-input auth-input-padded-r">


                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePasswordVisibility(
                        'login-password',
                        this
                    )">

                    <i class="fa-solid fa-eye"></i>

                </button>


            </div>


            <div class="auth-options">


                <label class="checkbox-label">

                    <input
                        type="checkbox"
                        name="remember">

                    Remember Me

                </label>


                <a
                    href="#"
                    onclick="
                        switchModal(
                            'login-modal',
                            'forgot-modal'
                        );
                        return false;
                    "
                    class="
                        auth-link
                        auth-link-primary
                    ">

                    Forgot Password?

                </a>


            </div>


            <button
                type="submit"
                class="
                    btn
                    btn-primary
                    btn-block
                    auth-btn
                    btn-full-width
                ">

                Log In

            </button>


        </form>


        <p class="auth-modal-footer">

            Don't have an account?


            <a
                href="#"
                onclick="
                    switchModal(
                        'login-modal',
                        'register-modal'
                    );
                    return false;
                "
                class="auth-link">

                Register

            </a>

        </p>


    </div>

</div>


<!-- =====================================================
     6. REGISTER MODAL
     ===================================================== -->

<div
    id="register-modal"
    class="auth-modal"
    style="display: none;">

    <div class="auth-modal-content">


        <button
            type="button"
            class="auth-modal-close"
            onclick="closeModal('register-modal')">

            &times;

        </button>


        <h2 class="modal-heading-lg">
            Create Account
        </h2>


        <div
            id="register-modal-alert"
            class="modal-alert">
        </div>


        <form
            id="ajax-register-form"
            class="auth-form"
            action="register.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="text"
                name="username"
                placeholder="Username"
                required
                autocomplete="username"
                class="auth-input auth-input-mb">


            <div class="form-row-2col">


                <input
                    type="text"
                    name="first_name"
                    placeholder="First Name"
                    required
                    class="
                        auth-input
                        auth-input-half
                    ">


                <input
                    type="text"
                    name="last_name"
                    placeholder="Last Name"
                    required
                    class="
                        auth-input
                        auth-input-half
                    ">


            </div>


            <input
                type="email"
                name="email"
                placeholder="Email Address"
                required
                autocomplete="email"
                class="auth-input auth-input-mb">


            <input
                type="text"
                name="phone"
                placeholder="Phone Number"
                required
                autocomplete="tel"
                class="auth-input auth-input-mb">


            <div class="password-container mb-md">


                <input
                    type="password"
                    id="register-password"
                    name="password"
                    placeholder="Password"
                    required
                    autocomplete="new-password"
                    class="auth-input auth-input-padded-r">


                <button
                    type="button"
                    class="toggle-password"
                    onclick="togglePasswordVisibility(
                        'register-password',
                        this
                    )">

                    <i class="fa-solid fa-eye"></i>

                </button>


            </div>


            <button
                type="submit"
                class="
                    btn
                    btn-primary
                    btn-block
                    auth-btn
                    btn-full-width
                ">

                Sign Up

            </button>


        </form>


        <p class="auth-modal-footer">

            Already have an account?


            <a
                href="#"
                onclick="
                    switchModal(
                        'register-modal',
                        'login-modal'
                    );
                    return false;
                "
                class="auth-link">

                Log In

            </a>

        </p>


    </div>

</div>


<!-- =====================================================
     7. FORGOT PASSWORD MODAL
     ===================================================== -->

<div
    id="forgot-modal"
    class="auth-modal"
    style="display: none;">

    <div class="auth-modal-content">


        <button
            type="button"
            class="auth-modal-close"
            onclick="closeModal('forgot-modal')">

            &times;

        </button>


        <h2 class="modal-heading">
            Reset Password
        </h2>


        <p class="modal-subtitle">
            Enter your email address to receive password reset instructions.
        </p>


        <div
            id="forgot-modal-alert"
            class="modal-alert">
        </div>


        <form
            id="ajax-forgot-form"
            class="auth-form"
            action="forgot_password.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="email"
                name="email"
                placeholder="Enter your email address"
                required
                class="
                    auth-input
                    auth-input-padded
                ">


            <button
                type="submit"
                class="
                    btn
                    btn-primary
                    btn-block
                    auth-btn
                    btn-full-width
                ">

                Send Reset Link

            </button>


        </form>


        <p class="auth-modal-footer">


            <a
                href="#"
                onclick="
                    switchModal(
                        'forgot-modal',
                        'login-modal'
                    );
                    return false;
                "
                class="auth-link">

                Back to Log In

            </a>


        </p>


    </div>

</div>


<!-- =====================================================
     8. CLAIM MODAL
     ===================================================== -->

<div
    id="claimModal"
    class="modal-overlay"
    style="display: none;">

    <div class="modal-content">


        <div class="modal-header">


            <h3>

                <i
                    class="fa-solid fa-clipboard-check"
                    style="color: #00d2d3;">
                </i>

                Claim Bundle:

                <span
                    id="modalBundleTitle">
                </span>

            </h3>


            <button
                type="button"
                class="modal-close-btn"
                onclick="closeClaimModal()">

                &times;

            </button>


        </div>


        <form
            action="process_booking.php"
            method="POST">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <input
                type="hidden"
                name="bundle_slug"
                id="modalBundleSlug">


            <div class="form-group">

                <label>
                    Full Name
                </label>


                <input
                    type="text"
                    value="<?= htmlspecialchars(
                                $modal_full_name,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    class="form-control"
                    readonly
                    >

            </div>


            <div class="form-group">

                <label>
                    Phone Number
                </label>


                <input
                    type="text"
                    value="<?= htmlspecialchars(
                                $user_phone,
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>"
                    class="form-control"
                    readonly
                    >

            </div>


            <div class="form-group">
                <label>Service</label>
                <select name="service_id" class="form-control" required>
                    <option value="">Select Service</option>
                    <?php foreach ($bookingServices as $bookingService): ?>
                        <option value="<?= (int) $bookingService['id'] ?>"><?= htmlspecialchars($bookingService['name'], ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Vehicle Model</label>
                <input type="text" name="vehicle_model" class="form-control" maxlength="255" required>
            </div>


            <div class="form-group">

                <label id="modalItemLabel">
                    Select Option
                </label>


                <select
                    name="selected_option"
                    id="modalItemOptions"
                    class="form-control"
                    required>
                </select>

            </div>

            <div class="form-group">
                <label>Notes (optional)</label>
                <textarea name="notes" class="form-control" rows="3"></textarea>
            </div>


            <div class="form-group">

                <label>
                    Preferred Appointment Date
                </label>


                <input
                    type="date"
                    name="appointment_date"
                    id="appointmentDateInput"
                    class="form-control"
                    required
                    min="<?= date('Y-m-d') ?>"
                    onclick="
                        this.showPicker &&
                        this.showPicker()
                    ">

            </div>


            <div class="form-group">

                <label>
                    Preferred Appointment Time Slot
                </label>


                <select
                    name="appointment_time"
                    class="form-control"
                    required>


                    <option
                        value=""
                        disabled
                        selected>

                        Select Time Slot

                    </option>


                    <option value="08:00 AM - 10:00 AM">
                        08:00 AM - 10:00 AM
                    </option>


                    <option value="10:00 AM - 12:00 PM">
                        10:00 AM - 12:00 PM
                    </option>


                    <option value="01:00 PM - 03:00 PM">
                        01:00 PM - 03:00 PM
                    </option>


                    <option value="03:00 PM - 05:00 PM">
                        03:00 PM - 05:00 PM
                    </option>


                </select>

            </div>


            <div class="modal-footer">


                <button
                    type="button"
                    class="btn-secondary-modal"
                    onclick="closeClaimModal()">

                    Cancel

                </button>


                <button
                    type="submit"
                    class="btn-primary-modal"
                    style="
                        background-color: var(--color-primary);
                        color: var(--color-text);
                        transition:
                            background-color 0.2s ease,
                            border-color 0.2s ease;
                    ">

                    Confirm & Claim Bundle

                </button>


            </div>


        </form>

    </div>

</div>


<!-- =====================================================
     9. CART QUANTITY MODAL
     ===================================================== -->

<div
    id="cart-modal"
    class="auth-modal">

    <div class="auth-modal-content">


        <span
            class="close-modal"
            onclick="closeModal('cart-modal')">

            &times;

        </span>


        <h3>
            Add to Cart
        </h3>


        <div
            id="cart-modal-alert"
            class="modal-alert">
        </div>


        <form
            id="ajax-cart-form"
            action="cart_action.php"
            method="POST">


            <input
                type="hidden"
                name="action"
                value="add">


            <input
                type="hidden"
                id="cart-product-id"
                name="product_id">


            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(
                            $_SESSION['csrf_token'],
                            ENT_QUOTES,
                            'UTF-8'
                        ) ?>">


            <div
                class="modal-product-info"
                style="margin-bottom: 15px;">


                <p>

                    <strong
                        id="cart-product-name">

                        Product Name

                    </strong>

                </p>


                <p
                    style="
                        color: #2e7d32;
                        font-weight: 600;
                    "
                    id="cart-product-price">

                    ₱0.00

                </p>


            </div>


            <div
                class="form-group"
                style="margin-bottom: 15px;">


                <label for="cart-quantity">
                    Quantity:
                </label>


                <input
                    type="number"
                    id="cart-quantity"
                    name="quantity"
                    class="form-control"
                    value="1"
                    min="1"
                    step="1"
                    oninput="validateCartStockLimit()"
                    required>


                <small
                    id="cart-stock-warning"
                    style="
                        color: #c62828;
                        display: none;
                        margin-top: 5px;
                    ">
                </small>


            </div>


            <button
                type="submit"
                class="btn btn-primary btn-block">

                Add to Cart

            </button>


        </form>

    </div>

</div>


<!-- =====================================================
     JAVASCRIPT CONTROLLER
     ===================================================== -->

<script>


    // =====================================================
    // GLOBAL AUTHENTICATION STATE
    // =====================================================

    let isLoggedIn =
        <?= (
            isset($_SESSION['user_id']) ||
            isset($_SESSION['user'])
        )
            ? 'true'
            : 'false'
        ?>;


    // =====================================================
    // OPEN MODAL
    // =====================================================

    function openModal(modalId) {

        const modal =
            document.getElementById(modalId);


        if (modal) {

            modal.style.display =
                'flex';

            modal.classList.add(
                'active'
            );

        } else {

            console.error(
                'Modal element not found for ID:',
                modalId
            );
        }
    }


    // =====================================================
    // CLOSE MODAL
    // =====================================================

    function closeModal(modalId) {

        const modal =
            document.getElementById(modalId);


        if (modal) {

            modal.style.display =
                'none';

            modal.classList.remove(
                'active'
            );
        }
    }


    // =====================================================
    // SWITCH MODAL
    // =====================================================

    function switchModal(
        fromId,
        toId
    ) {

        closeModal(fromId);

        openModal(toId);
    }


    // =====================================================
    // BUY MODAL
    // =====================================================

    function openBuyModal(
        productId,
        productName,
        productPrice,
        stockLimit = 0
    ) {


        if (!isLoggedIn) {

            openModal(
                'login-modal'
            );


            showModalAlert(
                'login-modal',
                'Please log in to make a purchase.',
                'error'
            );


            return;
        }


        const idInput =
            document.getElementById(
                'buy-product-id'
            );


        if (idInput) {

            idInput.value =
                productId;
        }


        const nameEl =
            document.getElementById(
                'buy-product-name'
            );


        if (nameEl) {

            nameEl.textContent =
                productName;
        }


        const priceEl =
            document.getElementById(
                'buy-product-price'
            );


        if (priceEl) {

            const numPrice =
                parseFloat(
                    productPrice
                ) || 0;


            priceEl.textContent =
                '₱' +
                numPrice.toFixed(2);
        }


        const qtyInput =
            document.getElementById(
                'buy-quantity'
            );


        if (qtyInput) {

            qtyInput.dataset.maxStock =
                stockLimit;


            qtyInput.max =
                stockLimit > 0
                    ? stockLimit
                    : '';


            qtyInput.min =
                1;


            qtyInput.value =
                1;
        }


        const warningEl =
            document.getElementById(
                'buy-stock-warning'
            );


        if (warningEl) {

            warningEl.style.display =
                'none';


            warningEl.textContent =
                '';
        }


        const alertBox =
            document.getElementById(
                'buy-modal-alert'
            );


        if (alertBox) {

            alertBox.style.display =
                'none';


            alertBox.textContent =
                '';
        }


        const submitBtn =
            document.querySelector(
                '#ajax-buy-form button[type="submit"]'
            );


        if (submitBtn) {

            submitBtn.disabled =
                false;


            submitBtn.textContent =
                'Place Order';
        }


        const buyForm =
            document.getElementById(
                'ajax-buy-form'
            );


        const orderConfirmation = document.getElementById(
            'order-confirmation-panel'
        );

        const buyProductSummary = document.getElementById(
            'buy-modal-product-summary'
        );

        if (buyForm) {

            buyForm.style.display = '';
            buyForm.dataset.submitting =
                'false';
        }

        if (orderConfirmation) {
            orderConfirmation.style.display = 'none';
        }

        if (buyProductSummary) {
            buyProductSummary.style.display = '';
        }


        openModal(
            'buy-modal'
        );
    }


    // =====================================================
    // BUY STOCK VALIDATION
    // =====================================================

    function validateStockLimit() {

        const qtyInput =
            document.getElementById(
                'buy-quantity'
            );


        const warningEl =
            document.getElementById(
                'buy-stock-warning'
            );


        const submitBtn =
            document.querySelector(
                '#ajax-buy-form button[type="submit"]'
            );


        if (
            !qtyInput ||
            !warningEl
        ) {

            return;
        }


        const maxStock =
            parseInt(
                qtyInput.dataset.maxStock
            ) || 0;


        const currentQty =
            parseInt(
                qtyInput.value
            ) || 0;


        if (
            maxStock > 0 &&
            currentQty > maxStock
        ) {

            warningEl.textContent =
                'Requested quantity exceeds available stock (' +
                maxStock +
                ' left).';


            warningEl.style.display =
                'block';


            if (submitBtn) {

                submitBtn.disabled =
                    true;
            }

        } else {

            warningEl.style.display =
                'none';


            if (submitBtn) {

                submitBtn.disabled =
                    false;
            }
        }
    }


    // =====================================================
    // CART MODAL
    // =====================================================

    function openCartModal(
        productId,
        productName,
        productPrice,
        stockLimit = 1
    ) {


        if (!isLoggedIn) {

            openModal(
                'login-modal'
            );


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


        const qtyInput =
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

            const numPrice =
                parseFloat(
                    productPrice
                ) || 0;


            priceField.textContent =
                '₱' +
                numPrice.toFixed(2);
        }


        if (qtyInput) {

            qtyInput.dataset.maxStock =
                stockLimit;


            qtyInput.max =
                stockLimit;


            qtyInput.min =
                1;


            qtyInput.value =
                1;
        }


        if (warning) {

            warning.style.display =
                'none';


            warning.textContent =
                '';
        }


        const alertBox =
            document.getElementById(
                'cart-modal-alert'
            );


        if (alertBox) {

            alertBox.style.display =
                'none';


            alertBox.textContent =
                '';
        }


        const cartForm =
            document.getElementById(
                'ajax-cart-form'
            );


        if (cartForm) {

            cartForm.dataset.submitting =
                'false';
        }


        const submitBtn =
            document.querySelector(
                '#ajax-cart-form button[type="submit"]'
            );


        if (submitBtn) {

            submitBtn.disabled =
                false;


            submitBtn.textContent =
                'Add to Cart';
        }


        openModal(
            'cart-modal'
        );
    }


    // =====================================================
    // CART STOCK VALIDATION
    // =====================================================

    function validateCartStockLimit() {

        const qtyInput =
            document.getElementById(
                'cart-quantity'
            );


        const warningEl =
            document.getElementById(
                'cart-stock-warning'
            );


        const submitBtn =
            document.querySelector(
                '#ajax-cart-form button[type="submit"]'
            );


        if (
            !qtyInput ||
            !warningEl
        ) {

            return;
        }


        const maxStock =
            parseInt(
                qtyInput.dataset.maxStock
            ) || 0;


        const currentQty =
            parseInt(
                qtyInput.value
            ) || 0;


        if (
            maxStock > 0 &&
            currentQty > maxStock
        ) {

            warningEl.textContent =
                'Requested quantity exceeds available stock (' +
                maxStock +
                ' left).';


            warningEl.style.display =
                'block';


            if (submitBtn) {

                submitBtn.disabled =
                    true;
            }

        } else {

            warningEl.style.display =
                'none';


            if (submitBtn) {

                submitBtn.disabled =
                    false;
            }
        }
    }


    // =====================================================
    // SERVICE BOOKING MODAL
    // =====================================================

    function openServiceBookingModal(
        serviceId,
        serviceName
    ) {


        if (!isLoggedIn) {

            openModal(
                'login-modal'
            );


            showModalAlert(
                'login-modal',
                'Please log in to book a service appointment.',
                'error'
            );


            return;
        }


        const serviceIdInput =
            document.getElementById(
                'booking-service-id'
            );


        const serviceNameSpan =
            document.getElementById(
                'booking-service-name'
            );


        if (serviceIdInput) {

            serviceIdInput.value =
                serviceId;
        }


        if (serviceNameSpan) {

            serviceNameSpan.textContent =
                serviceName;
        }

        const bookingForm = document.getElementById(
            'ajax-service-booking-form'
        );

        const bookingConfirmation = document.getElementById(
            'booking-confirmation-panel'
        );

        const bookingAlert = document.getElementById(
            'service-booking-modal-alert'
        );

        if (bookingForm) {
            bookingForm.style.display = '';
            bookingForm.dataset.submitting = 'false';

            const vehicleInput = document.getElementById('booking-vehicle');
            const dateInput = document.getElementById('booking-date');
            const timeInput = document.getElementById('booking-time');
            const notesInput = document.getElementById('booking-notes');

            if (vehicleInput) vehicleInput.value = '';
            if (dateInput) dateInput.value = '';
            if (timeInput) timeInput.selectedIndex = 0;
            if (notesInput) notesInput.value = '';

            if (serviceIdInput) {
                serviceIdInput.value = serviceId;
            }
        }

        if (bookingConfirmation) {
            bookingConfirmation.style.display = 'none';
        }

        if (bookingAlert) {
            bookingAlert.style.display = 'none';
            bookingAlert.textContent = '';
        }


        openModal(
            'service-booking-modal'
        );
    }


    // =====================================================
    // MODAL ALERT
    // =====================================================

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


            alertBox.style.display =
                'block';


            if (type === 'error') {

                alertBox.style.color =
                    '#ff6b6b';

            } else {

                alertBox.style.color =
                    '#2ed573';
            }
        }
    }


    // =====================================================
    // CLAIM MODAL
    // =====================================================

    function closeClaimModal() {

        closeModal(
            'claimModal'
        );
    }


    // =====================================================
    // PASSWORD VISIBILITY
    // =====================================================

    function togglePasswordVisibility(
        fieldId,
        btn
    ) {


        const input =
            document.getElementById(
                fieldId
            );


        if (!input) {

            return;
        }


        const isPassword =
            input.type === 'password';


        input.type =
            isPassword
                ? 'text'
                : 'password';


        if (btn) {

            btn.innerHTML =
                isPassword
                    ? '<i class="fa-solid fa-eye-slash"></i>'
                    : '<i class="fa-solid fa-eye"></i>';
        }
    }


    // =====================================================
    // AJAX FORM SUBMISSION
    // =====================================================

    window.handleFormSubmit =
        function(
            formId,
            targetUrl,
            modalId
        ) {


            const form =
                document.getElementById(
                    formId
                );


            if (
                !form ||
                form.dataset.listenerAttached ===
                'true'
            ) {

                return;
            }


            form.dataset.listenerAttached =
                'true';


            form.addEventListener(
                'submit',
                function(e) {


                    e.preventDefault();


                    // =====================================
                    // DOUBLE SUBMISSION LOCK
                    // =====================================

                    if (
                        form.dataset.submitting ===
                        'true'
                    ) {

                        console.log(
                            'Duplicate submission blocked:',
                            formId
                        );


                        return;
                    }


                    form.dataset.submitting =
                        'true';


                    // =====================================
                    // SUBMIT BUTTON
                    // =====================================

                    const submitBtn =
                        form.querySelector(
                            'button[type="submit"]'
                        );


                    if (submitBtn) {

                        submitBtn.disabled =
                            true;


                        if (
                            formId ===
                            'ajax-buy-form'
                        ) {

                            submitBtn.textContent =
                                'Processing...';

                        } else if (
                            formId ===
                            'ajax-cart-form'
                        ) {

                            submitBtn.textContent =
                                'Adding...';

                        } else {

                            submitBtn.textContent =
                                'Please wait...';
                        }
                    }


                    // =====================================
                    // FORM DATA
                    // =====================================

                    const formData =
                        new FormData(
                            form
                        );


                    // =====================================
                    // SEND REQUEST
                    // =====================================

                    fetch(
                        targetUrl,
                        {
                            method: 'POST',
                            body: formData
                        }
                    )


                    // =====================================
                    // RESPONSE
                    // =====================================

                    .then(
                        async response => {

                            const text =
                                await response.text();


                            try {

                                return JSON.parse(
                                    text
                                );

                            } catch (err) {

                                throw new Error(
                                    text.trim() ||
                                    'Server returned invalid JSON.'
                                );
                            }

                        }
                    )


                    // =====================================
                    // RESULT
                    // =====================================

                    .then(
                        data => {


                            // =================================
                            // SUCCESS
                            // =================================

                            if (data.success) {


                                showModalAlert(
                                    modalId,
                                    data.message ||
                                    'Success!',
                                    'success'
                                );


                                // =================================
                                // CART SUCCESS
                                // =================================

                                if (
                                    formId ===
                                    'ajax-cart-form'
                                ) {


                                    const cartBadge =
                                        document.getElementById(
                                            'cart-count-badge'
                                        );


                                    if (
                                        cartBadge &&
                                        data.cart_count !==
                                        undefined
                                    ) {

                                        cartBadge.textContent =
                                            data.cart_count;


                                        if (
                                            parseInt(
                                                data.cart_count
                                            ) > 0
                                        ) {

                                            cartBadge.style.display =
                                                'inline-flex';

                                        } else {

                                            cartBadge.style.display =
                                                'none';
                                        }
                                    }


                                    setTimeout(
                                        () => {


                                            closeModal(
                                                modalId
                                            );


                                            form.reset();


                                            form.dataset.submitting =
                                                'false';


                                            if (submitBtn) {

                                                submitBtn.disabled =
                                                    false;


                                                submitBtn.textContent =
                                                    'Add to Cart';
                                            }

                                        },
                                        1000
                                    );


                                    return;
                                }

                                if (
                                    formId ===
                                    'ajax-service-booking-form'
                                ) {
                                    const confirmationPanel =
                                        document.getElementById(
                                            'booking-confirmation-panel'
                                        );

                                    const confirmationReference =
                                        document.getElementById(
                                            'booking-confirmation-reference'
                                        );

                                    const bookingAlert =
                                        document.getElementById(
                                            modalId + '-alert'
                                        );

                                    if (confirmationReference) {
                                        confirmationReference.textContent =
                                            data.booking_reference || 'Pending';
                                    }

                                    document.getElementById(
                                        'booking-confirmation-customer'
                                    ).textContent = data.customer_name || '';
                                    document.getElementById(
                                        'booking-confirmation-service'
                                    ).textContent = data.service_name || '';
                                    document.getElementById(
                                        'booking-confirmation-vehicle'
                                    ).textContent = data.vehicle || '';
                                    document.getElementById(
                                        'booking-confirmation-schedule'
                                    ).textContent =
                                        (data.booking_date || '') +
                                        ' / ' +
                                        (data.booking_time || '');
                                    document.getElementById(
                                        'booking-confirmation-phone'
                                    ).textContent = data.phone || '';
                                    document.getElementById(
                                        'booking-confirmation-notes'
                                    ).textContent = data.notes || 'None';

                                    if (bookingAlert) {
                                        bookingAlert.style.display = 'none';
                                    }

                                    form.style.display = 'none';

                                    if (confirmationPanel) {
                                        confirmationPanel.style.display = 'block';
                                    }

                                    form.dataset.submitting = 'false';
                                    return;
                                }


                                // =================================
                                // LOGIN SUCCESS
                                // =================================

                                if (
                                    formId ===
                                    'ajax-login-form'
                                ) {


                                    isLoggedIn =
                                        true;

                                    if (data.csrf_token) {
                                        document
                                            .querySelectorAll('input[name="csrf_token"]')
                                            .forEach(input => {
                                                input.value = data.csrf_token;
                                            });
                                    }

                                    if (
                                        data.role === 'admin' ||
                                        data.role === 'staff'
                                    ) {

                                        window.location.href =
                                            data.redirect;

                                        return;
                                    }


                                    const displayName =
                                        data.user_name ||
                                        formData.get(
                                            'username'
                                        );

                                    const userDropdown =
                                        document.getElementById(
                                            'nav-user-dropdown'
                                        );
                                    const guestButtons =
                                        document.getElementById(
                                            'nav-guest-buttons'
                                        );

                                    if (userDropdown) {
                                        userDropdown.style.display = 'block';
                                    }
                                    if (guestButtons) {
                                        guestButtons.style.display = 'none';
                                    }


                                    document
                                        .querySelectorAll(
                                            '#nav-user-name,' +
                                            ' .user-display-name,' +
                                            ' .nav-username'
                                        )
                                        .forEach(
                                            el => {

                                                el.textContent =
                                                    displayName;
                                            }
                                        );


                                    setTimeout(
                                        () => {


                                            closeModal(
                                                modalId
                                            );


                                            form.reset();


                                            const alertBox =
                                                document.getElementById(
                                                    modalId +
                                                    '-alert'
                                                );


                                            if (alertBox) {

                                                alertBox.style.display =
                                                    'none';
                                            }


                                            form.dataset.submitting =
                                                'false';


                                            if (submitBtn) {

                                                submitBtn.disabled =
                                                    false;


                                                submitBtn.textContent =
                                                    'Log In';
                                            }

                                        },
                                        500
                                    );


                                    return;
                                }


                                // =================================
                                // BUY SUCCESS
                                // =================================

                                if (
                                    formId ===
                                    'ajax-buy-form'
                                ) {

                                    const paymentReference =
                                        data.payment_reference;

                                    const paymentMethod =
                                        data.payment_method;

                                    const orderConfirmation =
                                        document.getElementById(
                                            'order-confirmation-panel'
                                        );
                                    const orderConfirmationReference =
                                        document.getElementById(
                                            'order-confirmation-reference'
                                        );
                                    const buyProductSummary =
                                        document.getElementById(
                                            'buy-modal-product-summary'
                                        );
                                    const buyAlert =
                                        document.getElementById(
                                            modalId + '-alert'
                                        );

                                    if (orderConfirmationReference) {
                                        orderConfirmationReference.textContent =
                                            paymentReference || 'Pending';
                                    }

                                    const orderItems =
                                        (data.items || [])
                                            .map(item =>
                                                item.name +
                                                ' (x' +
                                                item.quantity +
                                                ')'
                                            )
                                            .join(', ');

                                    document.getElementById(
                                        'order-confirmation-customer'
                                    ).textContent = data.customer_name || '';
                                    document.getElementById(
                                        'order-confirmation-item'
                                    ).textContent = orderItems;
                                    document.getElementById(
                                        'order-confirmation-phone'
                                    ).textContent = data.phone || '';
                                    document.getElementById(
                                        'order-confirmation-address'
                                    ).textContent = data.address || '';
                                    document.getElementById(
                                        'order-confirmation-payment'
                                    ).textContent = paymentMethod || '';

                                    if (
                                        paymentReference &&
                                        paymentMethod
                                    ) {
                                        showModalAlert(
                                            modalId,
                                            'Order placed successfully! Reference number: ' +
                                            paymentReference +
                                            '. Payment method: ' +
                                            paymentMethod +
                                            '. Receipt history is available in Order History.',
                                            'success'
                                        );
                                    }


                                    if (submitBtn) {

                                        submitBtn.disabled =
                                            true;


                                        submitBtn.textContent =
                                            'Order Placed';
                                    }

                                    form.style.display = 'none';

                                    if (buyProductSummary) {
                                        buyProductSummary.style.display = 'none';
                                    }

                                    if (buyAlert) {
                                        buyAlert.style.display = 'none';
                                    }

                                    if (orderConfirmation) {
                                        orderConfirmation.style.display = 'block';
                                    }


                                    setTimeout(
                                        () => {

                                            window.location.reload();

                                        },
                                        paymentReference ? 5000 : 1000
                                    );


                                    return;
                                }


                                // =================================
                                // OTHER FORMS
                                // =================================

                                setTimeout(
                                    () => {


                                        if (
                                            data.redirect
                                        ) {

                                            window.location.href =
                                                data.redirect;

                                            return;
                                        }


                                        closeModal(
                                            modalId
                                        );


                                        form.dataset.submitting =
                                            'false';


                                        if (submitBtn) {

                                            submitBtn.disabled =
                                                false;


                                            submitBtn.textContent =
                                                'Submit';
                                        }

                                    },
                                    1200
                                );

                            }


                            // =================================
                            // SERVER ERROR
                            // =================================

                            else {


                                showModalAlert(
                                    modalId,
                                    data.message ||
                                    'An error occurred.',
                                    'error'
                                );


                                form.dataset.submitting =
                                    'false';


                                if (submitBtn) {

                                    submitBtn.disabled =
                                        false;


                                    if (
                                        formId ===
                                        'ajax-buy-form'
                                    ) {

                                        submitBtn.textContent =
                                            'Place Order';

                                    } else if (
                                        formId ===
                                        'ajax-cart-form'
                                    ) {

                                        submitBtn.textContent =
                                            'Add to Cart';

                                    } else if (
                                        formId ===
                                        'ajax-login-form'
                                    ) {

                                        submitBtn.textContent =
                                            'Log In';

                                    } else if (
                                        formId ===
                                        'ajax-service-booking-form'
                                    ) {

                                        submitBtn.textContent =
                                            'Confirm Service Appointment';

                                    } else {

                                        submitBtn.textContent =
                                            'Submit';
                                    }
                                }
                            }

                        }
                    )


                    // =====================================
                    // REQUEST ERROR
                    // =====================================

                    .catch(
                        error => {


                            console.error(
                                'AJAX Error:',
                                error
                            );


                            showModalAlert(
                                modalId,
                                'Request Failed: ' +
                                error.message,
                                'error'
                            );


                            form.dataset.submitting =
                                'false';


                            if (submitBtn) {

                                submitBtn.disabled =
                                    false;


                                if (
                                    formId ===
                                    'ajax-buy-form'
                                ) {

                                    submitBtn.textContent =
                                        'Place Order';

                                } else if (
                                    formId ===
                                    'ajax-cart-form'
                                ) {

                                    submitBtn.textContent =
                                        'Add to Cart';

                                } else if (
                                    formId ===
                                    'ajax-login-form'
                                ) {

                                    submitBtn.textContent =
                                        'Log In';

                                } else if (
                                    formId ===
                                    'ajax-service-booking-form'
                                ) {

                                    submitBtn.textContent =
                                        'Confirm Service Appointment';

                                } else {

                                    submitBtn.textContent =
                                        'Submit';
                                }
                            }

                        }
                    );

                }
            );
        };


    // =====================================================
    // DYNAMIC VIEW MODAL
    // =====================================================

    function openViewModal(id) {


        openModal(
            'viewModal'
        );


        const bodyEl =
            document.getElementById(
                'viewModalBody'
            );


        if (bodyEl) {

            bodyEl.innerHTML =
                '<p>Loading details...</p>';
        }


        fetch(
            'get_claim.php?id=' +
            encodeURIComponent(id) +
            '&mode=view'
        )


        .then(
            res => res.text()
        )


        .then(
            html => {

                if (bodyEl) {

                    bodyEl.innerHTML =
                        html;
                }

            }
        )


        .catch(
            () => {

                if (bodyEl) {

                    bodyEl.innerHTML =
                        '<p style="color: red;">Error loading details.</p>';
                }
            }
        );
    }


    // =====================================================
    // DYNAMIC EDIT MODAL
    // =====================================================

    function openEditModal(id) {


        openModal(
            'editModal'
        );


        const bodyEl =
            document.getElementById(
                'editModalBody'
            );


        if (bodyEl) {

            bodyEl.innerHTML =
                '<p>Loading form...</p>';
        }


        fetch(
            'get_claim.php?id=' +
            encodeURIComponent(id) +
            '&mode=edit'
        )


        .then(
            res => res.text()
        )


        .then(
            html => {

                if (bodyEl) {

                    bodyEl.innerHTML =
                        html;
                }

            }
        )


        .catch(
            () => {

                if (bodyEl) {

                    bodyEl.innerHTML =
                        '<p style="color: red;">Error loading form.</p>';
                }
            }
        );
    }


    // =====================================================
    // DOM CONTENT LOADED
    // =====================================================

    document.addEventListener(
        'DOMContentLoaded',
        () => {


            // ---------------------------------------------
            // LOGIN
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-login-form',
                'login.php',
                'login-modal'
            );


            // ---------------------------------------------
            // REGISTER
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-register-form',
                'register.php',
                'register-modal'
            );


            // ---------------------------------------------
            // FORGOT PASSWORD
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-forgot-form',
                'forgot_password.php',
                'forgot-modal'
            );


            // ---------------------------------------------
            // SERVICE BOOKING
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-service-booking-form',
                'process_booking.php',
                'service-booking-modal'
            );


            // ---------------------------------------------
            // CART
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-cart-form',
                'cart_action.php',
                'cart-modal'
            );


            // ---------------------------------------------
            // BUY NOW / CHECKOUT
            // ---------------------------------------------

            handleFormSubmit(
                'ajax-buy-form',
                'process_checkout.php',
                'buy-modal'
            );


            // ---------------------------------------------
            // ESCAPE KEY
            // ---------------------------------------------

            document.addEventListener(
                'keydown',
                (e) => {


                    if (
                        e.key === 'Escape'
                    ) {


                        const activeModal =
                            document.querySelector(
                                '.modal-overlay.active,' +
                                ' .auth-modal.active'
                            );


                        if (activeModal) {

                            closeModal(
                                activeModal.id
                            );
                        }
                    }

                }
            );


            // ---------------------------------------------
            // BACKGROUND CLICK
            // ---------------------------------------------

            document
                .querySelectorAll(
                    '.modal-overlay,' +
                    ' .auth-modal'
                )
                .forEach(
                    modal => {


                        modal.addEventListener(
                            'click',
                            (e) => {


                                if (
                                    e.target ===
                                    modal
                                ) {


                                    closeModal(
                                        modal.id
                                    );
                                }

                            }
                        );

                    }
                );


            // ---------------------------------------------
            // BUY QUANTITY INPUT
            // ---------------------------------------------

            const buyQuantity =
                document.getElementById(
                    'buy-quantity'
                );


            if (
                buyQuantity &&
                !buyQuantity.dataset.validationAttached
            ) {


                buyQuantity.dataset.validationAttached =
                    'true';


                buyQuantity.addEventListener(
                    'input',
                    validateStockLimit
                );

            }

        }
    );

</script>