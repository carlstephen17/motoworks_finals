<?php
// promo_history.php - Promo claim and appointment history

require_once __DIR__ . '/includes/auth.php';
require_once 'includes/config.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = intval($_SESSION['user_id']);
if (isAdmin($userId)) {
    header('Location: admin/admin_promo_history.php');
    exit;
}
if (isStaff($userId)) {
    header('Location: staff/staff_index.php');
    exit;
}
if (!hasRole($userId, 'customer')) {
    header('Location: login.php');
    exit;
}
$isAdmin = false;

$csrfToken = generateCSRFToken();

// Session fallback details
$sessionUserName =
    $_SESSION['user_name'] ??
    $_SESSION['fullname'] ??
    $_SESSION['full_name'] ??
    $_SESSION['name'] ??
    '';

$sessionUserPhone =
    $_SESSION['phone'] ??
    $_SESSION['contact'] ??
    '';

// ----------------------------------------------------
// DELETE CLAIM
// ----------------------------------------------------
if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    ($_POST['action'] ?? '') === 'delete_claim'
) {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? null)) {
        http_response_code(403);
        exit('Invalid security token. Refresh the page and try again.');
    }
    $claimId = filter_input(INPUT_POST, 'claim_id', FILTER_VALIDATE_INT);

    if ($claimId && $claimId > 0) {
        $table = ($_POST['record_type'] ?? '') === 'promo_claim' ? 'promo_claim' : 'service_booking';
        $sql = $table === 'promo_claim'
            ? 'DELETE FROM promo_claim WHERE claimId = ? AND userId = ?'
            : 'DELETE FROM service_booking WHERE bookingId = ? AND userId = ? AND bookingBundleSlug IS NOT NULL';
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$claimId, $userId]);
    }

    header('Location: promo_history.php');
    exit;
}

// ----------------------------------------------------
// FETCH CLAIMS
// ----------------------------------------------------
if ($isAdmin) {

    $stmt = $pdo->query("
         SELECT sb.bookingId as id, sb.bookingId, sb.userId as user_id, sb.userId as users_id, sb.serviceId as service_id, sb.bookingVehicleModel as vehicle_model, sb.bookingDate as booking_date, sb.bookingDate as appointment_date, sb.bookingTime as booking_time, sb.bookingTime as appointment_time, sb.bookingBundleSlug as bundle_slug, sb.bookingBundleOption as bundle_option, sb.bookingBundleOption as selected_option, sb.bookingStatus as status, sb.bookingNotes as notes, sb.bookingCreatedAt as created_at, CONCAT_WS(' ', u.userFirstName, u.userLastName) AS fullname, u.userPhone as phone, u.userId as users_id, u.username, u.userFirstName as first_name, u.userLastName as last_name, u.userEmail as email FROM service_booking sb INNER JOIN user u ON sb.userId = u.userId WHERE sb.bookingBundleSlug IS NOT NULL
         ORDER BY sb.bookingDate ASC,
               sb.bookingTime ASC,
               sb.bookingId DESC
    ");

} else {

    $stmt = $pdo->prepare("
         SELECT sb.*,
               sb.bookingBundleSlug AS bundle_slug,
               sb.bookingBundleOption AS selected_option,
             sb.bookingDate AS appointment_date,
             sb.bookingTime AS appointment_time,
             CONCAT_WS(' ', u.userFirstName, u.userLastName) AS fullname,
             u.userPhone AS phone,
             u.userId AS users_id,
             u.username,
               u.userFirstName AS first_name,
               u.userLastName AS last_name,
               u.userEmail AS email
         FROM service_booking sb
         INNER JOIN user u ON sb.userId = u.userId
         WHERE sb.userId = ?
          AND sb.bookingBundleSlug IS NOT NULL
         ORDER BY sb.bookingDate ASC,
               sb.bookingTime ASC,
               sb.bookingId DESC
    ");

    $stmt->execute([$userId]);
}

$claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($claims as &$claim) {
    $claim['record_type'] = 'service_booking';
}
unset($claim);

$promoClaimStmt = $pdo->prepare("
    SELECT pc.claimId AS id, pc.userId AS users_id, pc.claimFullName AS fullname, pc.claimPhone AS phone,
           pc.claimBundleSlug AS bundle_slug, pc.claimSelectedOption AS selected_option,
           pc.claimAppointmentDate AS appointment_date, pc.claimAppointmentTime AS appointment_time,
           pc.claimStatus AS status, pc.claimCreatedAt AS created_at,
           u.username, u.userFirstName AS first_name, u.userLastName AS last_name
    FROM promo_claim pc
    LEFT JOIN `user` u ON pc.userId = u.userId
    WHERE pc.userId = ?
");
$promoClaimStmt->execute([$userId]);
$promoClaims = $promoClaimStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($promoClaims as &$claim) {
    $claim['record_type'] = 'promo_claim';
}
unset($claim);

$claims = array_merge($claims, $promoClaims);
usort($claims, static function (array $first, array $second): int {
    $dateComparison = strcmp(
        (string) ($first['appointment_date'] ?? ''),
        (string) ($second['appointment_date'] ?? '')
    );
    if ($dateComparison !== 0) {
        return $dateComparison;
    }

    return strcmp(
        (string) ($first['appointment_time'] ?? ''),
        (string) ($second['appointment_time'] ?? '')
    );
});
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Promo Claim & Appointment History - Bencalo MotoWorks</title>

    <link rel="stylesheet" href="styles/index/style.css">
    <link rel="stylesheet" href="styles/index/components.css">
    <link rel="stylesheet" href="styles/index/layout.css">
    <link rel="stylesheet" href="styles/promo_history.css">

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >
</head>

<body>

<?php require_once 'components/header.php'; ?>

<div id="alertToast" class="alert-toast"></div>

<main class="history-wrapper">

    <div class="history-container">

        <h2 class="history-title">
            Promo Claim & Appointment History
        </h2>

        <a href="index.php" class="back-link">
            <i class="fa-solid fa-arrow-left"></i>
            Back to Home
        </a>

        <div class="order-table-wrapper">

            <table class="styled-table">

                <thead>
                    <tr>
                        <th style="width:50px;">#</th>
                        <th>Bundle Slug</th>
                        <th>Selected Option</th>
                        <th>Date & Time</th>
                        <th>Contact Phone</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?>
                        <th>Client / User</th>
                        <?php endif; ?>
                        <th>Actions</th>
                    </tr>
                </thead>

                <tbody>

                <?php if (empty($claims)): ?>

                    <tr>
                        <td colspan="<?= $isAdmin ? '8' : '7' ?>" class="empty-state">
                            No promo claims found.
                        </td>
                    </tr>

                <?php else: ?>

                    <?php
                    $index = 1;

                    foreach ($claims as $claim):

                        $claimJson = htmlspecialchars(
                            json_encode(
                                $claim,
                                JSON_HEX_TAG |
                                JSON_HEX_APOS |
                                JSON_HEX_QUOT |
                                JSON_HEX_AMP
                            ),
                            ENT_QUOTES,
                            'UTF-8'
                        );

                        $status = $claim['status'] ?? 'Pending';

                        $statusClass =
                            strtolower($status) === 'completed'
                            ? 'badge-completed'
                            : 'badge-pending';
                    ?>

                    <tr>

                        <td>
                            <strong><?= $index++ ?></strong>
                        </td>

                        <td>
                            <span class="bundle-name">
                                <?= htmlspecialchars(
                                    $claim['bundle_slug'] ?? 'N/A'
                                ) ?>
                            </span>
                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $claim['selected_option'] ?? 'N/A'
                            ) ?>
                        </td>

                        <td>

                            <?php
                            if (!empty($claim['appointment_date'])) {
                                echo date(
                                    'F j, Y',
                                    strtotime($claim['appointment_date'])
                                );
                            } else {
                                echo 'N/A';
                            }
                            ?>

                            <span class="appointment-meta">
                                <?= htmlspecialchars(
                                    $claim['appointment_time'] ?? ''
                                ) ?>
                            </span>

                        </td>

                        <td>
                            <?= htmlspecialchars(
                                $claim['phone'] ?? $sessionUserPhone ?: 'N/A'
                            ) ?>
                        </td>

                        <td>

                            <span class="badge <?= $statusClass ?>">
                                <?= htmlspecialchars($status) ?>
                            </span>

                        </td>

                        <?php if ($isAdmin): ?>
                        <td>
                            <span style="font-size: 0.9rem; color: #aaa;">
                                <?= htmlspecialchars(trim(($claim['first_name'] ?? '') . ' ' . ($claim['last_name'] ?? '')) ?: ($claim['username'] ?? 'User #' . $claim['users_id'])) ?>
                            </span>
                        </td>
                        <?php endif; ?>

                        <td>

                            <div class="table-actions">

                                <button
                                    type="button"
                                    class="btn-view"
                                    onclick='openViewModal(<?= $claimJson ?>)'
                                >
                                    <i class="fa-solid fa-eye"></i>
                                    View
                                </button>

                                <button
                                    type="button"
                                    class="btn-edit"
                                    onclick='openEditModal(<?= $claimJson ?>)'
                                >
                                    <i class="fa-solid fa-pen"></i>
                                    Edit
                                </button>

                                <form method="POST" action="promo_history.php" style="display:inline" onsubmit="return confirm('Are you sure you want to cancel and delete this promo claim?');">
                                    <input type="hidden" name="action" value="delete_claim">
                                    <input type="hidden" name="claim_id" value="<?= (int) $claim['id'] ?>">
                                    <input type="hidden" name="record_type" value="<?= htmlspecialchars($claim['record_type'], ENT_QUOTES, 'UTF-8') ?>">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>">
                                    <button type="submit" class="btn-cancel"><i class="fa-solid fa-trash"></i> Cancel</button>
                                </form>

                            </div>

                        </td>

                    </tr>

                    <?php endforeach; ?>

                <?php endif; ?>

                </tbody>

            </table>

        </div>

    </div>

</main>


<!-- VIEW MODAL -->

<div
    id="viewModal"
    class="modal-overlay"
    style="
        display:none;
        position:fixed;
        top:0;
        left:0;
        width:100%;
        height:100%;
        background:rgba(0,0,0,0.7);
        justify-content:center;
        align-items:center;
        z-index:1000;
    "
>

    <div
        class="modal-content"
        style="
            background:#1e2124;
            padding:30px;
            border-radius:8px;
            width:450px;
            color:#fff;
            position:relative;
        "
    >

        <h3 style="margin-bottom:20px;color:#00FFFF;">
            <i class="fa-solid fa-circle-info"></i>
            Promo Claim Details
        </h3>

        <div
            id="viewModalContent"
            style="
                background:#111;
                padding:20px;
                border-radius:6px;
                border:1px solid #333;
            "
        ></div>

        <div
            style="
                display:flex;
                justify-content:flex-end;
                margin-top:20px;
            "
        >

            <button
                type="button"
                onclick="closeViewModal()"
                style="
                    padding:10px 20px;
                    background:#333;
                    border:none;
                    color:#fff;
                    border-radius:4px;
                    cursor:pointer;
                "
            >
                Close
            </button>

        </div>

    </div>

</div>


<!-- EDIT MODAL -->

<div
    id="editModal"
    class="modal-overlay"
    style="
        display:none;
        position:fixed;
        top:0;
        left:0;
        width:100%;
        height:100%;
        background:rgba(0,0,0,0.7);
        justify-content:center;
        align-items:center;
        z-index:1000;
    "
>

    <div
        class="modal-content"
        style="
            background:#1e2124;
            padding:30px;
            border-radius:8px;
            width:450px;
            color:#fff;
            position:relative;
            max-height: 90vh;
            overflow-y: auto;
        "
    >

        <h3 style="margin-bottom:15px;color:#00FFFF;">
            <i class="fa-solid fa-pen-to-square"></i>
            Edit Claim Appointment
        </h3>

        <form id="editForm" onsubmit="submitEditClaim(event)">

            <input
                type="hidden"
                id="editClaimId"
                name="claim_id"
            >
            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars($csrfToken, ENT_QUOTES, 'UTF-8') ?>"
            >

            <div style="margin-bottom:15px;">

                <label>Full Name</label>

                <input
                    type="text"
                    id="editFullname"
                    name="fullname"
                    required
                    style="
                        width:100%;
                        padding:10px;
                        background:#111;
                        border:1px solid #333;
                        color:#fff;
                        border-radius:4px;
                    "
                >

            </div>


            <div style="margin-bottom:15px;">

                <label>Contact Phone</label>

                <input
                    type="text"
                    id="editPhone"
                    name="phone"
                    required
                    style="
                        width:100%;
                        padding:10px;
                        background:#111;
                        border:1px solid #333;
                        color:#fff;
                        border-radius:4px;
                    "
                >

            </div>


            <div style="margin-bottom:15px;">

                <label>Appointment Date</label>

                <input
                    type="date"
                    id="editDate"
                    name="appointment_date"
                    required
                    min="<?= date('Y-m-d') ?>"
                    style="
                        width:100%;
                        padding:10px;
                        background:#111;
                        border:1px solid #333;
                        color:#fff;
                        border-radius:4px;
                    "
                >

            </div>


            <div style="margin-bottom:15px;">

                <label>Preferred Time Slot</label>

                <select
                    id="editTime"
                    name="appointment_time"
                    required
                    style="
                        width:100%;
                        padding:10px;
                        background:#111;
                        border:1px solid #333;
                        color:#fff;
                        border-radius:4px;
                    "
                >

                    <option value="09:00:00">
                        09:00 AM - 10:30 AM
                    </option>

                    <option value="10:30:00">
                        10:30 AM - 12:00 PM
                    </option>

                    <option value="13:00:00">
                        01:00 PM - 02:30 PM
                    </option>

                    <option value="16:00:00">
                        04:00 PM - 05:30 PM
                    </option>

                </select>

            </div>

            <?php if ($isAdmin): ?>
            <div style="margin-bottom:20px;">

                <label>Status</label>

                <select
                    id="editStatus"
                    name="status"
                    required
                    style="
                        width:100%;
                        padding:10px;
                        background:#111;
                        border:1px solid #333;
                        color:#fff;
                        border-radius:4px;
                    "
                >
                    <option value="Pending">Pending</option>
                    <option value="Completed">Completed</option>
                    <option value="Cancelled">Cancelled</option>
                </select>

            </div>
            <?php else: ?>
            <input type="hidden" id="editStatusHidden" name="status" value="Pending">
            <?php endif; ?>


            <div
                style="
                    display:flex;
                    justify-content:flex-end;
                    gap:10px;
                "
            >

                <button
                    type="button"
                    onclick="closeEditModal()"
                    style="
                        padding:10px 20px;
                        background:#333;
                        border:none;
                        color:#fff;
                        border-radius:4px;
                        cursor:pointer;
                    "
                >
                    Cancel
                </button>

                <button
                    type="submit"
                    id="submitEditBtn"
                    style="
                        padding:10px 20px;
                        background:#00FFFF;
                        border:none;
                        color:#000;
                        font-weight:bold;
                        border-radius:4px;
                        cursor:pointer;
                    "
                >
                    Save Changes
                </button>

            </div>

        </form>

    </div>

</div>


<?php require_once 'components/footer.php'; ?>


<script>

const sessionDefaultName =
    <?= json_encode($sessionUserName) ?>;

const sessionDefaultPhone =
    <?= json_encode($sessionUserPhone) ?>;

const isAdminUser = <?= $isAdmin ? 'true' : 'false' ?>;


function showAlert(message, type) {

    const toast =
        document.getElementById('alertToast');

    toast.textContent = message;

    toast.className =
        'alert-toast ' +
        (type === 'success'
            ? 'alert-success'
            : 'alert-error');

    toast.style.display = 'block';

    setTimeout(function () {
        toast.style.display = 'none';
    }, 4000);
}


function openViewModal(claim) {

    const modalContent =
        document.getElementById('viewModalContent');

    modalContent.innerHTML = `
        <p>
            <strong>Bundle / Promo:</strong>
            ${claim.bundle_slug || 'N/A'}
        </p>

        <p>
            <strong>Selected Option:</strong>
            ${claim.selected_option || 'N/A'}
        </p>

        <hr style="border-color:#333;margin:10px 0;">

        <p>
            <strong>Full Name:</strong>
            ${claim.fullname || sessionDefaultName || 'N/A'}
        </p>

        <p>
            <strong>Contact Phone:</strong>
            ${claim.phone || sessionDefaultPhone || 'N/A'}
        </p>

        <p>
            <strong>Appointment Date:</strong>
            ${claim.appointment_date || 'N/A'}
        </p>

        <p>
            <strong>Time Slot:</strong>
            ${claim.appointment_time || 'N/A'}
        </p>

        <p>
            <strong>Current Status:</strong>
            <span style="color:#00FFFF;font-weight:bold;">
                ${claim.status || 'Pending'}
            </span>
        </p>
    `;

    document.getElementById('viewModal').style.display = 'flex';
}


function closeViewModal() {

    document.getElementById('viewModal').style.display = 'none';

}


function openEditModal(claim) {

    document.getElementById('editClaimId').value =
        claim.id;

    document.getElementById('editFullname').value =
        claim.fullname || sessionDefaultName;

    document.getElementById('editPhone').value =
        claim.phone || sessionDefaultPhone;

    document.getElementById('editDate').value =
        claim.appointment_date || '';

    document.getElementById('editTime').value =
        claim.appointment_time || '09:00:00';

    if (isAdminUser) {
        const statusSelect = document.getElementById('editStatus');
        if (statusSelect) {
            statusSelect.value = claim.status || 'Pending';
        }
    } else {
        const statusHidden = document.getElementById('editStatusHidden');
        if (statusHidden) {
            statusHidden.value = claim.status || 'Pending';
        }
    }

    document.getElementById('editModal').style.display = 'flex';
}


function closeEditModal() {

    document.getElementById('editModal').style.display = 'none';

}


function submitEditClaim(event) {

    event.preventDefault();

    const btn =
        document.getElementById('submitEditBtn');

    btn.disabled = true;
    btn.textContent = 'Saving...';

    const formData =
        new FormData(
            document.getElementById('editForm')
        );

    fetch('update_claim.php', {
        method: 'POST',
        body: formData
    })

    .then(async response => {
        const text = await response.text();
        try {
            return JSON.parse(text);
        } catch (err) {
            throw new Error(text.trim() || 'Server returned invalid JSON.');
        }
    })

    .then(data => {

        btn.disabled = false;
        btn.textContent = 'Save Changes';

        if (data.success) {

            showAlert(data.message, 'success');

            closeEditModal();

            setTimeout(function () {
                window.location.reload();
            }, 1200);

        } else {

            showAlert(data.message, 'error');

        }

    })

    .catch(function (error) {

        btn.disabled = false;
        btn.textContent = 'Save Changes';

        showAlert(
            'Error: ' + error.message,
            'error'
        );

    });
}


window.onclick = function(event) {

    const viewModal =
        document.getElementById('viewModal');

    const editModal =
        document.getElementById('editModal');

    if (event.target === viewModal) {
        closeViewModal();
    }

    if (event.target === editModal) {
        closeEditModal();
    }

};

</script>

</body>
</html>