<?php

require_once __DIR__ . '/includes/auth.php';
generateCSRFToken();

/*
|--------------------------------------------------------------------------
| 1. LOAD DATABASE CONFIG
|--------------------------------------------------------------------------
*/

if (file_exists('config.php')) {
    require_once 'config.php';
} elseif (file_exists('includes/config.php')) {
    require_once 'includes/config.php';
}


/*
|--------------------------------------------------------------------------
| 2. LOAD FUNCTIONS & AUTH HELPERS
|--------------------------------------------------------------------------
*/

if (file_exists('includes/functions.php')) {
    require_once 'includes/functions.php';
}

if (file_exists('includes/auth.php')) {
    require_once 'includes/auth.php';
}


/*
|--------------------------------------------------------------------------
| 3. NORMALIZE DATABASE CONNECTION
|--------------------------------------------------------------------------
*/

if (!isset($pdo) && isset($conn)) {
    $pdo = $conn;
}


/*
|--------------------------------------------------------------------------
| 4. RESOLVE LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$user_id = $_SESSION['user_id']
    ?? $_SESSION['id']
    ?? $_SESSION['user']['id']
    ?? $_SESSION['user_id_pk']
    ?? null;

if (!$user_id) {
    header('Location: login.php');
    exit();
}
if (isAdmin((int) $user_id)) {
    header('Location: admin/admin_appointment.php');
    exit();
}
if (isStaff((int) $user_id)) {
    header('Location: staff/staff_index.php');
    exit();
}
if (!hasRole((int) $user_id, 'customer')) {
    header('Location: login.php');
    exit();
}

$feedback = '';


/*
|--------------------------------------------------------------------------
| 5. HANDLE CRUD POST ACTIONS
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['crud_action']) &&
    isset($pdo)
) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        header('Location: appointment_history.php?msg=error');
        exit();
    }

    $action = $_POST['crud_action'];


    /*
    |--------------------------------------------------------------------------
    | UPDATE APPOINTMENT
    |--------------------------------------------------------------------------
    */

    if ($action === 'update') {

        $booking_id = filter_input(
            INPUT_POST,
            'booking_id',
            FILTER_VALIDATE_INT
        );

        $service_id = filter_input(
            INPUT_POST,
            'service_id',
            FILTER_VALIDATE_INT
        );

        $vehicle_model =
            trim($_POST['vehicle_model'] ?? '');

        $booking_date =
            trim($_POST['booking_date'] ?? '');

        $booking_time =
            trim($_POST['booking_time'] ?? '');

        $notes =
            trim($_POST['notes'] ?? '');

        if (!$booking_id || !$service_id || $vehicle_model === '' || mb_strlen($vehicle_model) > 255 || mb_strlen($notes) > 2000) {
            header('Location: appointment_history.php?msg=error');
            exit();
        }


        try {

            /*
            |--------------------------------------------------------------------------
            | Customer contact is read from the users profile.
            |--------------------------------------------------------------------------
            */

            $stmt = $pdo->prepare("
                UPDATE service_booking
                SET serviceId = ?, bookingVehicleModel = ?, bookingDate = ?, bookingTime = ?, bookingNotes = ?
                WHERE bookingId = ? AND userId = ?
                  AND COALESCE(LOWER(TRIM(bookingStatus)), '') <> 'completed'
            ");

            $stmt->execute([
                $service_id,
                $vehicle_model,
                $booking_date,
                $booking_time,
                $notes,
                $booking_id,
                $user_id
            ]);

            if ($stmt->rowCount() > 0) {
                $feedback = 'updated';
            } else {
                $statusStmt = $pdo->prepare('SELECT bookingStatus FROM service_booking WHERE bookingId = ? AND userId = ?');
                $statusStmt->execute([$booking_id, $user_id]);
                $currentStatus = $statusStmt->fetchColumn();
                $feedback = $currentStatus !== false && strtolower(trim((string) $currentStatus)) === 'completed'
                    ? 'completed'
                    : ($currentStatus === false ? 'error' : 'updated');
            }

        } catch (PDOException $e) {

            $feedback = 'error';
        }
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE / CANCEL APPOINTMENT
    |--------------------------------------------------------------------------
    */

    elseif ($action === 'delete') {

        $booking_id = filter_input(
            INPUT_POST,
            'booking_id',
            FILTER_VALIDATE_INT
        );

        try {

            $stmt = $pdo->prepare("
                DELETE FROM service_booking WHERE bookingId = ? AND userId = ?
            ");

            $stmt->execute([
                $booking_id,
                $user_id
            ]);

            $feedback = 'deleted';

        } catch (PDOException $e) {

            $feedback = 'error';
        }
    }


    header(
        "Location: appointment_history.php?msg=" .
        urlencode($feedback)
    );

    exit();
}


/*
|--------------------------------------------------------------------------
| 6. FETCH USER APPOINTMENTS
|--------------------------------------------------------------------------
|
| Directly joins:
|
| service_booking.serviceId
|            ↓
|       service.serviceId
|
| This prevents N/A when the service relationship is valid.
|--------------------------------------------------------------------------
*/

$appointments = [];
$services_list = [];

if (isset($pdo)) {

    try {

        /*
        |--------------------------------------------------------------------------
        | Fetch appointments with service name and price
        |--------------------------------------------------------------------------
        */

        $stmt = $pdo->prepare("
            SELECT sb.bookingId as id, sb.bookingId, sb.userId as user_id, sb.userId, sb.serviceId as service_id, sb.serviceId as services_id, sb.serviceId, sb.bookingVehicleModel as vehicle_model, sb.bookingDate as booking_date, sb.bookingTime as booking_time, sb.bookingBundleSlug as bundle_slug, sb.bookingBundleOption as bundle_option, sb.bookingStatus as status, sb.bookingNotes as notes, sb.bookingCreatedAt as created_at, u.userPhone as contact_number, NULL as booking_reference, s.serviceName as service_name, s.servicePrice as service_price FROM service_booking sb INNER JOIN service s ON sb.serviceId = s.serviceId INNER JOIN user u ON sb.userId = u.userId WHERE sb.userId = ? ORDER BY sb.bookingId DESC
        ");

        $stmt->execute([
            $user_id
        ]);

        $appointments =
            $stmt->fetchAll(PDO::FETCH_ASSOC);


        /*
        |--------------------------------------------------------------------------
        | Fetch available services for Edit dropdown
        |--------------------------------------------------------------------------
        */

        $stmtServices = $pdo->query("
            SELECT serviceId as id, serviceName as name, servicePrice as price, serviceId, serviceName, servicePrice FROM service
            WHERE serviceActive = 1
            ORDER BY serviceId ASC
        ");

        $services_list =
            $stmtServices->fetchAll(PDO::FETCH_ASSOC);

    } catch (PDOException $e) {

        $appointments = [];
        $services_list = [];
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
        Appointment History - Bencalo Motoworks
    </title>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/style.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/components.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/layout.css"
    >

    <link
        rel="stylesheet"
        href="styles/index/modals.css"
    >

    <link
        rel="stylesheet"
        href="styles/appointment_history.css"
    >

</head>


<body>


<?php include_once 'components/header.php'; ?>


<main class="appointment-history-container container">

    <div class="section-title">

        <h2>
            Appointment History
        </h2>

    </div>


    <?php if (isset($_GET['msg'])): ?>

        <div class="alert-toast">

            <?php

            if ($_GET['msg'] === 'updated') {
                echo "Appointment updated successfully!";
            }

            if ($_GET['msg'] === 'completed') {
                echo "Completed appointments cannot be edited.";
            }

            if ($_GET['msg'] === 'deleted') {
                echo "Appointment cancelled/deleted!";
            }

            if ($_GET['msg'] === 'error') {
                echo "Unable to process the appointment.";
            }

            ?>

        </div>

    <?php endif; ?>


    <?php if (empty($appointments)): ?>

        <div class="empty-history-card">

            <p>
                No appointments recorded yet.
            </p>

            <a
                href="services.php"
                class="btn-buy-now"
            >
                Book a Service
            </a>

        </div>

    <?php else: ?>

        <div class="appointment-table-wrapper">

            <table class="appointment-table">

                <thead>

                    <tr>

                        <th
                            style="
                                width: 50px;
                                text-align: center;
                            "
                        >
                            #
                        </th>

                        <th>
                            Service
                        </th>

                        <th>
                            Vehicle
                        </th>

                        <th>
                            Date & Time
                        </th>

                        <th>
                            Contact
                        </th>

                        <th>
                            Price
                        </th>

                        <th>
                            Status
                        </th>

                        <th>
                            <span
                                style="
                                    position: relative;
                                    left: -35px;
                                "
                            >
                                Actions
                            </span>
                        </th>

                    </tr>

                </thead>


                <tbody>

                <?php

                $counter = 1;

                foreach ($appointments as $app):

                    /*
                    |--------------------------------------------------------------------------
                    | SERVICE INFORMATION
                    |--------------------------------------------------------------------------
                    */

                    $serviceName =
                        $app['service_name']
                        ?? 'Service N/A';

                    $servicePrice =
                        (float)(
                            $app['service_price']
                            ?? 0
                        );

                    $servicesId =
                        (int)(
                            $app['services_id']
                            ?? 0
                        );

                    $status =
                        $app['status']
                        ?? 'Pending';


                    /*
                    |--------------------------------------------------------------------------
                    | DATA FOR MODALS
                    |--------------------------------------------------------------------------
                    */

                    $app_data = [

                        'id' =>
                            (int)$app['id'],

                        'services_id' =>
                            $servicesId,

                        'service_name' =>
                            $serviceName,

                        'service_price' =>
                            number_format(
                                $servicePrice,
                                2
                            ),

                        'vehicle_model' =>
                            $app['vehicle_model']
                            ?? '',

                        'booking_date' =>
                            $app['booking_date']
                            ?? '',

                        'booking_time' =>
                            $app['booking_time']
                            ?? '',

                        'contact_number' =>
                            $app['contact_number']
                            ?? '',

                        'notes' =>
                            $app['notes']
                            ?? '',

                        'booking_reference' =>
                            $app['booking_reference']
                            ?? '',

                        'status' =>
                            $status
                    ];

                ?>

                    <tr>



                        <th>
                            Reference
                        </th>
                        <!-- NUMBER -->

                        <td
                            style="
                                text-align: center;
                                color: var(
                                    --text-muted,
                                    #8b949e
                                );
                            "
                        >

                            <?= $counter++ ?>

                        </td>


                        <!-- SERVICE -->

                        <td>

                            <strong>

                                <?= htmlspecialchars(
                                    $serviceName,
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </strong>

                        </td>


                        <!-- VEHICLE -->

                        <td>

                            <?= htmlspecialchars(
                                $app['vehicle_model']
                                ?? 'N/A',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- DATE & TIME -->

                        <td>

                            <div>

                                <?php

                                if (
                                    !empty(
                                        $app['booking_date']
                                    )
                                ) {

                                    $timestamp =
                                        strtotime(
                                            $app['booking_date']
                                        );

                                    if ($timestamp !== false) {

                                        echo date(
                                            'F j, Y',
                                            $timestamp
                                        );

                                    } else {

                                        echo htmlspecialchars(
                                            $app['booking_date'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        );
                                    }

                                } else {

                                    echo 'N/A';
                                }

                                ?>

                            </div>

                            <small
                                style="
                                    color: #8b949e;
                                "
                            >

                                <?= htmlspecialchars(
                                    $app['booking_time']
                                    ?? '',
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </small>

                        </td>


                        <!-- CONTACT -->

                        <td>

                            <?= htmlspecialchars(
                                $app['contact_number']
                                ?? 'N/A',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </td>


                        <!-- PRICE -->

                        <td>

                            ₱<?= number_format(
                                $servicePrice,
                                2
                            ) ?>

                        </td>


                        <!-- STATUS -->

                        <td>

                            <span
                                class="badge-status-<?= htmlspecialchars(
                                    strtolower($status),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>"
                            >

                                <?= htmlspecialchars(
                                    ucfirst($status),
                                    ENT_QUOTES,
                                    'UTF-8'
                                ) ?>

                            </span>

                        </td>


                        <!-- ACTIONS -->

                        <td>
                            <?= htmlspecialchars(
                                $app['booking_reference'] ?? 'N/A',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </td>

                        <td>

                            <div
                                class="table-actions"
                                style="
                                    justify-content: flex-end;
                                "
                            >


                                <!-- VIEW -->

                                <button
                                    type="button"
                                    class="btn-row-action btn-view"
                                    onclick='openViewModal(
                                        <?= json_encode(
                                            $app_data,
                                            JSON_HEX_TAG |
                                            JSON_HEX_APOS |
                                            JSON_HEX_AMP |
                                            JSON_HEX_QUOT
                                        ) ?>
                                    )'
                                >

                                    <i class="fas fa-eye"></i>

                                    View

                                </button>


                                <?php
                                /*
                                |--------------------------------------------------------------------------
                                | EDIT + CANCEL ONLY FOR PENDING
                                |--------------------------------------------------------------------------
                                */
                                ?>

                                <?php if (
                                    strtolower($status)
                                    === 'pending'
                                ): ?>


                                    <!-- EDIT -->

                                    <button
                                        type="button"
                                        class="btn-row-action btn-edit"
                                        onclick='openEditModal(
                                            <?= json_encode(
                                                $app_data,
                                                JSON_HEX_TAG |
                                                JSON_HEX_APOS |
                                                JSON_HEX_AMP |
                                                JSON_HEX_QUOT
                                            ) ?>
                                        )'
                                    >

                                        <i class="fas fa-edit"></i>

                                        Edit

                                    </button>


                                    <!-- CANCEL -->

                                    <form
                                        method="POST"
                                        style="display:inline;"
                                        onsubmit="
                                            return confirm(
                                                'Are you sure you want to cancel this booking?'
                                            );
                                        "
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="crud_action"
                                            value="delete"
                                        >

                                        <input
                                            type="hidden"
                                            name="booking_id"
                                            value="<?= (int)$app['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-row-action btn-delete"
                                        >

                                            <i class="fas fa-trash"></i>

                                            Cancel

                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </td>

                    </tr>

                <?php endforeach; ?>

                </tbody>

            </table>

        </div>

    <?php endif; ?>

</main>


<!-- =========================================================
     VIEW MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="viewModal"
>

    <div class="crud-modal-content">


        <div class="crud-modal-header">

            <h3>
                Appointment Details
            </h3>

            <button
                type="button"
                class="close-modal-btn"
                onclick="closeModal('viewModal')"
            >

                &times;

            </button>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Service
            </span>

            <span
                class="detail-value highlight"
                id="view_service_name"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Status
            </span>

            <span id="view_status_badge">
                -
            </span>

        </div>

        <div class="view-detail-group">

            <span class="detail-label">
                Booking Reference
            </span>

            <span
                class="detail-value highlight"
                id="view_booking_reference"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Vehicle Model
            </span>

            <span
                class="detail-value"
                id="view_vehicle_model"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Booking Date & Time
            </span>

            <span
                class="detail-value"
                id="view_date_time"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Contact Number
            </span>

            <span
                class="detail-value"
                id="view_contact_number"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Total Price
            </span>

            <span
                class="detail-value highlight"
                id="view_price"
            >
                -
            </span>

        </div>


        <div class="view-detail-group">

            <span class="detail-label">
                Notes
            </span>

            <span
                class="detail-value"
                id="view_notes"
                style="font-style: italic;"
            >
                -
            </span>

        </div>


        <button
            type="button"
            class="btn-row-action btn-view"
            style="
                width:100%;
                justify-content:center;
                margin-top:20px;
                padding:10px;
            "
            onclick="closeModal('viewModal')"
        >

            Close

        </button>

    </div>

</div>


<!-- =========================================================
     EDIT MODAL
========================================================= -->

<div
    class="modal-overlay"
    id="editModal"
>

    <div class="crud-modal-content">


        <div class="crud-modal-header">

            <h3>
                Update Appointment
            </h3>

            <button
                type="button"
                class="close-modal-btn"
                onclick="closeModal('editModal')"
            >

                &times;

            </button>

        </div>


        <form method="POST">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(generateCSRFToken(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <input
                type="hidden"
                name="crud_action"
                value="update"
            >


            <input
                type="hidden"
                name="booking_id"
                id="edit_booking_id"
            >


            <div class="form-group">

                <label>
                    Service
                </label>

                <select
                    name="service_id"
                    id="edit_services_id"
                    class="form-control"
                    required
                >

                    <?php foreach ($services_list as $svc): ?>

                        <option
                            value="<?= (int)$svc['id'] ?>"
                        >

                            <?= htmlspecialchars(
                                $svc['name'],
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Vehicle Model
                </label>

                <input
                    type="text"
                    name="vehicle_model"
                    id="edit_vehicle_model"
                    class="form-control"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Contact Number
                </label>

                <input
                    type="text"
                    id="edit_contact_number"
                    class="form-control"
                    readonly
                >

            </div>


            <div class="form-group">

                <label>
                    Booking Date
                </label>

                <input
                    type="date"
                    name="booking_date"
                    id="edit_booking_date"
                    class="form-control"
                    required
                >

            </div>


            <div class="form-group">

                <label>
                    Time Slot
                </label>

                <select
                    name="booking_time"
                    id="edit_booking_time"
                    class="form-control"
                    required
                >

                    <option value="09:00:00">
                        09:00 AM - 10:30 AM
                    </option>

                    <option value="10:30:00">
                        10:30 AM - 12:00 PM
                    </option>

                    <option value="13:30:00">
                        01:30 PM - 03:00 PM
                    </option>

                    <option value="14:30:00">
                        02:30 PM - 04:00 PM
                    </option>

                    <option value="15:00:00">
                        03:00 PM - 04:30 PM
                    </option>

                    <option value="16:00:00">
                        04:00 PM - 05:30 PM
                    </option>

                </select>

            </div>


            <div class="form-group">

                <label>
                    Notes
                </label>

                <textarea
                    name="notes"
                    id="edit_notes"
                    class="form-control"
                    rows="2"
                ></textarea>

            </div>


            <button
                type="submit"
                class="btn-row-action btn-edit"
                style="
                    width:100%;
                    justify-content:center;
                    margin-top:10px;
                    padding:10px;
                "
            >

                Save Changes

            </button>

        </form>

    </div>

</div>


<?php include_once 'components/footer.php'; ?>


<script>

/*
|--------------------------------------------------------------------------
| VIEW APPOINTMENT
|--------------------------------------------------------------------------
*/

function openViewModal(booking) {

    document.getElementById(
        'view_service_name'
    ).textContent =
        booking.service_name ||
        'Service N/A';


    document.getElementById(
        'view_vehicle_model'
    ).textContent =
        booking.vehicle_model ||
        'N/A';


    document.getElementById(
        'view_date_time'
    ).textContent =
        (
            booking.booking_date ||
            'N/A'
        ) +
        ' (' +
        (
            booking.booking_time ||
            'N/A'
        ) +
        ')';


    document.getElementById(
        'view_contact_number'
    ).textContent =
        booking.contact_number ||
        'N/A';


    document.getElementById(
        'view_price'
    ).textContent =
        '₱' +
        (
            booking.service_price ||
            '0.00'
        );


    document.getElementById(
        'view_notes'
    ).textContent =
        booking.notes ||
        'No notes provided.';


    const status =
        booking.status ||
        'Pending';


    const statusBadge =
        document.getElementById(
            'view_status_badge'
        );


    statusBadge.className =
        'badge-status-' +
        status.toLowerCase();


    statusBadge.textContent =
        status.charAt(0).toUpperCase() +
        status.slice(1);

    document.getElementById(
        'view_booking_reference'
    ).textContent =
        booking.booking_reference ||
        'N/A';


    document.getElementById(
        'viewModal'
    ).classList.add('active');
}


/*
|--------------------------------------------------------------------------
| EDIT APPOINTMENT
|--------------------------------------------------------------------------
*/

function openEditModal(booking) {

    document.getElementById(
        'edit_booking_id'
    ).value =
        booking.id;


    document.getElementById(
        'edit_services_id'
    ).value =
        booking.services_id;


    document.getElementById(
        'edit_vehicle_model'
    ).value =
        booking.vehicle_model ||
        '';


    document.getElementById(
        'edit_contact_number'
    ).value =
        booking.contact_number ||
        '';


    document.getElementById(
        'edit_booking_date'
    ).value =
        booking.booking_date ||
        '';


    document.getElementById(
        'edit_booking_time'
    ).value =
        booking.booking_time ||
        '';


    document.getElementById(
        'edit_notes'
    ).value =
        booking.notes ||
        '';


    document.getElementById(
        'editModal'
    ).classList.add('active');
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL
|--------------------------------------------------------------------------
*/

function closeModal(modalId) {

    const modal =
        document.getElementById(modalId);

    if (modal) {

        modal.classList.remove(
            'active'
        );
    }
}


/*
|--------------------------------------------------------------------------
| CLOSE MODAL WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

window.addEventListener(
    'click',
    function(event) {

        const viewModal =
            document.getElementById(
                'viewModal'
            );

        const editModal =
            document.getElementById(
                'editModal'
            );


        if (
            event.target === viewModal
        ) {

            closeModal(
                'viewModal'
            );
        }


        if (
            event.target === editModal
        ) {

            closeModal(
                'editModal'
            );
        }

    }
);

</script>


</body>
</html>