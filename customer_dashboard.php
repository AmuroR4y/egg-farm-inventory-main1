<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

require 'db.php';

/* =========================================================
   SECURITY CHECK
========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

if (!isset($_SESSION['role']) || $_SESSION['role'] != 'customer') {
    header("Location: login.php");
    exit();
}


/* =========================================================
   CUSTOMER INFORMATION
========================================================= */

$customer_id = $_SESSION['user_id'];

$customer_name = isset($_SESSION['customer_name'])
    ? $_SESSION['customer_name']
    : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer');


/* =========================================================
   TOTAL RESERVATIONS
========================================================= */

$total_reservations = 0;

$reservation_count_sql = "
    SELECT COUNT(DISTINCT reservation_code) AS total
    FROM reservations
    WHERE customer_id = ?
";

$stmt = $conn->prepare($reservation_count_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $total_reservations = $row['total'];
    }

    $stmt->close();
}


/* =========================================================
   TOTAL TRAYS ORDERED
========================================================= */

$total_trays = 0;

$total_trays_sql = "
    SELECT SUM(quantity) AS total
    FROM reservations
    WHERE customer_id = ?
";

$stmt = $conn->prepare($total_trays_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result && $row = $result->fetch_assoc()) {
        $total_trays = $row['total'] ? $row['total'] : 0;
    }

    $stmt->close();
}


/* =========================================================
   AVAILABLE EGG STOCKS
========================================================= */

$egg_stocks = array();

$stock_sql = "
    SELECT 
        egg_size,
        available_stock
    FROM egg_inventory
    ORDER BY 
        FIELD(
            egg_size,
            'XS',
            'Small',
            'Medium',
            'Large',
            'XL',
            'Jumbo',
            'Super Jumbo',
            'Double Yolk'
        )
";

$stock_result = $conn->query($stock_sql);

if ($stock_result) {
    while ($stock_row = $stock_result->fetch_assoc()) {
        $egg_stocks[] = $stock_row;
    }
}


/* =========================================================
   RECENT CUSTOMER ACTIVITY
========================================================= */

$recent_activities = array();

$activity_sql = "
    SELECT
        reservation_code,
        egg_size,
        quantity,
        status,
        reserved_at
    FROM reservations
    WHERE customer_id = ?
    ORDER BY reserved_at DESC
    LIMIT 5
";

$stmt = $conn->prepare($activity_sql);

if ($stmt) {
    $stmt->bind_param("i", $customer_id);
    $stmt->execute();

    $result = $stmt->get_result();

    if ($result) {
        while ($activity_row = $result->fetch_assoc()) {
            $recent_activities[] = $activity_row;
        }
    }

    $stmt->close();
}


/* =========================================================
   GREETING
========================================================= */

$current_hour = date('H');

if ($current_hour < 12) {
    $greeting = "Good morning";
} elseif ($current_hour < 18) {
    $greeting = "Good afternoon";
} else {
    $greeting = "Good evening";
}


/* =========================================================
   GET FIRST NAME
========================================================= */

$name_parts = explode(" ", trim($customer_name));
$first_name = $name_parts[0];


/* =========================================================
   STATUS CLASS
========================================================= */

function getActivityIcon($status)
{
    $status = strtolower($status);

    if ($status == 'confirmed') {
        return array(
            'icon' => 'fa-calendar-check',
            'class' => 'confirmed'
        );
    }

    if ($status == 'completed') {
        return array(
            'icon' => 'fa-circle-check',
            'class' => 'completed'
        );
    }

    if (
        $status == 'cancelled' ||
        $status == 'canceled' ||
        $status == 'rejected'
    ) {
        return array(
            'icon' => 'fa-circle-xmark',
            'class' => 'cancelled'
        );
    }

    if ($status == 'pending') {
        return array(
            'icon' => 'fa-clock',
            'class' => 'pending'
        );
    }

    return array(
        'icon' => 'fa-calendar',
        'class' => 'default'
    );
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

    <title>Customer Dashboard</title>

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css"
    >

 
<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    width: 100%;
    min-height: 100%;
}

body {
    min-height: 100vh;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f3f1eb;

    color: #3f4b45;
}


/* =========================================================
   MAIN CONTENT
========================================================= */

.main-content {

    margin-left: 312px;

    min-height: 100vh;

    padding: 18px;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );
}


/* =========================================================
   CUSTOMER DASHBOARD OUTER CARD
========================================================= */

.customer-dashboard-wrapper {

    width: 100%;

    min-height:
        calc(100vh - 36px);

    overflow: hidden;

    background: #ffffff;

    border:
        1px solid #dedbd2;

    border-radius: 22px;

    box-shadow:
        0 12px 35px
        rgba(
            80,
            72,
            55,
            0.10
        );

    padding: 0;
}


/* =========================================================
   CUSTOMER DASHBOARD HEADER
   SAME STYLE AS RESERVATION / MANAGER HEADER
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

.customer-page-title {

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

.customer-topbar-right {

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
   DATE AND LIVE TIME
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

    line-height:
        1.2;
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

    white-space:
        nowrap;
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
   DASHBOARD CONTENT
========================================================= */

.customer-dashboard-content {

    width:
        100%;

    padding:
        22px 28px 28px;
}


/* =========================================================
   DASHBOARD GREETING
========================================================= */

.dashboard-header {

    margin-bottom:
        24px;
}


.greeting-wrapper {

    display:
        flex;

    align-items:
        flex-start;

    gap:
        10px;
}


.greeting-icon {

    color:
        #315f4a;

    font-size:
        22px;

    margin-top:
        4px;
}


.greeting-text-container {

    display:
        flex;

    flex-direction:
        column;
}


.dashboard-header h1 {

    margin:
        0;

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size:
        25px;

    font-weight:
        700;

    color:
        #315f4a;

    letter-spacing:
        0.5px;

    line-height:
        1.1;
}


.dashboard-header h1 span {

    font-size:
        25px;
}


.dashboard-header p {

    margin:
        5px 0 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size:
        13px;

    font-weight:
        400;

    color:
        #77817b;

    letter-spacing:
        0.2px;
}


/* =========================================================
   SUMMARY CARDS
========================================================= */

.summary-grid {

    display:
        grid;

    grid-template-columns:
        repeat(3, 1fr);

    gap:
        14px;

    margin-bottom:
        14px;
}


.summary-card {

    min-height:
        98px;

    padding:
        15px;

    border-radius:
        10px;

    background:
        linear-gradient(
            135deg,
            #ffffff 0%,
            #f8f8f4 100%
        );

    border:
        1px solid #e2dfd7;

    box-shadow:
        0 3px 10px
        rgba(
            70,
            65,
            55,
            0.06
        );

    display:
        flex;

    align-items:
        center;

    transition:
        0.2s ease;
}


.summary-card:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 18px
        rgba(
            70,
            65,
            55,
            0.10
        );
}


/* =========================================================
   SUMMARY ICON
========================================================= */

.summary-icon {

    width:
        42px;

    height:
        42px;

    min-width:
        42px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        18px;

    margin-right:
        12px;
}


.summary-icon.account {

    background:
        #e9f1e6;

    color:
        #527d59;
}


.summary-icon.reservation {

    background:
        #eee9f7;

    color:
        #70558c;
}


.summary-icon.trays {

    background:
        #e6f0f4;

    color:
        #3e7182;
}


.summary-content {

    min-width:
        0;
}


.summary-content span {

    display:
        block;

    font-size:
        13px;

    font-weight:
        700;

    color:
        #59635c;
}


.summary-content h2 {

    margin:
        6px 0 0;

    font-size:
        24px;

    color:
        #38433d;

    font-weight:
        700;
}


.summary-content small {

    display:
        block;

    margin-top:
        6px;

    font-size:
        11px;

    color:
        #8b9089;
}


/* =========================================================
   MAIN DASHBOARD GRID
========================================================= */

.dashboard-grid {

    display:
        grid;

    grid-template-columns:
        1.2fr 0.9fr;

    gap:
        14px;
}


/* =========================================================
   DASHBOARD CARD
========================================================= */

.dashboard-card {

    min-width:
        0;

    overflow:
        hidden;

    border-radius:
        10px;

    border:
        1px solid #e2dfd7;

    background:
        #ffffff;

    box-shadow:
        0 3px 10px
        rgba(
            70,
            65,
            55,
            0.05
        );
}


/* =========================================================
   CARD HEADER
========================================================= */

.card-header {

    min-height:
        50px;

    padding:
        0 16px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    border-bottom:
        1px solid #e9e6df;
}


.card-title {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;
}


.card-title-icon {

    width:
        30px;

    height:
        30px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    border-radius:
        50%;

    background:
        #eef3ed;

    color:
        #527d59;

    font-size:
        14px;
}


.card-title-text h2 {

    margin:
        0;

    font-size:
        14px;

    font-weight:
        700;

    color:
        #3f4b45;
}


.card-title-text p {

    margin:
        4px 0 0;

    font-size:
        10px;

    color:
        #8a9089;
}


.view-all-btn {

    font-size:
        11px;

    font-weight:
        700;

    color:
        #527d59;

    text-decoration:
        none;
}


.view-all-btn:hover {

    text-decoration:
        underline;
}


/* =========================================================
   STOCK TABLE
========================================================= */

.stock-table-container {

    width:
        100%;

    overflow-x:
        auto;

    padding:
        10px 12px;
}


.stock-table {

    width:
        100%;

    border-collapse:
        collapse;

    font-size:
        11px;
}


.stock-table thead {

    background:
        #edf0eb;
}


.stock-table th {

    padding:
        9px 8px;

    color:
        #627068;

    font-size:
        11px;

    font-weight:
        700;

    text-align:
        left;

    white-space:
        nowrap;
}


.stock-table td {

    padding:
        12px 10px;

    color:
        #59635c;

    border-bottom:
        1px solid #eceae4;

    font-size:
        13px;
}


.stock-table tbody tr:last-child td {

    border-bottom:
        none;
}


.stock-table tbody tr:hover {

    background:
        #fafaf7;
}


.stock-number {

    font-size:
        17px;

    font-weight:
        700;

    color:
        #46554d;
}


.stock-status {

    display:
        inline-flex;

    align-items:
        center;

    gap:
        7px;

    font-size:
        11px;

    color:
        #527d59;

    font-weight:
        700;
}


.status-dot {

    width:
        7px;

    height:
        7px;

    border-radius:
        50%;

    background:
        #527d59;
}


/* =========================================================
   STOCK FOOTER
========================================================= */

.stock-footer {

    padding:
        12px 14px 16px;
}


.stock-info {

    display:
        flex;

    align-items:
        center;

    gap:
        8px;

    color:
        #8a9089;

    font-size:
        10px;

    margin-bottom:
        14px;
}


.stock-info i {

    color:
        #527d59;
}


.reservation-button {

    width:
        100%;

    max-width:
        220px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    gap:
        9px;

    padding:
        12px 18px;

    background:
        linear-gradient(
            135deg,
            #527d59,
            #315f4a
        );

    color:
        #ffffff;

    border-radius:
        8px;

    text-decoration:
        none;

    font-size:
        13px;

    font-weight:
        600;

    box-shadow:
        0 5px 12px
        rgba(
            49,
            95,
            74,
            0.18
        );

    transition:
        transform 0.2s ease,
        box-shadow 0.2s ease;
}


.reservation-button:hover {

    transform:
        translateY(-2px);

    box-shadow:
        0 8px 18px
        rgba(
            49,
            95,
            74,
            0.25
        );
}


/* =========================================================
   RECENT ACTIVITY
========================================================= */

.activity-list {

    padding:
        5px 14px 10px;

    max-height:
        390px;

    overflow-y:
        auto;
}


.activity-list::-webkit-scrollbar {

    width:
        5px;
}


.activity-list::-webkit-scrollbar-thumb {

    background:
        #d9ddd8;

    border-radius:
        10px;
}


.activity-item {

    min-height:
        48px;

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

    border-bottom:
        1px solid #eceae4;
}


.activity-item:last-child {

    border-bottom:
        none;
}


.activity-icon {

    width:
        29px;

    height:
        29px;

    min-width:
        29px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    font-size:
        12px;
}


.activity-icon.confirmed {

    background:
        #e9f1e6;

    color:
        #527d59;
}


.activity-icon.completed {

    background:
        #e6f0f4;

    color:
        #3e7182;
}


.activity-icon.cancelled {

    background:
        #fbe9e6;

    color:
        #c65b4e;
}


.activity-icon.pending {

    background:
        #fff2df;

    color:
        #a87932;
}


.activity-icon.default {

    background:
        #edf1f2;

    color:
        #54707b;
}


.activity-content {

    flex:
        1;

    min-width:
        0;
}


.activity-content h4 {

    margin:
        0 0 4px;

    font-size:
        12px;

    color:
        #4d5851;

    font-weight:
        700;
}


.activity-content p {

    margin:
        0;

    font-size:
        10px;

    color:
        #8a9089;
}


.activity-time {

    font-size:
        10px;

    color:
        #969a94;

    white-space:
        nowrap;
}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    padding:
        35px 15px;

    text-align:
        center;

    color:
        #92968f;
}


.empty-state i {

    font-size:
        35px;

    margin-bottom:
        12px;

    color:
        #c4c9c4;
}


.empty-state p {

    margin:
        0;

    font-size:
        11px;
}


/* =========================================================
   SUPPORT SECTION
========================================================= */

.support-card {

    margin-top:
        14px;

    min-height:
        74px;

    position:
        relative;

    overflow:
        hidden;

    display:
        flex;

    align-items:
        center;

    padding:
        0 24px;

    background:
        linear-gradient(
            90deg,
            #fbfaf7,
            #f4f2eb
        );

    border:
        1px solid #e2dfd7;

    border-radius:
        10px;

    box-shadow:
        0 3px 10px
        rgba(
            70,
            65,
            55,
            0.05
        );
}


.support-icon {

    width:
        42px;

    height:
        42px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    background:
        #eef3ed;

    color:
        #527d59;

    margin-right:
        14px;

    font-size:
        18px;

    position:
        relative;

    z-index:
        2;

    flex-shrink:
        0;
}


.support-content {

    position:
        relative;

    z-index:
        2;
}


.support-content h3 {

    margin:
        0 0 5px;

    font-size:
        13px;

    color:
        #4d5851;
}


.support-content p {

    margin:
        0;

    font-size:
        11px;

    color:
        #8a9089;
}


/* =========================================================
   FARM DECORATION
========================================================= */

.farm-decoration {

    position:
        absolute;

    right:
        0;

    bottom:
        0;

    height:
        100%;

    width:
        55%;

    display:
        flex;

    justify-content:
        flex-end;

    align-items:
        flex-end;

    pointer-events:
        none;

    z-index:
        1;

    overflow:
        hidden;
}


.farm-decoration img {

    width:
        100%;

    height:
        auto;

    max-height:
        100%;

    object-fit:
        contain;

    object-position:
        right bottom;

    display:
        block;
}


/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1300px) {

    .summary-grid {

        grid-template-columns:
            repeat(2, 1fr);
    }

    .dashboard-grid {

        grid-template-columns:
            1fr;
    }
}


@media screen and (max-width: 1100px) {

    .main-content {

        margin-left:
            270px;

        width:
            calc(100% - 270px);
    }
}


@media screen and (max-width: 900px) {

    .main-content {

        margin-left:
            0;

        width:
            100%;

        padding:
            15px;
    }

    .customer-dashboard-wrapper {

        border-radius:
            18px;
    }

    .customer-account-details,
    .customer-dropdown-icon {

        display:
            none;
    }
}


@media screen and (max-width: 600px) {

    .customer-dashboard-content {

        padding:
            18px;
    }

    .summary-grid {

        grid-template-columns:
            1fr;
    }

    .dashboard-header h1 {

        font-size:
            22px;
    }

    .customer-dashboard-topbar {

        padding:
            0 18px;
    }
}

</style>


</head>


<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

<?php include('customer_panel.php'); ?>


<!-- =====================================================
     MAIN CONTENT
===================================================== -->

<div class="main-content">


    <div class="customer-dashboard-wrapper">


        <!-- =====================================================
             CUSTOMER DASHBOARD TOPBAR
        ===================================================== -->

     <div class="customer-dashboard-topbar">

    <div class="customer-page-title">
        Customer Dashboard
    </div>


    <div class="customer-topbar-right">


        <!-- NOTIFICATION -->

        <a
            href="customer_notifications.php"
            class="customer-top-notification"
            title="Notifications"
        >

            <i class="fa-regular fa-bell"></i>

        </a>


        <!-- DIVIDER -->

        <div class="customer-topbar-divider"></div>


        <!-- CUSTOMER PROFILE -->

        <a
            href="customer_profile.php"
            class="customer-top-profile"
        >


            <!-- AVATAR -->

            <div class="customer-avatar">

                <i class="fa-solid fa-user"></i>

            </div>


            <!-- CUSTOMER DETAILS -->

            <div class="customer-account-details">

                <span class="customer-top-name">
                    Customer
                </span>


                <div class="customer-date-time">

                    <span
                        class="customer-date"
                        id="customer-live-date"
                    >
                        <?php
                        echo date('F j, Y');
                        ?>
                    </span>


                    <span
                        class="customer-time"
                        id="customer-live-time"
                    >
                        <?php
                        echo date('h:i:s A');
                        ?>
                    </span>

                </div>

            </div>


            <!-- DROPDOWN -->

            <div class="customer-dropdown-icon">

                <i class="fa-solid fa-chevron-down"></i>

            </div>


        </a>

    </div>

</div>


        <!-- =====================================================
             DASHBOARD CONTENT
        ===================================================== -->

        <div class="customer-dashboard-content">


            <!-- =====================================================
                 DASHBOARD HEADER
            ===================================================== -->

            <div class="dashboard-header">


                <div class="greeting-wrapper">


                    <div class="greeting-icon">

                        <i
                            class="
                            fa-solid
                            fa-leaf
                            "
                        ></i>

                    </div>


                    <div class="greeting-text-container">


                        <h1>

                            <?php
                            echo $greeting;
                            ?>,

                            <?php
                            echo htmlspecialchars(
                                $first_name
                            );
                            ?>!

                            <span>👋</span>

                        </h1>


                        <p>

                            Here's a quick overview
                            of your account.

                        </p>


                    </div>


                </div>


            </div>


 

 

        <!-- =====================================================
             SUMMARY CARDS
        ===================================================== -->

        <div class="summary-grid">


            <!-- ACCOUNT TYPE -->

            <div class="summary-card">

                <div class="summary-icon account">

                    <i class="fa-regular fa-user"></i>

                </div>


                <div class="summary-content">

                    <span>Account Type</span>

                    <h2>Customer</h2>

                    <small>Regular Customer</small>

                </div>

            </div>


            <!-- TOTAL RESERVATIONS -->

            <div class="summary-card">

                <div class="summary-icon reservation">

                    <i class="fa-regular fa-calendar-check"></i>

                </div>


                <div class="summary-content">

                    <span>Total Reservations</span>

                    <h2>
                        <?php echo $total_reservations; ?>
                    </h2>

                    <small>All time</small>

                </div>

            </div>


            <!-- TRAYS ORDERED -->

            <div class="summary-card">

                <div class="summary-icon trays">

                    <i class="fa-solid fa-cube"></i>

                </div>


                <div class="summary-content">

                    <span>Trays Ordered</span>

                    <h2>
                        <?php echo $total_trays; ?>
                    </h2>

                    <small>All time</small>

                </div>

            </div>


        </div>


        <!-- =====================================================
             MAIN DASHBOARD CONTENT
        ===================================================== -->

        <div class="dashboard-grid">


            <!-- =====================================================
                 AVAILABLE EGG STOCKS
            ===================================================== -->

            <div class="dashboard-card">


                <div class="card-header">

                    <div class="card-title">

                        <div class="card-title-icon">

                            <i class="fa-solid fa-egg"></i>

                        </div>


                        <div class="card-title-text">

                            <h2>
                                Available Egg Stocks
                            </h2>

                            <p>
                                Real-time availability for all egg sizes.
                            </p>

                        </div>

                    </div>

                </div>


                <!-- STOCK TABLE -->

                <div class="stock-table-container">


                    <?php if (count($egg_stocks) > 0) { ?>


                        <table class="stock-table">


                            <thead>

                                <tr>

                                    <th>
                                        Egg Size
                                    </th>

                                    <th>
                                        Available Stock
                                    </th>

                                    <th>
                                        Unit
                                    </th>

                                    <th>
                                        Status
                                    </th>

                                </tr>

                            </thead>


                            <tbody>


                                <?php foreach ($egg_stocks as $stock) { ?>


                                    <?php

                                    $available_stock =
                                        isset($stock['available_stock'])
                                        ? $stock['available_stock']
                                        : 0;

                                    ?>


                                    <tr>

                                        <td>

                                            <strong>
                                                <?php
                                                echo htmlspecialchars(
                                                    $stock['egg_size']
                                                );
                                                ?>
                                            </strong>

                                        </td>


                                        <td>

                                            <span class="stock-number">

                                                <?php
                                                echo $available_stock;
                                                ?>

                                            </span>

                                        </td>


                                        <td>
                                            trays
                                        </td>


                                        <td>

                                            <?php if ($available_stock > 0) { ?>

                                                <span class="stock-status">

                                                    <span
                                                        class="status-dot"
                                                    ></span>

                                                    Available

                                                </span>

                                            <?php } else { ?>

                                                <span
                                                    style="
                                                        color:#c65b4e;
                                                        font-size:11px;
                                                        font-weight:500;
                                                    "
                                                >

                                                    <i
                                                        class="
                                                        fa-solid
                                                        fa-circle
                                                        "
                                                        style="
                                                            font-size:7px;
                                                        "
                                                    ></i>

                                                    Out of Stock

                                                </span>

                                            <?php } ?>

                                        </td>

                                    </tr>


                                <?php } ?>


                            </tbody>


                        </table>


                    <?php } else { ?>


                        <div class="empty-state">

                            <i
                                class="
                                fa-solid
                                fa-box-open
                                "
                            ></i>

                            <p>
                                No egg stocks are currently available.
                            </p>

                        </div>


                    <?php } ?>


                </div>


                <!-- STOCK FOOTER -->

                <div class="stock-footer">


                    <div class="stock-info">

                        <i
                            class="
                            fa-solid
                            fa-circle-info
                            "
                        ></i>

                        <span>
                            Stocks are updated in real-time.
                            Reserve now to secure your preferred stock.
                        </span>

                    </div>


                    <a
                        href="customer_reservation.php"
                        class="reservation-button"
                    >

                        <i
                            class="
                            fa-regular
                            fa-calendar-plus
                            "
                        ></i>

                        Make a Reservation

                    </a>


                </div>


            </div>


            <!-- =====================================================
                 RECENT ACTIVITY
            ===================================================== -->

            <div class="dashboard-card">


                <div class="card-header">


                    <div class="card-title">


                        <div class="card-title-icon">

                            <i
                                class="
                                fa-solid
                                fa-clock-rotate-left
                                "
                            ></i>

                        </div>


                        <div class="card-title-text">

                            <h2>
                                Recent Activity
                            </h2>

                        </div>


                    </div>


                    <a
                        href="customer_history.php"
                        class="view-all-btn"
                    >

                        View All

                    </a>


                </div>


                <div class="activity-list">


                    <?php if (count($recent_activities) > 0) { ?>


                        <?php
                        foreach (
                            $recent_activities
                            as $activity
                        ) {
                        ?>


                            <?php

                            $icon_data =
                                getActivityIcon(
                                    $activity['status']
                                );

                            ?>


                            <div class="activity-item">


                                <!-- ACTIVITY ICON -->

                                <div
                                    class="
                                    activity-icon
                                    <?php
                                    echo $icon_data['class'];
                                    ?>
                                    "
                                >

                                    <i
                                        class="
                                        fa-solid
                                        <?php
                                        echo $icon_data['icon'];
                                        ?>
                                        "
                                    ></i>

                                </div>


                                <!-- ACTIVITY CONTENT -->

                                <div class="activity-content">


                                    <h4>

                                        Reservation
                                        <?php
                                        echo htmlspecialchars(
                                            $activity[
                                                'reservation_code'
                                            ]
                                        );
                                        ?>

                                        <?php
                                        echo htmlspecialchars(
                                            strtolower(
                                                $activity['status']
                                            )
                                        );
                                        ?>

                                    </h4>


                                    <p>

                                        <?php

                                        if (
                                            !empty(
                                                $activity[
                                                    'reserved_at'
                                                ]
                                            )
                                        ) {

                                            echo date(
                                                "M d, Y",
                                                strtotime(
                                                    $activity[
                                                        'reserved_at'
                                                    ]
                                                )
                                            );

                                        }

                                        ?>

                                        &nbsp;•&nbsp;

                                        <?php
                                        echo $activity['quantity'];
                                        ?>

                                        trays

                                        (
                                        <?php
                                        echo htmlspecialchars(
                                            $activity['egg_size']
                                        );
                                        ?>
                                        )

                                    </p>


                                </div>


                                <!-- TIME -->

                                <div class="activity-time">


                                    <?php

                                    if (
                                        !empty(
                                            $activity[
                                                'reserved_at'
                                            ]
                                        )
                                    ) {

                                        echo date(
                                            "g:i A",
                                            strtotime(
                                                $activity[
                                                    'reserved_at'
                                                ]
                                            )
                                        );

                                    }

                                    ?>


                                </div>


                            </div>


                        <?php } ?>


                    <?php } else { ?>


                        <div class="empty-state">

                            <i
                                class="
                                fa-regular
                                fa-calendar
                                "
                            ></i>

                            <p>
                                No recent reservation activity.
                            </p>

                        </div>


                    <?php } ?>


                </div>


            </div>


        </div>


        <!-- =====================================================
             SUPPORT CARD
        ===================================================== -->

        <div class="support-card">


            <div class="support-icon">

                <i
                    class="
                    fa-solid
                    fa-house
                    "
                ></i>

            </div>


            <div class="support-content">

                <h3>
                    Thank you for supporting local farms!
                </h3>

                <p>
                    We appreciate your trust and support.
                </p>

            </div>


         <!-- FARM IMAGE DECORATION -->

<div class="farm-decoration">

    <img
        src="/EggFarm/dashboard_footer.png"
        alt="Egg Farm Landscape"
    >

</div>

        </div>


    </div>


</div>
<script>
function updateCustomerDateTime() {

    var now = new Date();

    var options = {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    };

    var dateText = now.toLocaleDateString(
        'en-US',
        options
    );

    var timeText = now.toLocaleTimeString(
        'en-US',
        {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }
    );

    var dateElement = document.getElementById(
        'customer-live-date'
    );

    var timeElement = document.getElementById(
        'customer-live-time'
    );

    if (dateElement) {
        dateElement.textContent = dateText;
    }

    if (timeElement) {
        timeElement.textContent = timeText;
    }
}

updateCustomerDateTime();

setInterval(
    updateCustomerDateTime,
    1000
);
</script>

</body>

</html>