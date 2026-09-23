<?php
ob_start();

require 'db.php';
// Helper function to get manager users
// Helper function to get manager users
function getManagerUsers($conn) {
    $managers = array();  // Changed from [] to array()
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'manager'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $managers[] = $row['id'];
    }
    $stmt->close();
    return $managers;
}

function getOwnerUsers($conn) {
    $owners = array();
    $stmt = $conn->prepare("SELECT id FROM users WHERE role = 'owner' AND status = 'active'");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $owners[] = $row['id'];
    }
    $stmt->close();
    return $owners;
}
// Start session to store submission status safely across redirect
if (session_id() == '') {
    session_start();
}

// Security Enforcement
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Make sure only customers can access this page
if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}

// Match the active logged-in parameters
$user_id = $_SESSION['user_id'];
$customer_name = isset($_SESSION['fullname']) ? $_SESSION['fullname'] : '';
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'Customer';

// Helper function to insert notifications easily into the database
function add_notification($conn, $user_id, $title, $description, $type = 'info') {
    $stmt = $conn->prepare("INSERT INTO notifications (user_id, title, description, type, is_read) VALUES (?, ?, ?, ?, 0)");
    $stmt->bind_param("isss", $user_id, $title, $description, $type);
    $stmt->execute();
    $stmt->close();
}

// AJAX API Endpoint Processing
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['action']) && $_GET['action'] === 'log_cancellation') {
    header('Content-Type: application/json');
    add_notification($conn, $user_id, "Draft process cancelled", "Timeline reset to Draft status", "alert");
  echo json_encode(array('status' => 'success'));
    exit();
}

$message = isset($_SESSION['success_message']) ? $_SESSION['success_message'] : '';
$is_submitted = isset($_SESSION['is_submitted']) ? $_SESSION['is_submitted'] : false;

unset($_SESSION['success_message']);
unset($_SESSION['is_submitted']);

// Price list configuration
$prices = array( 'Extra Small' => 140, 'Small' => 150, 'Medium' => 175, 'Large' => 195, 'Extra Large' => 235, 'Jumbo' => 255, 'Super Jumbo' => 270, 'Double Yolk' => 320 );

$today = date('Y-m-d');

// =========================================================
// GENERATE RESERVATION CODE
// Format: RES-YYYYMMDD-001
// =========================================================

function generateReservationCode($conn) {

    $datePrefix = date('Ymd');
    $prefix = 'RES-' . $datePrefix . '-';

    $stmt = $conn->prepare("
        SELECT reservation_code
        FROM reservations
        WHERE reservation_code LIKE ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $likePrefix = $prefix . '%';

    $stmt->bind_param("s", $likePrefix);
    $stmt->execute();

    $result = $stmt->get_result();
    $lastNumber = 0;

    if ($row = $result->fetch_assoc()) {

        $lastCode = $row['reservation_code'];

        $parts = explode('-', $lastCode);

        if (count($parts) == 3) {
            $lastNumber = intval($parts[2]);
        }
    }

    $stmt->close();

    $nextNumber = $lastNumber + 1;

    return $prefix . str_pad($nextNumber, 3, '0', STR_PAD_LEFT);
}


// Current reservation ID displayed on the form
$reservation_code = generateReservationCode($conn);


// =========================================================
// HANDLE FORM SUBMISSION
// =========================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_reservation'])) {

    $customer_name = $_POST['customer_name'];
    $contact_number = $_POST['contact_number'];
    $delivery_address = $_POST['delivery_address'];
    $delivery_method = isset($_POST['delivery_method'])
    ? $_POST['delivery_method']
    : '';

$reservation_date = isset($_POST['reservation_date']) && $_POST['reservation_date'] !== ''
    ? $_POST['reservation_date']
    : null;

$reserved_at = date('Y-m-d H:i:s');


// =========================================================
// RESERVATION DATE RULE
// Pickup  = customer chooses the date
// Delivery = manager will assign the date later
// =========================================================

if ($delivery_method === 'Pickup' && empty($reservation_date)) {

    $_SESSION['success_message'] =
        "Please select your preferred pickup date.";

    $_SESSION['is_submitted'] = false;

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}

if ($delivery_method === 'Delivery') {

    // No date yet.
    // Manager will assign the delivery date later.
    $reservation_date = null;
}

    // Get the reservation code from the hidden form field
    $reservation_code = isset($_POST['reservation_code'])
        ? $_POST['reservation_code']
        : generateReservationCode($conn);

    // Grab item bundle arrays
    $egg_types = isset($_POST['egg_types']) ? $_POST['egg_types'] : array();
    $quantities = isset($_POST['quantities']) ? $_POST['quantities'] : array();

    if (!empty($egg_types) && count($egg_types) === count($quantities)) {

        $conn->autocommit(false);

        try {

            $stmt = $conn->prepare("
              INSERT INTO reservations
(user_id, customer_name, contact_number, delivery_address, egg_type, quantity, delivery_method, reservation_date, reserved_at, total_price, reservation_code, status)
VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");

            $total_bundle_trays = 0;

            for ($i = 0; $i < count($egg_types); $i++) {

                $egg_type = $egg_types[$i];
                $quantity = intval($quantities[$i]);

                if ($quantity <= 0) {
                    continue;
                }

                $price_per_egg = isset($prices[$egg_type])
                    ? $prices[$egg_type]
                    : 0;

                $total_price = $quantity * $price_per_egg;

                $total_bundle_trays += $quantity;

                $status = 'Pending';

             $stmt->bind_param(
    "issssisssdss",
    $user_id,
    $customer_name,
    $contact_number,
    $delivery_address,
    $egg_type,
    $quantity,
    $delivery_method,
    $reservation_date,
    $reserved_at,
    $total_price,
    $reservation_code,
    $status
);

                $stmt->execute();
            }

            $stmt->close();

// Notification to customer
add_notification(
    $conn,
    $user_id,
    "Reservation submitted!",
    "Reservation {$reservation_code} with {$total_bundle_trays} total tray(s) is waiting for manager confirmation.",
    "success"
);

// NOTIFY ALL MANAGERS
$managers = getManagerUsers($conn);
foreach ($managers as $manager_id) {
    add_notification(
        $conn,
        $manager_id,
        "New Reservation Received",
        "Customer {$customer_name} submitted reservation {$reservation_code}. Please review and confirm.",
        "alert"
    );
}

$owners = getOwnerUsers($conn);
foreach ($owners as $owner_id) {
    add_notification(
        $conn,
        $owner_id,
        "New Reservation Received",
        "Customer {$customer_name} submitted reservation {$reservation_code}.",
        "info"
    );
}

            $conn->commit();

            $_SESSION['success_message'] =
                "Reservation {$reservation_code} submitted successfully!";

            $_SESSION['is_submitted'] = true;

        } catch (Exception $e) {

            $conn->rollback();

            $_SESSION['success_message'] =
                "Error processing your reservation.";
        }
    }

    header("Location: " . $_SERVER['PHP_SELF']);
    exit();
}
  

// Fetch user notifications
$notifications = array();
$notif_stmt = $conn->prepare("SELECT id, title, description, type, is_read, created_at FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 15");
$notif_stmt->bind_param("i", $user_id);
$notif_stmt->execute();
$result = $notif_stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}
$notif_stmt->close();

$unread_count = 0;
foreach ($notifications as $n) {
    if (!$n['is_read']) $unread_count++;
}

// =========================================================
// FETCH CUSTOMER RESERVATIONS
// =========================================================

$my_reservations = array();

$reservation_stmt = $conn->prepare("
   SELECT
    reservation_code,
    customer_name,
    contact_number,
    delivery_address,
    egg_type,
    quantity,
    delivery_method,
    reservation_date,
    reserved_at,
    total_price,
    status
FROM reservations
    WHERE user_id = ?
    ORDER BY id DESC
");

$reservation_stmt->bind_param("i", $user_id);
$reservation_stmt->execute();

$reservation_result = $reservation_stmt->get_result();

while ($row = $reservation_result->fetch_assoc()) {

    $code = $row['reservation_code'];

    if (!isset($my_reservations[$code])) {

        $my_reservations[$code] = array(
            'reservation_code' => $code,
            'customer_name' => $row['customer_name'],
            'delivery_address' => $row['delivery_address'],
            'delivery_method' => $row['delivery_method'],
            'reservation_date' => $row['reservation_date'],
            'reserved_at' => $row['reserved_at'],
            'status' => $row['status'],
            'total_price' => 0,
            'total_quantity' => 0,
            'items' => array()
        );
    }

    $my_reservations[$code]['total_price'] += $row['total_price'];
    $my_reservations[$code]['total_quantity'] += $row['quantity'];

    $my_reservations[$code]['items'][] = array(
        'egg_type' => $row['egg_type'],
        'quantity' => $row['quantity']
    );
}

$reservation_stmt->close();

// Default avatar - no profile_photo column required

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VDVC - Customer Reservation</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
</head>
<style>

/* =========================================================
   CUSTOMER RESERVATION — CUSTOMER DASHBOARD MATCH
   HEADER + PAGE SHELL ONLY
========================================================= */

/* =========================================================
   MAIN CONTENT
========================================================= */
html,
body {

    margin:
        0;

    padding:
        0;

    width:
        100%;

    min-height:
        100%;

    background:
        #f3f1eb;
}
.main-content {

    margin-left:
        312px;

    min-height:
        100vh;

    padding:
        18px;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );
}


/* =========================================================
   RESERVATION OUTER CARD
   SAME AS CUSTOMER DASHBOARD WRAPPER
========================================================= */

.reservation-card {

    width:
        100%;

    background:
        #ffffff;

    border:
        1px solid #dedbd2;

    border-radius:
        22px;

    box-shadow:
        0 12px 35px
        rgba(
            80,
            72,
            55,
            0.10
        );

    overflow:
        hidden;
}


/* =========================================================
   REMOVE EXTRA TAILWIND OUTER SPACING
========================================================= */

.main-content > .flex-1 {
    width: 100% !important;
}

.main-content > .flex-1 > .max-w-7xl {
    width: 100% !important;
    max-width: none !important;

    margin: 0 !important;

    padding:
        0 !important;
}


/* =========================================================
   CUSTOMER RESERVATION HEADER
   EXACT CUSTOMER DASHBOARD HEADER SIZE
========================================================= */

.reservation-card > header {

    min-height:
        86px !important;

    width:
        100% !important;

    padding:
        0 28px !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        space-between !important;

    background:
        #f7f6f2 !important;

    border-bottom:
        1px solid #dddcd6 !important;

    box-sizing:
        border-box !important;
}


/* =========================================================
   HEADER TITLE
========================================================= */

.reservation-card > header h1 {

    margin:
        0 !important;

    padding:
        0 !important;

    font-family:
        Arial,
        Helvetica,
        sans-serif !important;

    font-size:
        1.35rem !important;

    font-weight:
        700 !important;

    line-height:
        1.2 !important;

    color:
        #3f4b45 !important;

    letter-spacing:
        -0.3px !important;
}


/* =========================================================
   HEADER RIGHT SIDE
========================================================= */

.reservation-card > header > div {

    display:
        flex !important;

    align-items:
        center !important;

    gap:
        18px !important;

    height:
        100% !important;
}


/* =========================================================
   CUSTOMER ACCOUNT AREA

   Customer Dashboard:
   Avatar → Name → Date/Time → Dropdown

   Reservation:
   We keep the existing name position but
   make its size/color match.
========================================================= */

.reservation-card > header .text-right {

    display:
        flex !important;

    flex-direction:
        column !important;

    justify-content:
        center !important;

    min-width:
        100px !important;

    text-align:
        left !important;
}


/* =========================================================
   CUSTOMER NAME
========================================================= */

.reservation-card > header .text-right > div:first-child {

    display:
        block !important;

    margin:
        0 !important;

    color:
        #3f4b45 !important;

    font-size:
        0.88rem !important;

    font-weight:
        700 !important;

    line-height:
        1.2 !important;
}


/* =========================================================
   CUSTOMER ROLE
========================================================= */

.reservation-card > header .text-right > div:last-child {

    margin-top:
        5px !important;

    font-size:
        0.63rem !important;

    color:
        #8a9590 !important;

    line-height:
        1.2 !important;
}


/* =========================================================
   PROFILE AVATAR
   EXACT CUSTOMER DASHBOARD SIZE
========================================================= */

.reservation-card > header .w-10.h-10 {

    width:
        54px !important;

    height:
        54px !important;

    min-width:
        54px !important;

    border-radius:
        50% !important;

    overflow:
        hidden !important;

    background:
        #e8ebe7 !important;

    border:
        1px solid #d9ddd8 !important;

    box-shadow:
        0 2px 6px
        rgba(
            0,
            0,
            0,
            0.04
        ) !important;
}


.reservation-card > header .w-10.h-10 img {

    width:
        100% !important;

    height:
        100% !important;

    object-fit:
        cover !important;

    display:
        block !important;
}


/* =========================================================
   NOTIFICATION CONTAINER
========================================================= */

.reservation-card > header .relative {

    position:
        relative !important;
}


/* =========================================================
   NOTIFICATION BELL
========================================================= */

.reservation-card > header .relative > div:first-child {

    width:
        40px !important;

    height:
        40px !important;

    padding:
        0 !important;

    display:
        flex !important;

    align-items:
        center !important;

    justify-content:
        center !important;

    border-radius:
        50% !important;

    color:
        #65716b !important;

    background:
        transparent !important;
}


.reservation-card > header .relative > div:first-child:hover {

    background:
        transparent !important;

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION ICON
========================================================= */

.reservation-card > header .relative > div:first-child i {

    color:
        #65716b !important;

    font-size:
        1.2rem !important;
}


.reservation-card > header .relative > div:first-child:hover i {

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION BADGE
   SAME SIZE/COLOR AS DASHBOARD
========================================================= */

#bell_badge {

    top:
        1px !important;

    right:
        0 !important;

    width:
        auto !important;

    min-width:
        17px !important;

    height:
        17px !important;

    padding:
        0 4px !important;

    border-radius:
        50% !important;

    background:
        #527d59 !important;

    border:
        2px solid #f7f6f2 !important;
}


/* =========================================================
   NOTIFICATION DROPDOWN
   CHANGE BLUE ACCENTS TO DASHBOARD GREEN
========================================================= */

#notification_dropdown {

    width:
        320px !important;

    margin-top:
        12px !important;

    background:
        #ffffff !important;

    border:
        1px solid #dedbd2 !important;

    border-radius:
        12px !important;

    box-shadow:
        0 12px 30px
        rgba(
            70,
            65,
            55,
            0.12
        ) !important;
}


/* =========================================================
   NOTIFICATION HEADER
========================================================= */

#notification_dropdown > div:first-child {

    background:
        #f7f6f2 !important;

    border-bottom:
        1px solid #e9e6df !important;
}


#notification_dropdown > div:first-child span:first-child {

    color:
        #3f4b45 !important;
}


/* =========================================================
   UNREAD COUNT
========================================================= */

#unread_count {

    background:
        #e9f1e6 !important;

    color:
        #527d59 !important;
}


/* =========================================================
   NOTIFICATION ITEMS
========================================================= */

#notification_list > div {

    transition:
        background 0.2s ease;
}


#notification_list > div:hover {

    background:
        #fafaf7 !important;
}


/* =========================================================
   NOTIFICATION ICON COLORS
========================================================= */

#notification_list .bg-blue-100 {

    background:
        #eef3ed !important;

    color:
        #527d59 !important;
}


/* =========================================================
   RESERVATION BODY
   SAME INNER SPACING FEEL AS DASHBOARD
========================================================= */

.reservation-card > .p-6 {

    padding:
        22px 28px 28px !important;
}


/* =========================================================
   MY RESERVATIONS SECTION
========================================================= */

.reservation-card .mt-6 {

    border:
        1px solid #e2dfd7 !important;

    border-radius:
        10px !important;

    background:
        #ffffff !important;

    box-shadow:
        0 3px 10px
        rgba(
            70,
            65,
            55,
            0.05
        ) !important;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1100px) {

    .main-content {

        margin-left:
            270px !important;

        width:
            calc(100% - 270px) !important;
    }
}


@media screen and (max-width: 900px) {

    .main-content {

        margin-left:
            0 !important;

        width:
            100% !important;

        padding:
            15px !important;
    }

    .reservation-card {

        border-radius:
            18px !important;
    }

    .reservation-card > header {

        padding:
            0 18px !important;
    }
}


@media screen and (max-width: 600px) {

    .reservation-card > .p-6 {

        padding:
            18px !important;
    }

    .reservation-card > header h1 {

        font-size:
            1.15rem !important;
    }

    .reservation-card > header .text-right {

        display:
            none !important;
    }
}
/* =========================================================
   CUSTOMER RESERVATION HEADER
   SAME AS CUSTOMER DASHBOARD
========================================================= */

.customer-dashboard-topbar {

    min-height:
        86px;

    width:
        100%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    padding:
        0 28px;

    background:
        #f7f6f2;

    border-bottom:
        1px solid #dddcd6;

    box-sizing:
        border-box;
}


/* =========================================================
   HEADER TITLE
========================================================= */

.customer-dashboard-page-title {

    margin:
        0;

    font-size:
        1.35rem;

    font-weight:
        700;

    color:
        #3f4b45;

    letter-spacing:
        -0.3px;
}


/* =========================================================
   HEADER RIGHT
========================================================= */

.customer-dashboard-topbar-right {

    display:
        flex;

    align-items:
        center;

    gap:
        18px;

    height:
        100%;
}


/* =========================================================
   NOTIFICATION CONTAINER
========================================================= */

.customer-notification-container {

    position:
        relative;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.customer-top-notification {

    position:
        relative;

    width:
        40px;

    height:
        40px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    text-decoration:
        none;

    background:
        transparent;

    border:
        none;

    padding:
        0;

    cursor:
        pointer;

    color:
        #65716b;

    font-size:
        1.2rem;

    transition:
        0.2s ease;
}


.customer-top-notification:hover {

    color:
        #527d59;
}


/* =========================================================
   NOTIFICATION BADGE
========================================================= */

.customer-notification-badge {

    position:
        absolute;

    top:
        1px;

    right:
        0;

    min-width:
        17px;

    height:
        17px;

    padding:
        0 4px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #527d59;

    color:
        #ffffff;

    font-size:
        0.58rem;

    font-weight:
        700;

    border:
        2px solid #f7f6f2;
}


/* =========================================================
   HEADER DIVIDER
========================================================= */

.customer-topbar-divider {

    width:
        1px;

    height:
        42px;

    background:
        #deded8;
}


/* =========================================================
   CUSTOMER PROFILE
========================================================= */

.customer-top-profile {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    min-width:
        190px;

    text-decoration:
        none;

    color:
        #3f4b45;
}


/* =========================================================
   CUSTOMER AVATAR
========================================================= */

.customer-avatar {

    width:
        54px;

    height:
        54px;

    min-width:
        54px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #e8ebe7;

    border:
        1px solid #d9ddd8;

    color:
        #527d59;

    font-size:
        1.25rem;

    box-shadow:
        0 2px 6px
        rgba(
            0,
            0,
            0,
            0.04
        );
}


/* =========================================================
   CUSTOMER ACCOUNT DETAILS
========================================================= */

.customer-account-details {

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    min-width:
        100px;
}


/* =========================================================
   CUSTOMER NAME
========================================================= */

.customer-top-name {

    display:
        block;

    margin:
        0;

    color:
        #3f4b45;

    font-size:
        0.88rem;

    font-weight:
        700;

    line-height:
        1.2;
}


/* =========================================================
   CUSTOMER ROLE
========================================================= */

.customer-role {

    display:
        block;

    margin-top:
        5px;

    color:
        #8a9590;

    font-size:
        0.63rem;

    line-height:
        1.2;

    white-space:
        nowrap;
}


/* =========================================================
   DATE AND TIME
========================================================= */

.customer-date-time {

    display:
        flex;

    flex-direction:
        column;

    align-items:
        flex-start;

    gap:
        2px;

    margin-top:
        5px;

    white-space:
        nowrap;
}


.customer-date {

    font-size:
        0.63rem;

    color:
        #8a9590;
}


.customer-time {

    font-size:
        0.63rem;

    color:
        #527d59;

    font-weight:
        600;

    line-height:
        1.2;
}


/* =========================================================
   DROPDOWN ICON
========================================================= */

.customer-dropdown-icon {

    width:
        28px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    color:
        #65716b;

    font-size:
        0.7rem;
}
/* =========================================================
   RESERVATION STEP CARDS
========================================================= */

.step-column {
    min-width: 0;
}

.step-card {
    background: #ffffff;
    border: 1px solid #dedbd2;
    border-radius: 16px;
    padding: 22px;
    height: 100%;
    box-sizing: border-box;
    box-shadow: 0 4px 14px rgba(80, 72, 55, 0.06);
}

.step-1-card {
    background: #ffffff;
}

.step-scroll {
    min-height: 0;
}
</style>
<body class="font-sans text-gray-700 antialiased min-h-screen">

<?php include('customer_panel.php'); ?>

<div class="main-content">
      

        <!-- Main Workspace -->
        <div class="flex-1 flex flex-col min-w-0">
           

     <div class="w-full mx-auto">
    <form id="reservation_form" action="" method="POST" onsubmit="return validateForm()" autocomplete="off">

        <div class="reservation-card bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">

            <!-- =====================================================
                 RESERVATION HEADER
                 ===================================================== -->
 <!-- =====================================================
     CUSTOMER RESERVATION TOPBAR
===================================================== -->

<div class="customer-dashboard-topbar">

    <!-- PAGE TITLE -->
    <h1 class="customer-dashboard-page-title">
        📝 Customer Reservation
    </h1>


    <!-- HEADER RIGHT -->
    <div class="customer-dashboard-topbar-right">


        <!-- NOTIFICATION -->
        <div class="customer-notification-container">

            <button
                type="button"
                class="customer-top-notification"
                onclick="toggleNotifications(event)"
                title="Notifications"
            >

                <i class="fa-regular fa-bell"></i>

                <?php if ($unread_count > 0) { ?>

                    <span class="customer-notification-badge">
                        <?php echo $unread_count; ?>
                    </span>

                <?php } ?>

            </button>


            <!-- KEEP YOUR EXISTING NOTIFICATION DROPDOWN -->
            <div
                id="notification_dropdown"
                class="notification-dropdown-custom hidden"
            >

                <div class="notification-dropdown-header-custom">

                    <strong>
                        Notifications
                    </strong>

                    <?php if ($unread_count > 0) { ?>

                        <span>
                            <?php echo $unread_count; ?> unread
                        </span>

                    <?php } ?>

                </div>


                <div class="notification-list-custom">

                    <?php if (count($notifications) > 0) { ?>

                        <?php foreach ($notifications as $notification) { ?>

                            <div class="notification-item-custom">

                                <div class="notification-item-icon-custom">

                                    <i class="fa-regular fa-bell"></i>

                                </div>


                                <div class="notification-item-content-custom">

                                    <strong>
                                        <?php
                                        echo htmlspecialchars(
                                            $notification['title']
                                        );
                                        ?>
                                    </strong>

                                    <span>
                                        <?php
                                        echo htmlspecialchars(
                                            $notification['message']
                                        );
                                        ?>
                                    </span>

                                </div>

                            </div>

                        <?php } ?>

                    <?php } else { ?>

                        <div class="notification-empty-custom">

                            <i class="fa-regular fa-circle-check"></i>

                            <strong>
                                No new notifications
                            </strong>

                            <span>
                                You're all caught up.
                            </span>

                        </div>

                    <?php } ?>

                </div>

            </div>

        </div>


        <!-- DIVIDER -->
        <div class="customer-topbar-divider"></div>


        <!-- CUSTOMER PROFILE -->
        <a
            href="customer_profile.php"
            class="customer-top-profile"
        >

            <!-- AVATAR -->
            <div class="customer-avatar">

                <i class="fa-regular fa-user"></i>

            </div>


            <!-- ACCOUNT DETAILS -->
            <div class="customer-account-details">

                <span class="customer-top-name">

                    <?php

                    $reservation_name_parts =
                        explode(
                            " ",
                            trim($customer_name)
                        );

                    echo htmlspecialchars(
                        $reservation_name_parts[0]
                    );

                    ?>

                </span>


              


                <div class="customer-date-time">

                  <span class="customer-date">

    <?php
    echo date("F d, Y");
    ?>

</span>

<span
    class="customer-time"
    id="live-time"
>
    <?php
    echo date("g:i A");
    ?>
</span>

                </div>

            </div>


            <!-- DROPDOWN ICON -->
            <div class="customer-dropdown-icon">

                <i class="fa-solid fa-chevron-down"></i>

            </div>

        </a>

    </div>

</div>



            <!-- =====================================================
                 STEP 1 / STEP 2 / STEP 3
                 ===================================================== -->

            <div class="p-6">

                <div class="reservation-grid grid grid-cols-1 lg:grid-cols-3 gap-6 items-stretch">
                    
                    <!-- Step 1: Info -->
           <div class="step-column">

    <div class="step-card step-1-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 1: Contact & Delivery Information
        </h2>
<!-- Reservation ID -->
<div class="border border-blue-200 bg-blue-50/40 rounded-lg px-4 py-3 mb-4">

    <div class="flex items-center justify-between">

        <div>
            <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
                Reservation ID
            </label>

            <div class="text-lg font-extrabold text-blue-600 tracking-wide">
                <?= htmlspecialchars($reservation_code); ?>
            </div>

            <p class="text-[10px] text-gray-400 mt-1">
                Auto-generated
            </p>
        </div>

        <button
            type="button"
            onclick="copyReservationCode()"
            class="w-8 h-8 rounded-lg hover:bg-blue-100 text-gray-500 hover:text-blue-600 transition"
            title="Copy Reservation ID"
        >
            <i class="fa-regular fa-copy"></i>
        </button>

    </div>

</div>

<!-- Hidden Reservation Code -->
<input
    type="hidden"
    name="reservation_code"
    value="<?= htmlspecialchars($reservation_code); ?>"
>
        <div class="step-1-content space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Customer Name</label>
                                <div class="relative">
                                    <input type="text" id="cust_name_input" name="customer_name" placeholder="Enter full name" value="<?= htmlspecialchars($customer_name); ?>" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                                    <i class="fa-solid fa-pen absolute right-3 top-3 text-gray-400 text-xs"></i>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Contact Number</label>
                               <input
    type="text"
    id="contact_input"
    name="contact_number"
    placeholder="09XXXXXXXXX"
    maxlength="11"
    minlength="11"
    inputmode="numeric"
    pattern="[0-9]{11}"
    required
    oninput="this.value = this.value.replace(/[^0-9]/g, '').slice(0, 11)"
    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Delivery Address</label>
                                <input type="text" id="delivery_address_input" name="delivery_address" placeholder="Type location..." required oninput="handleAddressTyping(this.value)" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500 mb-2">
                                <div class="border border-gray-200 rounded-lg overflow-hidden relative h-48 bg-sky-50 z-10" id="map"></div>
                            </div>
                        </div>
                    </div>
</div>
                    <!-- Step 2: Bundle Items Configurator -->
                 <div class="step-column">

    <div class="step-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 2: Add Items
        </h2>

        <div class="step-scroll space-y-4">
                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">Egg Size Selection</label>
                                <div class="grid grid-cols-4 gap-2 mb-3">
                                    <?php foreach($prices as $size => $price): ?>
                                        <button type="button" data-egg="<?= $size ?>" onclick="setEgg('<?= $size ?>')" class="egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50">
                                            <span class="w-5 h-7 bg-slate-100 border border-slate-400 rounded-full inline-block shadow-inner mb-1"></span>
                                            <span class="text-[9px] text-gray-600 font-medium text-center leading-none mb-1"><?= $size ?></span>
                                            <span class="text-[9px] text-blue-600 font-bold">₱<?= $price ?></span>
                                        </button>
                                    <?php endforeach; ?>
                                </div>
                              <input
    type="text"
    id="egg_type_select"
    placeholder="-- Choose Size --"
    readonly
    class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm bg-gray-50 text-gray-700 focus:outline-none cursor-default"
>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Quantity</label>
                                <input type="number" id="quantity_input" min="1" class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500">
                            </div>

                            <div>

    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-2">
        Delivery Method
    </label>

    <input
        type="hidden"
        name="delivery_method"
        id="delivery_method_input"
        required
    >

    <div class="grid grid-cols-2 gap-4">

        <!-- DELIVERY -->
        <button
            type="button"
            id="btn-delivery"
            onclick="setMethod('Delivery')"
            class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500"
        >
            <i class="fa-solid fa-truck text-lg"></i>
            <span class="text-xs font-semibold">Delivery</span>
        </button>


        <!-- PICKUP -->
        <button
            type="button"
            id="btn-pickup"
            onclick="setMethod('Pickup')"
            class="p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 transition-all border-gray-200 text-gray-500"
        >
            <i class="fa-solid fa-shop text-lg"></i>
            <span class="text-xs font-semibold">Pickup</span>
        </button>

    </div>


    <!-- INFORMATION BASED ON METHOD -->

    <div id="method_description" class="mt-3">

        <div class="text-[10px] text-gray-400 text-center">
            Please select Delivery or Pickup.
        </div>

    </div>


    <hr class="border-gray-100 my-3">


    <button
        type="button"
        onclick="addItemToBundle()"
        class="w-full bg-slate-800 hover:bg-slate-900 text-white font-medium py-2 px-4 rounded-lg text-xs transition flex items-center justify-center space-x-2"
    >
        <i class="fa-solid fa-plus"></i>
        <span>Add To Cart</span>
    </button>

</div>

<!-- =====================================================
     PICKUP DATE
     Only visible when Pickup is selected
     ===================================================== -->

<div id="pickup_date_section" class="hidden">

    <label class="block text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">
        Preferred Pickup Date
    </label>

    <input
        type="date"
        id="reservation_date_input"
        name="reservation_date"
        min="<?= $today ?>"
        onchange="updateLiveSummary()"
        class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-blue-500"
    >

    <p class="text-[10px] text-gray-400 mt-1">
        Select the date when you prefer to pick up your order at the farm.
    </p>

</div>

<!-- =====================================================
     DELIVERY INFORMATION
     Only visible when Delivery is selected
     ===================================================== -->

<div
    id="delivery_schedule_info"
    class="hidden border border-blue-100 bg-blue-50 rounded-lg p-3"
>

    <div class="flex items-start gap-2">

        <i class="fa-solid fa-truck text-blue-500 mt-0.5"></i>

        <div>

            <p class="text-xs font-bold text-blue-700">
                Delivery Schedule
            </p>

            <p class="text-[10px] text-blue-600 mt-1 leading-relaxed">
                No date is required at this time.
                The farm manager will assign the delivery date
                based on availability.
            </p>

        </div>

    </div>

</div>
                        </div>
                    </div>
</div>
                    <!-- Step 3: Bundle Checkout Review -->
                  <div class="step-column">

    <div class="step-card">

        <h2 class="text-lg font-bold text-slate-800 mb-5">
            Step 3: Review & Submit
        </h2>

        <div class="step-scroll space-y-6">
                            <div>
                                <h3 class="text-sm font-bold text-slate-700 mb-3"> Items List</h3>
                                <!-- Container for Hidden Form Array Inputs -->
                                <div id="hidden_bundle_inputs"></div>
                                
                                <div class="border border-gray-100 rounded-lg overflow-hidden text-sm">
                                    <table class="w-full text-left border-collapse">
                                        <thead class="bg-gray-50 text-xs text-gray-500 uppercase tracking-wider">
                                            <tr>
                                                <th class="p-3">Item</th>
                                                <th class="p-3 text-center">Qty</th>
                                                <th class="p-3 text-right">Total</th>
                                                <th class="p-3 text-center"></th>
                                            </tr>
                                        </thead>
                                        <tbody id="bundle_table_body" class="divide-y divide-gray-100">
                                            <tr>
                                                <td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added yet.</td>
                                            </tr>
                                        </tbody>
                                    </table>
                                    
                                    <div class="divide-y divide-gray-100 bg-gray-50 p-3 text-xs space-y-2 border-t border-gray-100">
                                        <div class="flex justify-between"><span class="text-gray-500">Method:</span><span id="summary_method" class="font-semibold text-gray-700">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Date:</span><span id="summary_date" class="font-semibold text-blue-600">None selected</span></div>
                                        <div class="flex justify-between"><span class="text-gray-500">Address:</span><span id="summary_address_display" class="font-semibold text-gray-700 max-w-[180px] truncate text-right">None</span></div>
                                    </div>
                                    <div class="flex justify-between p-4 bg-slate-50 items-center border-t border-gray-200">
                                        <span class="font-bold text-slate-800">Grand Total:</span>
                                        <span class="text-xl font-extrabold text-blue-600">₱<span id="total_display">0</span></span>
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 class="text-xs font-bold text-gray-500 uppercase tracking-wider mb-4">Reservation Timeline</h3>
                                <div class="relative flex items-center justify-between px-4">
                                    <div class="absolute left-4 right-4 h-1 bg-gray-200 top-1/2 -translate-y-1/2 z-0"></div>
                                    <div id="timeline_progress_bar" class="absolute left-4 h-1 bg-blue-500 top-1/2 -translate-y-1/2 z-0 transition-all duration-500 <?= $is_submitted ? 'w-full' : 'w-0'; ?>"></div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full bg-blue-600 border-4 border-white shadow"></div>
                                        <span class="text-xs font-semibold text-blue-600 mt-1">Draft</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Processing</span>
                                    </div>
                                    <div class="z-10 flex flex-col items-center">
                                        <div class="w-5 h-5 rounded-full border-4 border-white shadow bg-gray-300 <?= $is_submitted ? 'bg-blue-600' : 'bg-gray-300'; ?>"></div>
                                        <span class="text-xs mt-1 <?= $is_submitted ? 'text-blue-600 font-semibold' : 'text-gray-400 font-medium'; ?>">Submitted</span>
                                    </div>
                                </div>
                            </div>

                            <div class="space-y-3 pt-2">
                                <button type="submit" name="submit_reservation" class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-3 px-4 rounded-xl shadow-md transition flex items-center justify-center space-x-2">
                                    <i class="fa-regular fa-circle-check"></i><span>Submit Reservation</span>
                                </button>
                                <button type="button" onclick="openCancellationModal()" class="w-full text-center text-sm text-gray-500 hover:text-red-500 transition font-medium py-1 block">
                                    <i class="fa-solid fa-xmark mr-1"></i> Cancel pending reservation
                                </button>
                            </div>
                        </div>
                    </div>
                                    </div>
                                    </div>
                                    </div>
                                     </div>
                           <!-- =====================================================
     MY RESERVATIONS & STATUS
     ===================================================== -->

<div class="mt-6 border border-gray-200 rounded-xl overflow-hidden bg-white">

    <!-- Section Header -->
    <div class="px-5 py-4 border-b border-gray-200 flex items-center justify-between">

        <div>
            <h2 class="text-lg font-bold text-slate-800">
                My Reservations & Status
            </h2>

            <p class="text-xs text-gray-500 mt-1">
                View and track the status of your reservations in real-time.
            </p>
        </div>

        <div class="border border-gray-200 rounded-lg px-3 py-2 text-xs font-semibold text-slate-700">
            All Reservations
        </div>

    </div>


    <!-- Reservation List -->

  <div class="reservation-list p-4 space-y-3">

        <?php if (!empty($my_reservations)): ?>

            <?php foreach ($my_reservations as $reservation): ?>

                <?php

                $status = $reservation['status'];

                if ($status === 'Confirmed') {

                    $status_class = 'bg-green-100 text-green-700';
                    $status_icon = 'fa-circle-check';
                    $status_title = 'Confirmed';
                    $status_message = 'Your reservation has been confirmed by the manager.';

                } elseif ($status === 'Rejected') {

                    $status_class = 'bg-red-100 text-red-700';
                    $status_icon = 'fa-circle-xmark';
                    $status_title = 'Rejected';
                    $status_message = 'Your reservation was rejected by the manager.';

                } else {

                    $status_class = 'bg-amber-100 text-amber-700';
                    $status_icon = 'fa-clock';
                    $status_title = 'Pending Confirmation';
                    $status_message = 'Waiting for manager review.';

                }

                ?>

                <div class="border border-gray-200 rounded-xl p-4 hover:shadow-sm transition">

                    <div class="grid grid-cols-1 lg:grid-cols-4 gap-4 items-center">

                        <!-- Reservation Information -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <div class="flex items-center gap-2 mb-2">

                                <span class="text-base font-extrabold text-blue-600">
                                    <?= htmlspecialchars($reservation['reservation_code']); ?>
                                </span>

                            </div>

                            <div class="space-y-1 text-xs text-gray-600">

                              <div>

    <?php if ($reservation['delivery_method'] === 'Pickup'): ?>

        <i class="fa-solid fa-shop mr-2 text-gray-400"></i>

        <span class="font-medium">
            Pickup Date:
        </span>

        <?php if (!empty($reservation['reservation_date'])): ?>

            <?= date(
                'M d, Y (D)',
                strtotime($reservation['reservation_date'])
            ); ?>

        <?php else: ?>

            <span class="text-gray-400">
                Not selected
            </span>

        <?php endif; ?>


    <?php elseif ($reservation['delivery_method'] === 'Delivery'): ?>

        <i class="fa-solid fa-truck mr-2 text-gray-400"></i>

        <span class="font-medium">
            Delivery Date:
        </span>

        <?php if (!empty($reservation['reservation_date'])): ?>

            <?= date(
                'M d, Y (D)',
                strtotime($reservation['reservation_date'])
            ); ?>

        <?php else: ?>

            <span class="text-amber-600 font-medium">
                To be scheduled by farm
            </span>

        <?php endif; ?>

    <?php endif; ?>


    <div class="text-xs text-gray-500 mt-1">

        <i class="fa-regular fa-clock mr-1"></i>

        Reserved On:

        <?= date(
            'M d, Y h:i A',
            strtotime($reservation['reserved_at'])
        ); ?>

    </div>

</div>

                                <div>
                                    <?php if ($reservation['delivery_method'] === 'Delivery'): ?>

                                        <i class="fa-solid fa-truck mr-2 text-gray-400"></i>

                                    <?php else: ?>

                                        <i class="fa-solid fa-shop mr-2 text-gray-400"></i>

                                    <?php endif; ?>

                                    <?= htmlspecialchars($reservation['delivery_method']); ?>
                                </div>

                            </div>

                        </div>


                        <!-- Items Ordered -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Items Ordered
                            </p>

                            <div class="flex flex-wrap gap-2">

                                <?php foreach ($reservation['items'] as $item): ?>

                                    <span class="px-2.5 py-1 bg-blue-50 text-blue-700 rounded-md text-xs font-semibold">

                                        <?= htmlspecialchars($item['egg_type']); ?>
                                        x <?= intval($item['quantity']); ?>

                                    </span>

                                <?php endforeach; ?>

                            </div>

                            <p class="text-xs text-gray-500 mt-3">

                                Total Quantity:
                                <strong>
                                    <?= intval($reservation['total_quantity']); ?>
                                </strong>
                                tray(s)

                            </p>

                        </div>


                        <!-- Total Price -->
                        <div class="lg:border-r lg:border-gray-200 lg:pr-4">

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Total Price
                            </p>

                            <p class="text-xl font-extrabold text-blue-600">

                                ₱<?= number_format(
                                    $reservation['total_price'],
                                    2
                                ); ?>

                            </p>

                        </div>


                        <!-- Manager Confirmation -->
                        <div>

                            <p class="text-[10px] font-bold uppercase tracking-wider text-gray-500 mb-2">
                                Manager Confirmation
                            </p>

                            <div class="flex items-start gap-2">

                                <i class="fa-solid <?= $status_icon; ?> mt-1 <?= 
                                    $status === 'Confirmed'
                                    ? 'text-green-500'
                                    : ($status === 'Rejected'
                                        ? 'text-red-500'
                                        : 'text-amber-500');
                                ?>"></i>

                                <div>

                                    <div class="font-bold text-sm
                                        <?= $status === 'Confirmed'
                                            ? 'text-green-600'
                                            : ($status === 'Rejected'
                                                ? 'text-red-600'
                                                : 'text-amber-600');
                                        ?>">

                                        <?= htmlspecialchars($status_title); ?>

                                    </div>

                                    <p class="text-xs text-gray-500 mt-1">
                                        <?= htmlspecialchars($status_message); ?>
                                    </p>

                                </div>

                            </div>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php else: ?>

            <!-- No Reservations -->
            <div class="py-10 text-center text-gray-400">

                <i class="fa-regular fa-calendar-xmark text-3xl mb-3"></i>

                <p class="text-sm font-medium">
                    No reservations yet.
                </p>

                <p class="text-xs mt-1">
                    Your submitted reservations will appear here.
                </p>

            </div>

        <?php endif; ?>

    </div>


    <!-- Information Footer -->

    <?php if (!empty($my_reservations)): ?>

        <div class="px-5 py-3 bg-blue-50 border-t border-blue-100 text-xs text-blue-700">

            <i class="fa-solid fa-circle-info mr-2"></i>

            You will receive a notification once your reservation status is updated by the manager.

        </div>

    <?php endif; ?>

</div>         
                </form>
            </div>
        </div>
   </div>

    <!-- Modals -->
    <div id="success_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 text-center space-y-4">
            <div class="w-16 h-16 bg-green-100 text-green-600 rounded-full flex items-center justify-center mx-auto text-3xl"><i class="fa-solid fa-circle-check"></i></div>
            <div><h3 class="text-xl font-bold text-slate-800">Success!</h3><p class="text-sm text-gray-500"><?= htmlspecialchars($message); ?></p></div>
            <button type="button" onclick="closeSuccessModal()" class="w-full bg-green-500 text-white font-semibold py-2 rounded-lg text-sm transition">Great, Thank You!</button>
        </div>
    </div>

    <div id="cancellation_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
        <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-md w-full p-6 space-y-4">
            <div class="flex items-center space-x-3 text-amber-500"><i class="fa-solid fa-triangle-exclamation text-2xl"></i><h3 class="text-lg font-bold text-slate-800">Cancel Reservation?</h3></div>
            <p class="text-sm text-gray-500">Are you sure you want to cancel? All bundle items will be cleared.</p>
            <div class="flex justify-end space-x-3"><button type="button" onclick="closeCancellationModal()" class="bg-gray-100 px-4 py-2 rounded-lg text-sm">Keep Editing</button><button type="button" onclick="executeCancellation()" class="bg-red-500 text-white px-4 py-2 rounded-lg text-sm">Yes, Cancel</button></div>
        </div>
    </div>

    <!-- Interactive Logout Confirmation Modal -->
<div id="logout_modal" class="hidden fixed inset-0 bg-slate-900/50 backdrop-blur-sm flex items-center justify-center z-[100] p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-gray-100 max-w-sm w-full p-6 space-y-4 transform transition-all">
        <div class="flex items-center space-x-3 text-red-500">
            <div class="w-10 h-10 bg-red-100 rounded-full flex items-center justify-center text-red-600">
                <i class="fa-solid fa-right-from-bracket text-base"></i>
            </div>
            <h3 class="text-lg font-bold text-slate-800">Confirm Logout</h3>
        </div>
        <p class="text-sm text-gray-500">Are you sure you want to end your session? You will need to log back in to manage reservations.</p>
        <div class="flex justify-end space-x-3 pt-2">
            <button type="button" onclick="closeLogoutModal()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-4 py-2 rounded-lg text-sm font-semibold transition">
                Stay Logged In
            </button>
            <a href="logout.php" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg text-sm font-semibold text-center transition">
                Yes, Logout
            </a>
        </div>
    </div>
</div>

    <script>
  
/* =========================================================
   CUSTOMER HEADER LIVE TIME
========================================================= */

function updateCustomerLiveTime() {

    var timeElement =
        document.getElementById('customer_live_time');

    if (!timeElement) {
        return;
    }

    var now = new Date();

    var hours = now.getHours();
    var minutes = now.getMinutes();
    var seconds = now.getSeconds();

    var ampm = hours >= 12 ? 'PM' : 'AM';

    hours = hours % 12;

    hours = hours
        ? hours
        : 12;

    hours = String(hours).padStart(2, '0');
    minutes = String(minutes).padStart(2, '0');
    seconds = String(seconds).padStart(2, '0');

    timeElement.innerText =
        hours + ':' +
        minutes + ':' +
        seconds + ' ' +
        ampm;
}


updateCustomerLiveTime();

setInterval(
    updateCustomerLiveTime,
    1000
);


        function openLogoutModal() {
    document.getElementById('logout_modal').classList.remove('hidden');
}

function closeLogoutModal() {
    document.getElementById('logout_modal').classList.add('hidden');
}

// Optional: Close the modal if the user clicks anywhere outside of the white modal container box
window.addEventListener('click', function(event) {
    const logoutModal = document.getElementById('logout_modal');
    if (event.target === logoutModal) {
        closeLogoutModal();
    }
});


        const priceList = { 'Extra Small': 140, 'Small': 150, 'Medium': 175, 'Large': 195, 'Extra Large': 235, 'Jumbo': 255, 'Super Jumbo': 270, 'Double Yolk': 320 };
        let bundleItems = [];
        let addressLookupTimeout = null;

        // Leaflet Setup
        const map = L.map('map').setView([14.5995, 120.9842], 13);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png').addTo(map);
        const marker = L.marker([14.5995, 120.9842], { draggable: true }).addTo(map);

        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(res => res.json()).then(data => {
                    if (data && data.display_name) {
                        document.getElementById('delivery_address_input').value = data.display_name;
                        document.getElementById('summary_address_display').innerText = data.display_name;
                    }
                });
        }
        map.on('click', e => { marker.setLatLng(e.latlng); reverseGeocode(e.latlng.lat, e.latlng.lng); });
        marker.on('dragend', () => { reverseGeocode(marker.getLatLng().lat, marker.getLatLng().lng); });

        function handleAddressTyping(val) {
            document.getElementById('summary_address_display').innerText = val || 'None';
            clearTimeout(addressLookupTimeout);
            if (!val.trim()) return;
            addressLookupTimeout = setTimeout(() => {
                fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(val)}&limit=1`)
                    .then(res => res.json()).then(data => {
                        if (data.length > 0) {
                            map.setView([data[0].lat, data[0].lon], 16);
                            marker.setLatLng([data[0].lat, data[0].lon]);
                        }
                    });
            }, 600);
        }

        // Bundle Logic Operations
        function setEgg(val) {
            document.getElementById('egg_type_select').value = val;
            document.querySelectorAll('.egg-btn').forEach(btn => {
                btn.className = btn.getAttribute('data-egg') === val 
                    ? "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-blue-500 bg-blue-50"
                    : "egg-btn flex flex-col items-center p-2 border rounded-lg transition-all border-gray-200 hover:bg-gray-50";
            });
        }

        function addItemToBundle() {
            const size = document.getElementById('egg_type_select').value;
            const qty = parseInt(document.getElementById('quantity_input').value);

            if (!size || isNaN(qty) || qty <= 0) {
                alert('Please select a valid size and quantity.');
                return;
            }

            const existingItem = bundleItems.find(item => item.size === size);
            if (existingItem) {
                existingItem.qty += qty;
            } else {
                bundleItems.push({ size, qty, price: priceList[size] });
            }

            document.getElementById('quantity_input').value = '';
            updateLiveSummary();
        }

        function removeBundleItem(index) {
            bundleItems.splice(index, 1);
            updateLiveSummary();
        }

        function updateLiveSummary() {
            const tbody = document.getElementById('bundle_table_body');
            const hiddenInputs = document.getElementById('hidden_bundle_inputs');
            tbody.innerHTML = '';
            hiddenInputs.innerHTML = '';

            let grandTotal = 0;

            if (bundleItems.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" class="p-4 text-center text-gray-400 text-xs">No items added to the bundle yet.</td></tr>`;
            } else {
                bundleItems.forEach((item, index) => {
                    const rowTotal = item.price * item.qty;
                    grandTotal += rowTotal;

                    tbody.innerHTML += `
                        <tr>
                            <td class="p-3 font-medium text-gray-700">${item.size}</td>
                            <td class="p-3 text-center font-bold text-gray-600">${item.qty}</td>
                            <td class="p-3 text-right font-semibold text-gray-800">₱${rowTotal.toLocaleString()}</td>
                            <td class="p-3 text-center">
                                <button type="button" onclick="removeBundleItem(${index})" class="text-red-400 hover:text-red-600"><i class="fa-solid fa-trash-can"></i></button>
                            </td>
                        </tr>`;

                    hiddenInputs.innerHTML += `
                        <input type="hidden" name="egg_types[]" value="${item.size}">
                        <input type="hidden" name="quantities[]" value="${item.qty}">`;
                });
            }

           document.getElementById('total_display').innerText =
    grandTotal.toLocaleString();


const method =
    document.getElementById('delivery_method_input').value;

const date =
    document.getElementById('reservation_date_input').value;


if (method === 'Pickup') {

    document.getElementById('summary_date').innerText =
        date || 'Select pickup date';

}
else if (method === 'Delivery') {

    document.getElementById('summary_date').innerText =
        'To be scheduled by farm';

}
else {

    document.getElementById('summary_date').innerText =
        'None selected';

}
        }

        function setMethod(method) {

    const methodInput = document.getElementById('delivery_method_input');
    const pickupSection = document.getElementById('pickup_date_section');
    const deliveryInfo = document.getElementById('delivery_schedule_info');
    const dateInput = document.getElementById('reservation_date_input');

    const summaryMethod = document.getElementById('summary_method');
    const summaryDate = document.getElementById('summary_date');

    const btnDelivery = document.getElementById('btn-delivery');
    const btnPickup = document.getElementById('btn-pickup');

    const methodDescription = document.getElementById('method_description');


    // Save selected method
    methodInput.value = method;


    // =====================================================
    // DELIVERY
    // =====================================================

    if (method === 'Delivery') {

        // Hide pickup date
        pickupSection.classList.add('hidden');

        // Show delivery information
        deliveryInfo.classList.remove('hidden');

        // Date is NOT required
        dateInput.required = false;

        // Clear date
        dateInput.value = '';

        // Update summary
        summaryMethod.innerText = 'Delivery';
        summaryDate.innerText = 'To be scheduled by farm';

        // Description
        methodDescription.innerHTML = `
            <div class="border border-blue-100 bg-blue-50 rounded-lg p-3">
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-truck text-blue-500 mt-0.5"></i>

                    <div>
                        <p class="text-xs font-bold text-blue-700">
                            Delivery Selected
                        </p>

                        <p class="text-[10px] text-blue-600 mt-1">
                            The farm manager will assign your delivery date
                            based on availability.
                        </p>
                    </div>
                </div>
            </div>
        `;

    }


    // =====================================================
    // PICKUP
    // =====================================================

    else if (method === 'Pickup') {

        // Show pickup date
        pickupSection.classList.remove('hidden');

        // Hide delivery information
        deliveryInfo.classList.add('hidden');

        // Date IS required
        dateInput.required = true;

        // Update summary
        summaryMethod.innerText = 'Pickup';

        if (dateInput.value) {
            summaryDate.innerText = dateInput.value;
        } else {
            summaryDate.innerText = 'Select pickup date';
        }

        // Description
        methodDescription.innerHTML = `
            <div class="border border-green-100 bg-green-50 rounded-lg p-3">
                <div class="flex items-start gap-2">
                    <i class="fa-solid fa-shop text-green-500 mt-0.5"></i>

                    <div>
                        <p class="text-xs font-bold text-green-700">
                            Pickup Selected
                        </p>

                        <p class="text-[10px] text-green-600 mt-1">
                            Please select your preferred pickup date
                            from the farm.
                        </p>
                    </div>
                </div>
            </div>
        `;
    }


    // =====================================================
    // BUTTON STYLING
    // =====================================================

    btnDelivery.className =
        method === 'Delivery'
        ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-blue-500 bg-blue-50 text-blue-600"
        : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";

    btnPickup.className =
        method === 'Pickup'
        ? "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-blue-500 bg-blue-50 text-blue-600"
        : "p-3 border rounded-lg flex flex-col items-center justify-center space-y-1 border-gray-200 text-gray-500";
}

      function validateForm() {

    const contactNumber =
        document.getElementById('contact_input').value;

    const deliveryMethod =
        document.getElementById('delivery_method_input').value;

    const reservationDate =
        document.getElementById('reservation_date_input').value;


    // =====================================================
    // CONTACT NUMBER
    // =====================================================

    if (contactNumber.length !== 11) {

        alert('Contact number must be exactly 11 digits.');

        document.getElementById('contact_input').focus();

        return false;
    }


    if (!/^09[0-9]{9}$/.test(contactNumber)) {

        alert(
            'Please enter a valid Philippine mobile number starting with 09.'
        );

        document.getElementById('contact_input').focus();

        return false;
    }


    // =====================================================
    // ITEMS
    // =====================================================

    if (bundleItems.length === 0) {

        alert(
            'Please add at least one item row to your bundle layout.'
        );

        return false;
    }


    // =====================================================
    // DELIVERY METHOD
    // =====================================================

    if (!deliveryMethod) {

        alert(
            'Please choose either Delivery or Pickup.'
        );

        return false;
    }


    // =====================================================
    // PICKUP DATE
    // =====================================================

    if (deliveryMethod === 'Pickup' && !reservationDate) {

        alert(
            'Please select your preferred pickup date.'
        );

        document.getElementById('reservation_date_input').focus();

        return false;
    }


    // =====================================================
    // DELIVERY
    // =====================================================

    if (deliveryMethod === 'Delivery') {

        // Delivery should NOT have a customer-selected date.
        document.getElementById('reservation_date_input').value = '';
    }


    return true;
}

        function openSuccessModal() { document.getElementById('success_modal').classList.remove('hidden'); }
        function closeSuccessModal() { document.getElementById('success_modal').classList.add('hidden'); }
        function openCancellationModal() { document.getElementById('cancellation_modal').classList.remove('hidden'); }
        function closeCancellationModal() { document.getElementById('cancellation_modal').classList.add('hidden'); }

        function executeCancellation() {
            closeCancellationModal();
            fetch('?action=log_cancellation', { method: 'POST' })
            .then(res => res.json()).then(data => { if(data.status === 'success') location.reload(); });
        }

        function toggleNotifications(e) { e.stopPropagation(); document.getElementById('notification_dropdown').classList.toggle('hidden'); }
        document.addEventListener('click', () => document.getElementById('notification_dropdown').classList.add('hidden'));

        <?php if (!empty($message)): ?>
            document.addEventListener('DOMContentLoaded', openSuccessModal);
        <?php endif; ?>


     function matchReservationStepHeights() {

    const stepCards = document.querySelectorAll('.step-card');
    const step1Card = document.querySelector('.step-1-card');
    const scrollAreas = document.querySelectorAll('.step-scroll');

    if (!step1Card || stepCards.length === 0) {
        return;
    }


    /* =====================================================
       MOBILE / TABLET
       ===================================================== */

    if (window.innerWidth < 1024) {

        stepCards.forEach(function(card) {
            card.style.height = '';
        });

        scrollAreas.forEach(function(area) {
            area.style.height = '';
            area.style.maxHeight = '';
            area.style.overflowY = 'visible';
        });

        return;
    }


    /* =====================================================
       IMPORTANT:
       CLEAR ALL PREVIOUS INLINE HEIGHTS FIRST
       ===================================================== */

    stepCards.forEach(function(card) {
        card.style.height = '';
    });

    scrollAreas.forEach(function(area) {
        area.style.height = '';
        area.style.maxHeight = '';
        area.style.overflowY = 'visible';
    });


    /* =====================================================
       GET STEP 1'S TRUE NATURAL HEIGHT

       Step 1 is NOT allowed to inherit the height
       of Step 2 or Step 3.
       ===================================================== */

    const step1Height = step1Card.getBoundingClientRect().height;


    /* =====================================================
       APPLY STEP 1 HEIGHT TO STEP 2 & STEP 3
       ===================================================== */

    stepCards.forEach(function(card) {
        card.style.height = step1Height + 'px';
    });


    /* =====================================================
       STEP 2 & STEP 3 = INTERNAL SCROLL

       Their outer cards have the exact same height
       as Step 1, while their content can scroll.
       ===================================================== */

    scrollAreas.forEach(function(area) {

        area.style.height = 'auto';
        area.style.flex = '1 1 auto';
        area.style.minHeight = '0';
        area.style.overflowY = 'auto';

    });

}


/* Run after page finishes loading */
window.addEventListener('load', function() {
    setTimeout(function() {
        matchReservationStepHeights();
    }, 300);
});


/* Recalculate when browser is resized */
window.addEventListener('resize', function() {
    matchReservationStepHeights();
});


function copyReservationCode() {

    var reservationCode = <?php echo json_encode($reservation_code); ?>;

    if (navigator.clipboard) {

        navigator.clipboard.writeText(reservationCode).then(function() {

            alert('Reservation ID copied: ' + reservationCode);

        });

    } else {

        var tempInput = document.createElement('input');
        tempInput.value = reservationCode;

        document.body.appendChild(tempInput);
        tempInput.select();

        document.execCommand('copy');

        document.body.removeChild(tempInput);

        alert('Reservation ID copied: ' + reservationCode);
    }
}


function updateLiveTime() {

    var now = new Date();

    var hours = now.getHours();
    var minutes = now.getMinutes();
    var seconds = now.getSeconds();

    var ampm = hours >= 12 ? "PM" : "AM";

    hours = hours % 12;

    if (hours === 0) {
        hours = 12;
    }

    hours = hours < 10 ? "0" + hours : hours;
    minutes = minutes < 10 ? "0" + minutes : minutes;
    seconds = seconds < 10 ? "0" + seconds : seconds;

    var timeString =
        hours + ":" +
        minutes + ":" +
        seconds + " " +
        ampm;

    var liveTime =
        document.getElementById("live-time");

    if (liveTime) {
        liveTime.innerHTML = timeString;
    }
}

updateLiveTime();

setInterval(
    updateLiveTime,
    1000
);


    </script>
</body>
</html>