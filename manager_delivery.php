<?php
// Include standardized session configuration FIRST
require_once 'session_config.php';

require 'db.php';

/* =========================================================
   MANAGER/OWNER ACCESS
   ========================================================= */

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

// Allow both manager and owner roles
if (isset($_SESSION['role']) && !in_array($_SESSION['role'], ['manager', 'owner'])) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

$manager_name = 'Manager';

if (isset($_SESSION['fullname']) && $_SESSION['fullname'] != '') {
    $manager_name = $_SESSION['fullname'];
}

/* =========================================================
   TIMEZONE
   ========================================================= */
date_default_timezone_set('Asia/Manila');

$today = date('Y-m-d');

/*
 * IMPORTANT:
 * Walang default/suggested date.
 * Blank ang date picker kapag unang bukas ng page.
 */
$selected_date = '';

if (isset($_GET['date']) && $_GET['date'] != '') {

    $requested_date = trim($_GET['date']);

    $date_object = DateTime::createFromFormat(
        'Y-m-d',
        $requested_date
    );

    /*
     * Tatanggapin lamang ang:
     * 1. valid date
     * 2. today onwards
     */
    if (
        $date_object &&
        $date_object->format('Y-m-d') == $requested_date &&
        $requested_date >= $today
    ) {

        $selected_date = $requested_date;
    }
}


/*
 * Display text
 */
if ($selected_date != '') {

    $display_date = date(
        'F d, Y',
        strtotime($selected_date)
    );

} else {

    $display_date = 'All Dates';
}

/* =========================================================
   SEARCH
   ========================================================= */

$search = '';

if (isset($_GET['search'])) {
    $search = trim($_GET['search']);
}

/* =========================================================
   FILTER
   ========================================================= */

$status_filter = '';

if (isset($_GET['status'])) {
    $status_filter = trim($_GET['status']);
}

/* =========================================================
   CHECK RESERVATION COLUMNS
   ========================================================= */

$reservation_columns = array();

$column_result = mysqli_query(
    $conn,
    "SHOW COLUMNS FROM reservations"
);

if ($column_result) {

    while ($column = mysqli_fetch_assoc($column_result)) {

        $reservation_columns[] =
            $column['Field'];
    }
}

/* =========================================================
   HELPER
   ========================================================= */

function reservationHasColumn(
    $columns,
    $column_name
) {
    return in_array(
        $column_name,
        $columns
    );
}

/* =========================================================
   AVAILABLE COLUMNS
   ========================================================= */

$has_reservation_code =
    reservationHasColumn(
        $reservation_columns,
        'reservation_code'
    );

$has_customer_name =
    reservationHasColumn(
        $reservation_columns,
        'customer_name'
    );

$has_contact_number =
    reservationHasColumn(
        $reservation_columns,
        'contact_number'
    );

$has_address =
    reservationHasColumn(
        $reservation_columns,
        'address'
    );

$has_delivery_address =
    reservationHasColumn(
        $reservation_columns,
        'delivery_address'
    );

$has_total_trays =
    reservationHasColumn(
        $reservation_columns,
        'total_trays'
    );

$has_trays =
    reservationHasColumn(
        $reservation_columns,
        'trays'
    );

$has_quantity =
    reservationHasColumn(
        $reservation_columns,
        'quantity'
    );

$has_reservation_date =
    reservationHasColumn(
        $reservation_columns,
        'reservation_date'
    );

$has_delivery_method =
    reservationHasColumn(
        $reservation_columns,
        'delivery_method'
    );

$has_status =
    reservationHasColumn(
        $reservation_columns,
        'status'
    );

$has_created_at =
    reservationHasColumn(
        $reservation_columns,
        'created_at'
    );

/* =========================================================
   AVAILABLE CONFIRMED DELIVERY DATES
   ========================================================= */

$available_dates = array();

if ($has_reservation_date && $has_status) {

    $date_query = "
        SELECT DISTINCT DATE(reservation_date) AS delivery_date
        FROM reservations
        WHERE status = 'Confirmed'
    ";

    if ($has_delivery_method) {

        $date_query .= "
            AND delivery_method = 'Delivery'
        ";
    }

    $date_query .= "
        ORDER BY delivery_date ASC
    ";

    $date_result = mysqli_query(
        $conn,
        $date_query
    );

    if ($date_result) {

        while ($date_row = mysqli_fetch_assoc(
            $date_result
        )) {

            if (
                isset(
                    $date_row['delivery_date']
                ) &&
                $date_row['delivery_date'] != ''
            ) {

                $available_dates[] =
                    $date_row['delivery_date'];
            }
        }
    }
}

/* =========================================================
   BUILD SELECT
   ========================================================= */

$select_parts = array();

if ($has_reservation_code) {

    $select_parts[] =
        "reservation_code";

} else {

    $select_parts[] =
        "'' AS reservation_code";
}

if ($has_customer_name) {

    $select_parts[] =
        "customer_name";

} else {

    $select_parts[] =
        "'' AS customer_name";
}

if ($has_contact_number) {

    $select_parts[] =
        "contact_number";

} else {

    $select_parts[] =
        "'' AS contact_number";
}

if ($has_delivery_address) {

    $select_parts[] =
        "delivery_address AS delivery_address";

} elseif ($has_address) {

    $select_parts[] =
        "address AS delivery_address";

} else {

    $select_parts[] =
        "'' AS delivery_address";
}

if ($has_total_trays) {

    $select_parts[] =
        "SUM(total_trays) AS total_trays";

} elseif ($has_trays) {

    $select_parts[] =
        "SUM(trays) AS total_trays";

} elseif ($has_quantity) {

    $select_parts[] =
        "SUM(quantity) AS total_trays";

} else {

    $select_parts[] =
        "0 AS total_trays";
}

if ($has_reservation_date) {

    $select_parts[] =
        "reservation_date";

} else {

    $select_parts[] =
        "'' AS reservation_date";
}

if ($has_delivery_method) {

    $select_parts[] =
        "delivery_method";

} else {

    $select_parts[] =
        "'' AS delivery_method";
}

if ($has_status) {

    $select_parts[] =
        "status";

} else {

    $select_parts[] =
        "'Confirmed' AS status";
}

/* =========================================================
   DELIVERY QUERY
   ========================================================= */

$sql = "
    SELECT " .
    implode(
        ", ",
        $select_parts
    ) .
    "
    FROM reservations
    WHERE 1=1
";

/* =========================================================
   ONLY CONFIRMED RESERVATIONS
   ========================================================= */

if ($has_status) {

    $sql .= "
        AND status = 'Confirmed'
    ";
}

/* =========================================================
   SELECTED DELIVERY DATE
   ========================================================= */

/*
 * DATE FILTER
 *
 * Kapag WALANG selected date:
 *     lahat ng Confirmed deliveries
 *
 * Kapag MAY selected date:
 *     deliveries para sa selected date lamang
 */
if (
    $has_reservation_date &&
    $selected_date != ''
) {

    $safe_selected_date =
        mysqli_real_escape_string(
            $conn,
            $selected_date
        );

    $sql .= "
        AND DATE(reservation_date) = '" .
        $safe_selected_date .
        "'
    ";
}

/* =========================================================
   DELIVERY METHOD
   ========================================================= */

if ($has_delivery_method) {

    $sql .= "
        AND delivery_method = 'Delivery'
    ";
}

/* =========================================================
   SEARCH
   ========================================================= */

if ($search != '') {

    $safe_search =
        mysqli_real_escape_string(
            $conn,
            $search
        );

    $search_parts = array();

    if ($has_customer_name) {

        $search_parts[] =
            "customer_name LIKE '%" .
            $safe_search .
            "%'";
    }

    if ($has_contact_number) {

        $search_parts[] =
            "contact_number LIKE '%" .
            $safe_search .
            "%'";
    }

    if ($has_reservation_code) {

        $search_parts[] =
            "reservation_code LIKE '%" .
            $safe_search .
            "%'";
    }

    if (count($search_parts) > 0) {

        $sql .= "
            AND (" .
            implode(
                " OR ",
                $search_parts
            ) .
            ")
        ";
    }
}

/* =========================================================
   STATUS FILTER
   ========================================================= */

if ($status_filter != '') {

    /*
     * Delivery Management is based on confirmed
     * reservations.
     *
     * Therefore only Confirmed can produce
     * delivery records.
     */

    if (
        strtolower($status_filter) ==
        'confirmed'
    ) {

        if ($has_status) {

            $sql .= "
                AND status = 'Confirmed'
            ";
        }

    } else {

        /*
         * Other old filter values are preserved
         * visually, but they cannot override the
         * Confirmed-reservation rule.
         */

        $sql .= "
            AND 1 = 0
        ";
    }
}

/* =========================================================
   GROUP
   ========================================================= */

$sql .= "
    GROUP BY
        reservation_code,
        customer_name,
        contact_number,
        delivery_address,
        reservation_date,
        delivery_method,
        status
";

/* =========================================================
   ORDER
   ========================================================= */

$sql .= "
    ORDER BY
        reservation_date ASC
";

/* =========================================================
   EXECUTE
   ========================================================= */

$reservations = array();

$result = mysqli_query(
    $conn,
    $sql
);

if ($result) {

    while ($row = mysqli_fetch_assoc(
        $result
    )) {

        $reservations[] = $row;
    }
}

/* =========================================================
   COUNTS
   ========================================================= */

$total_scheduled =
    count($reservations);

$successful_sms = 0;
$failed_sms = 0;
$pending_sms =
    $total_scheduled;

$total_recipients =
    $total_scheduled;

/* =========================================================
   NOTIFICATION COUNT
   ========================================================= */

$notification_count = 0;
$notification_items = array();

if ($has_status) {

    $notification_sql = "
        SELECT
            reservation_code,
            customer_name,
            reservation_date
        FROM reservations
        WHERE status = 'Pending'
        ORDER BY
            reservation_date ASC
    ";

    $notification_result =
        mysqli_query(
            $conn,
            $notification_sql
        );

    if ($notification_result) {

        while (
            $notification_row =
            mysqli_fetch_assoc(
                $notification_result
            )
        ) {

            $notification_items[] =
                $notification_row;
        }
    }
}

$notification_count =
    count($notification_items);

/* =========================================================
   PAGINATION
   ========================================================= */

$per_page = 5;

$total_pages = ceil(
    $total_scheduled /
    $per_page
);

$page = 1;

if (isset($_GET['page'])) {

    $page =
        intval($_GET['page']);
}

if ($page < 1) {
    $page = 1;
}

if (
    $total_pages > 0 &&
    $page > $total_pages
) {

    $page =
        $total_pages;
}

$start_index =
    ($page - 1) *
    $per_page;

$page_reservations =
    array_slice(
        $reservations,
        $start_index,
        $per_page
    );

/* =========================================================
   STATUS DISPLAY
   ========================================================= */

function deliveryStatusClass(
    $status
) {

    $status_lower =
        strtolower(
            trim($status)
        );

    if (
        $status_lower ==
            'completed' ||
        $status_lower ==
            'delivered'
    ) {

        return 'status-completed';
    }

    if (
        $status_lower ==
            'cancelled' ||
        $status_lower ==
            'canceled' ||
        $status_lower ==
            'failed'
    ) {

        return 'status-failed';
    }

    if (
        $status_lower ==
        'confirmed'
    ) {

        return 'status-confirmed';
    }

    return 'status-scheduled';
}

function deliveryStatusText(
    $status
) {

    if ($status == '') {
        return 'Confirmed';
    }

    return $status;
}

/* =========================================================
   PAGINATION URL
   ========================================================= */

function pageUrl(
    $page_number,
    $selected_date,
    $search,
    $status_filter
) {

    $url =
        '?date=' .
        urlencode($selected_date) .
        '&page=' .
        intval($page_number);

    if ($search != '') {

        $url .=
            '&search=' .
            urlencode($search);
    }

    if ($status_filter != '') {

        $url .=
            '&status=' .
            urlencode($status_filter);
    }

    return $url;
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

<title>Delivery Management</title>

<style>

/* =========================================================
   GLOBAL
   ========================================================= */

* {
    box-sizing: border-box;
}

body {
    margin: 0;
    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background: #f1f5f3;
    color: #1f2937;
    font-size: 14px;
}

button,
input,
select,
textarea {
    font-family: inherit;
}

button {
    cursor: pointer;
}

/* =========================================================
   PAGE
   ========================================================= */
.delivery-page {
    width: 100%;
    min-height: 100vh;

    padding: 8px 12px;

    box-sizing: border-box;
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
   OUTER CARD
   ========================================================= */


.delivery-outer-card {
    width: 100%;
    min-height: calc(100vh - 16px);

    margin: 0 auto;

    background: #ffffff;

    border: 1px solid #dddcd6;

    border-radius: 24px;

    overflow: hidden;

    box-sizing: border-box;

    box-shadow:
        0 1px 3px rgba(0, 0, 0, 0.03);
}
/* =========================================================
   HEADER
   ========================================================= */

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    padding-bottom: 15px;
    border-bottom: 1px solid #edf1ee;
}

.page-title {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    color: #202525;
}

.page-subtitle {
    margin: 6px 0 0;
    color: #6e7873;
    font-size: 13px;
}

.manager-profile {
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
}

/* =========================================================
   NOTIFICATION
   ========================================================= */

.notification-wrap {
    position: relative;
}

.notification-button {
    position: relative;
    width: 38px;
    height: 38px;
    border: 1px solid #e2e8e4;
    background: #ffffff;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #39413d;
    font-size: 18px;
}

.notification-button:hover {
    background: #f4faf6;
    color: #247b46;
}

.notification-badge {
    position: absolute;
    right: -2px;
    top: -3px;
    min-width: 17px;
    height: 17px;
    padding: 0 4px;
    border-radius: 10px;
    background: #ef4444;
    color: white;
    font-size: 9px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid white;
}

.notification-dropdown {
    display: none;
    position: absolute;
    right: 0;
    top: 46px;
    width: 310px;
    background: white;
    border: 1px solid #dfe7e2;
    border-radius: 9px;
    box-shadow:
        0 12px 30px rgba(0,0,0,.13);
    z-index: 5000;
    overflow: hidden;
}

.notification-dropdown.show {
    display: block;
}

.notification-dropdown-header {
    padding: 12px 14px;
    background: #f1f8f3;
    border-bottom: 1px solid #e3ebe6;
    font-size: 13px;
    font-weight: 700;
    color: #31503c;
}

.notification-list {
    max-height: 280px;
    overflow-y: auto;
}

.notification-item {
    display: block;
    padding: 11px 13px;
    border-bottom: 1px solid #edf1ee;
    text-decoration: none;
    color: #3e4743;
}

.notification-item:hover {
    background: #f8fcf9;
}

.notification-item-name {
    font-size: 12px;
    font-weight: 700;
    color: #303936;
}

.notification-item-text {
    margin-top: 3px;
    font-size: 11px;
    color: #737b77;
}

.notification-empty {
    padding: 18px;
    text-align: center;
    color: #7b8380;
    font-size: 11px;
}

.notification-footer {
    display: block;
    padding: 10px;
    text-align: center;
    border-top: 1px solid #edf1ee;
    color: #328e51;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
}

.notification-footer:hover {
    background: #f4faf6;
}
/* =========================================================
   DELIVERY PAGE HEADER
========================================================= */

.delivery-page-header {
    min-height: 86px;
    width: 100%;

    display: flex;
    align-items: center;
    justify-content: space-between;

    padding: 0 28px;

    background: #f7f6f2;

    border-bottom: 1px solid #dddcd6;

    box-sizing: border-box;
}


/* =========================================================
   HEADER LEFT
========================================================= */

.delivery-header-left {
    display: flex;
    align-items: center;
}

.delivery-header-left h1 {
    margin: 0;

    font-size: 1.35rem;
    font-weight: 700;

    color: #3f4b45;

    letter-spacing: -0.3px;
}


/* =========================================================
   HEADER RIGHT
========================================================= */

.delivery-header-right {
    display: flex;
    align-items: center;

    gap: 18px;

    height: 100%;
}


/* =========================================================
   NOTIFICATION
========================================================= */

.delivery-notification-wrapper {
    position: relative;

    display: flex;
    align-items: center;
}

.delivery-notification-icon {
    width: 40px;
    height: 40px;

    border: none;
    background: transparent;

    display: flex;
    align-items: center;
    justify-content: center;

    position: relative;

    cursor: pointer;

    color: #65716b;

    font-size: 1.2rem;

    transition: 0.2s ease;
}

.delivery-notification-icon:hover {
    color: #527d59;
}


/* notification badge */

.delivery-notification-badge {
    position: absolute;

    top: 1px;
    right: 0;

    min-width: 17px;
    height: 17px;

    padding: 0 4px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #527d59;
    color: #ffffff;

    font-size: 0.58rem;
    font-weight: 700;

    border: 2px solid #f7f6f2;
}


/* =========================================================
   HEADER DIVIDER
========================================================= */

.delivery-header-divider {
    width: 1px;
    height: 42px;

    background: #deded8;
}


/* =========================================================
   MANAGER PROFILE
========================================================= */

.delivery-manager-profile-header {
    display: flex;
    align-items: center;

    gap: 11px;

    min-width: 230px;
}


/* =========================================================
   MANAGER AVATAR
========================================================= */

.delivery-manager-avatar {
    width: 54px;
    height: 54px;

    min-width: 54px;

    border-radius: 50%;

    display: flex;
    align-items: center;
    justify-content: center;

    background: #e8ebe7;

    border: 1px solid #d9ddd8;

    color: #527d59;

    font-size: 1.25rem;

    box-shadow:
        0 2px 6px
        rgba(0, 0, 0, 0.04);
}


/* =========================================================
   MANAGER ACCOUNT
========================================================= */

.delivery-manager-account {
    display: flex;
    flex-direction: column;

    justify-content: center;

    min-width: 120px;
}

.delivery-manager-account strong {
    display: block;

    margin: 0;

    color: #3f4b45;

    font-size: 0.88rem;
    font-weight: 700;

    line-height: 1.2;
}


/* =========================================================
   DATE AND TIME
========================================================= */

.delivery-manager-date-time {
    display: flex;
    flex-direction: column;

    align-items: flex-start;

    gap: 2px;

    margin-top: 5px;

    white-space: nowrap;
}

.delivery-manager-date {
    font-size: 0.63rem;

    color: #8a9590;
}

.delivery-manager-time {
    font-size: 0.63rem;

    color: #527d59;

    font-weight: 600;

    line-height: 1.2;
}


/* =========================================================
   DROPDOWN ICON
========================================================= */

.delivery-manager-dropdown-icon {
    width: 28px;

    display: flex;
    align-items: center;
    justify-content: center;

    color: #65716b;

    font-size: 0.7rem;

    cursor: pointer;
}


/* =========================================================
   NOTIFICATION DROPDOWN
========================================================= */

.delivery-notification-dropdown {
    display: none;

    position: absolute;

    right: 0;
    top: 48px;

    width: 320px;

    background: #ffffff;

    border: 1px solid #e1e4e1;

    border-radius: 10px;

    box-shadow:
        0 10px 25px
        rgba(60, 70, 60, 0.12);

    z-index: 1000;

    overflow: hidden;
}

.delivery-notification-dropdown.show {
    display: block;
}


/* dropdown header */

.delivery-notification-dropdown-header {
    padding: 12px 16px;

    border-bottom: 1px solid #e4e7e4;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.delivery-notification-dropdown-header p {
    margin: 0;

    font-size: 13px;
    font-weight: 600;

    color: #3f4b45;
}

.delivery-notification-dropdown-header span {
    display: block;

    margin-top: 2px;

    font-size: 10px;

    color: #9aa29e;
}

.delivery-notification-dropdown-header strong {
    font-size: 10px;

    color: #b27a28;

    font-weight: 500;
}


/* notification list */

.delivery-notification-list {
    max-height: 320px;

    overflow-y: auto;
}

.delivery-notification-item {
    display: block;

    padding: 12px 16px;

    border-bottom: 1px solid #f0f1ef;

    text-decoration: none;

    transition: background 0.15s ease;
}

.delivery-notification-item:hover {
    background: #f8faf8;
}

.delivery-notification-item-name {
    font-size: 11px;

    font-weight: 600;

    color: #3f4b45;
}

.delivery-notification-item-text {
    margin-top: 3px;

    font-size: 10px;

    color: #6f7974;
}

.delivery-notification-empty {
    padding: 28px 16px;

    text-align: center;

    font-size: 11px;

    color: #8b9590;
}


/* dropdown footer */

.delivery-notification-footer {
    display: block;

    padding: 10px;

    text-align: center;

    border-top: 1px solid #edf1ee;

    color: #328e51;

    font-size: 11px;
    font-weight: 700;

    text-decoration: none;
}

.delivery-notification-footer:hover {
    background: #f4faf6;
}

/* =========================================================
   TOP GRID
   ========================================================= */

.top-grid {
    display: grid;

    grid-template-columns:
        minmax(0, 1.35fr)
        minmax(300px, .95fr);

    gap: 15px;

    margin: 20px 20px 15px 20px;
}

/* =========================================================
   CARD
   ========================================================= */

.card {
    background: white;
    border: 1px solid #e0e7e3;
    border-radius: 9px;
    box-shadow:
        0 1px 3px rgba(0,0,0,.025);
}

/* =========================================================
   SMS CARD
   ========================================================= */

.sms-card {
    overflow: hidden;
}

.card-header {
    min-height: 42px;
    padding: 0 16px;
    background: linear-gradient(
        to right,
        #edf8f0,
        #f7fbf8
    );
    border-bottom: 1px solid #e7eee9;
    display: flex;
    align-items: center;
    gap: 8px;
}

.card-header-icon {
    color: #2f9b59;
    font-size: 16px;
}

.card-header-title {
    font-size: 12px;
    font-weight: 700;
    color: #31503c;
    letter-spacing: .2px;
}

.sms-body {
    padding: 17px;
}

.sms-info-row {
    display: grid;
    grid-template-columns:
        1.15fr .75fr .75fr;
    margin-bottom: 16px;
}

.info-box {
    padding-right: 18px;
    border-right: 1px solid #edf0ee;
}

.info-box:last-child {
    border-right: 0;
    padding-left: 18px;
}

.info-label {
    display: block;
    font-size: 11px;
    color: #404746;
    font-weight: 700;
    margin-bottom: 9px;
}

.date-select {
    height: 36px;
    width: 100%;
    padding: 0 10px;
    border: 1px solid #dfe6e2;
    border-radius: 5px;
    background: white;
    color: #343a38;
    font-size: 12px;
    outline: none;
}

.date-select:focus {
    border-color: #42a968;
}

.date-form {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
}

.show-all-date-button {
    display: inline-flex;
    align-items: center;
    justify-content: center;

    height: 36px;
    padding: 0 12px;

    border: 1px solid #52a56e;
    border-radius: 8px;

    background: #ffffff;
    color: #31834f;

    font-size: 10px;
    font-weight: 700;

    text-decoration: none;
    white-space: nowrap;

    transition: all 0.2s ease;
}

.show-all-date-button:hover {
    background: #52a56e;
    color: #ffffff;
}
.customer-count-wrap {
    display: flex;
    align-items: center;
    min-height: 36px;
}

.customer-count {
    font-size: 28px;
    line-height: 28px;
    font-weight: 700;
    color: #218046;
    display: inline-block;
    margin-right: 8px;
}

.customer-label {
    font-size: 12px;
    color: #555;
    line-height: 1;
}

.sms-status-pill {
    display: inline-flex;
    align-items: center;
    padding: 7px 11px;
    border-radius: 15px;
    background: #fff1d5;
    color: #c88e20;
    font-size: 10px;
    font-weight: 700;
    margin-top: 1px;
}

.message-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    color: #444;
    margin-bottom: 8px;
}

.message-box {
    width: 100%;
    min-height: 88px;
    resize: vertical;
    border: 1px solid #e0e6e2;
    border-radius: 6px;
    padding: 11px;
    font-size: 12px;
    line-height: 1.5;
    color: #444;
    outline: none;
}

.message-box:focus {
    border-color: #42a968;
}

.sms-actions {
    display: flex;
    justify-content: flex-end;
    gap: 9px;
    margin-top: 11px;
}

.btn-edit {
    height: 34px;
    padding: 0 15px;
    border: 1px solid #55a874;
    color: #2c8650;
    background: white;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
}

.btn-edit:hover {
    background: #f0faf3;
}

.btn-sms {
    height: 34px;
    padding: 0 16px;
    border: 0;
    color: white;
    background: #116332;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
    opacity: .65;
    cursor: not-allowed;
}

/* =========================================================
   SMS SUMMARY
   ========================================================= */

.summary-card {
    padding: 0;
}

.summary-content {
    padding: 17px;
}

.summary-title {
    font-size: 12px;
    font-weight: 700;
    color: #414745;
    margin-bottom: 12px;
}

.summary-layout {
    display: flex;
    align-items: center;
    gap: 20px;
}

.donut-wrap {
    position: relative;
    width: 98px;
    height: 98px;
    flex-shrink: 0;
}

.donut {
    width: 98px;
    height: 98px;
    border-radius: 50%;
    background:
        conic-gradient(
            #42a968 0deg,
            #42a968 360deg
        );
    position: relative;
}

.donut::after {
    content: "";
    position: absolute;
    width: 70px;
    height: 70px;
    background: white;
    border-radius: 50%;
    top: 14px;
    left: 14px;
}

.summary-legend {
    flex: 1;
}

.legend-item {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
    font-size: 11px;
}

.legend-left {
    display: flex;
    align-items: center;
    gap: 7px;
    color: #555;
}

.legend-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
}

.dot-success {
    background: #43a968;
}

.dot-failed {
    background: #ef4444;
}

.dot-pending {
    background: #d5d9d7;
}

.legend-number {
    color: #555;
    font-weight: 600;
    font-size: 11px;
}

.total-recipients {
    display: flex;
    justify-content: space-between;
    border-top: 1px solid #edf0ee;
    padding-top: 10px;
    margin-top: 6px;
    font-size: 11px;
    color: #333;
    font-weight: 700;
}

/* =========================================================
   SMS HISTORY
   ========================================================= */

.history-card {
    margin-top: 15px;
    min-height: 130px;
}

.history-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 14px 15px 8px;
}

.history-title {
    font-size: 11px;
    font-weight: 700;
    color: #3c4641;
}

.history-title-icon {
    color: #37955c;
    margin-right: 6px;
}

.view-all {
    color: #328e51;
    text-decoration: underline;
    font-size: 11px;
}

.history-empty {
    text-align: center;
    color: #777;
    font-size: 11px;
    padding: 15px;
}

.history-empty-icon {
    display: block;
    font-size: 30px;
    color: #edf0ee;
    margin-bottom: 4px;
}

/* =========================================================
   BOTTOM GRID
   ========================================================= */

.bottom-grid {
    display: block;

    width: auto;

    margin: 0 20px 20px 20px;
}

/* =========================================================
   DELIVERY TABLE CARD
   ========================================================= */

.delivery-table-card {
    overflow: hidden;
}

.delivery-table-header {
    min-height: 56px;
    padding: 9px 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-bottom: 1px solid #e9eeeb;
    gap: 12px;
}

.delivery-title {
    font-size: 12px;
    font-weight: 700;
    color: #353b39;
}

.table-tools {
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-box {
    position: relative;
}

.search-input {
    width: 200px;
    height: 34px;
    border: 1px solid #e1e6e3;
    border-radius: 5px;
    padding: 0 10px 0 32px;
    font-size: 11px;
    outline: none;
}

.search-input:focus {
    border-color: #48a869;
}

.search-icon {
    position: absolute;
    left: 10px;
    top: 8px;
    color: #8b9390;
    font-size: 14px;
}

.filter-select {
    height: 34px;
    border: 1px solid #e1e6e3;
    border-radius: 5px;
    padding: 0 9px;
    font-size: 11px;
    color: #444;
    background: white;
    outline: none;
}

/* =========================================================
   TABLE
   ========================================================= */

.table-wrap {
    width: 100%;
    overflow-x: auto;
    overflow-y: hidden;

    -webkit-overflow-scrolling: touch;
}
.delivery-table {
    width: 100%;
    min-width: 1180px;

    border-collapse: collapse;
    table-layout: fixed;
}
.delivery-table th {
    height: 44px;

    padding: 0 12px;

    font-size: 10px;
    font-weight: 700;

    white-space: nowrap;
    text-align: left;

    vertical-align: middle;
}
.delivery-table td {
    height: 52px;

    padding: 0 12px;

    font-size: 11px;
    line-height: 1.35;

    vertical-align: middle;

    white-space: nowrap;

    overflow: hidden;
    text-overflow: ellipsis;
}

.delivery-table tr:hover td {
    background: #fbfdfb;
}

.delivery-table .address-cell {
    white-space: normal;

    overflow: hidden;
    text-overflow: ellipsis;

    line-height: 1.35;
}
.checkbox {
    width: 14px;
    height: 14px;
    accent-color: #36a35c;
}

.customer-cell {
    font-weight: 600;
    color: #343a37;
}

.contact-cell {
    color: #5c6561;
}

.code-cell {
    color: #606965;
    font-weight: 600;
}

.address-cell {
    color: #5e6662;
}

.trays-cell {
    text-align: center;
    font-weight: 600;
}

.delivery-time {
    color: #555;
}

.status-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 12px;
    font-size: 9px;
    font-weight: 700;
}

.status-confirmed {
    background: #e8f7ed;
    color: #27834b;
}

.status-scheduled {
    background: #eaf4ff;
    color: #3885c8;
}

.status-completed {
    background: #e7f8ec;
    color: #32834e;
}

.status-failed {
    background: #fdeaea;
    color: #dc4a4a;
}

.sms-badge {
    display: inline-block;
    padding: 5px 9px;
    border-radius: 12px;
    background: #fff2d8;
    color: #d59a2c;
    font-size: 9px;
    font-weight: 700;
}

.view-button {
    border: 1px solid #52a56e;
    background: white;
    color: #31834f;
    border-radius: 5px;
    padding: 6px 10px;
    font-size: 10px;
    font-weight: 600;
    cursor: pointer;
}

.view-button:hover {
    background: #eff9f2;
}

/* =========================================================
   TABLE FOOTER
   ========================================================= */

.table-footer {
    min-height: 50px;
    padding: 0 15px;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.entries-text {
    font-size: 10px;
    color: #737b78;
}

.pagination {
    display: flex;
    gap: 5px;
}

.page-button {
    width: 28px;
    height: 28px;
    border: 1px solid #e2e7e4;
    background: white;
    color: #555;
    border-radius: 5px;
    font-size: 10px;
    text-decoration: none;
    display: flex;
    align-items: center;
    justify-content: center;
}

.page-button:hover {
    border-color: #43a968;
}

.page-button.active {
    background: #42a968;
    color: white;
    border-color: #42a968;
}

.page-button.disabled {
    color: #c4c8c6;
    cursor: default;
}

/* =========================================================
   QUICK ACTIONS
   ========================================================= */

.quick-card {
    overflow: hidden;
    align-self: start;
}

.quick-header {
    min-height: 45px;
    padding: 0 15px;
    background: linear-gradient(
        to right,
        #edf8f0,
        #f5faf6
    );
    display: flex;
    align-items: center;
    gap: 7px;
    border-bottom: 1px solid #e5eee8;
}

.quick-icon {
    color: #2d9253;
    font-size: 15px;
}

.quick-title {
    color: #33523e;
    font-size: 11px;
    font-weight: 700;
}

.quick-body {
    padding: 10px;
}

.quick-action {
    display: flex;
    align-items: center;
    gap: 10px;
    width: 100%;
    min-height: 40px;
    border: 1px solid #e5e9e7;
    background: white;
    border-radius: 6px;
    padding: 0 10px;
    margin-bottom: 8px;
    color: #4c5651;
    font-size: 11px;
    font-weight: 600;
    text-decoration: none;
}

.quick-action:last-child {
    margin-bottom: 0;
}

.quick-action:hover {
    background: #f7fbf8;
    border-color: #b9d9c4;
}

.quick-action-icon {
    color: #2d9153;
    width: 16px;
    text-align: center;
    font-size: 14px;
}

/* =========================================================
   MODAL
   ========================================================= */

.modal-overlay {
    display: none;
    position: fixed;
    z-index: 9999;
    left: 0;
    top: 0;
    right: 0;
    bottom: 0;
    background: rgba(25,34,29,.42);
    align-items: center;
    justify-content: center;
}

.modal-overlay.show {
    display: flex;
}

.modal {
    width: 460px;
    max-width: calc(100% - 30px);
    background: white;
    border-radius: 10px;
    box-shadow:
        0 15px 50px rgba(0,0,0,.18);
    overflow: hidden;
}

.modal-header {
    min-height: 54px;
    padding: 0 17px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    background: #f2f8f4;
    border-bottom: 1px solid #e4ebe6;
}

.modal-title {
    font-size: 15px;
    font-weight: 700;
    color: #26332c;
}

.modal-close {
    border: 0;
    background: transparent;
    color: #777;
    font-size: 22px;
    cursor: pointer;
}

.modal-body {
    padding: 17px;
}

.detail-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 11px;
}

.detail-item {
    border: 1px solid #e8ecea;
    border-radius: 6px;
    padding: 11px;
}

.detail-item.full {
    grid-column: 1 / -1;
}

.detail-label {
    display: block;
    font-size: 9px;
    color: #858c89;
    text-transform: uppercase;
    font-weight: 700;
    margin-bottom: 5px;
}

.detail-value {
    display: block;
    font-size: 11px;
    color: #333b37;
    font-weight: 600;
}

.modal-footer {
    padding: 10px 17px 17px;
    display: flex;
    justify-content: flex-end;
}

.close-btn {
    height: 34px;
    padding: 0 16px;
    background: #238247;
    color: white;
    border: 0;
    border-radius: 5px;
    font-size: 11px;
    font-weight: 600;
}

/* =========================================================
   EMPTY STATE
   ========================================================= */

.empty-row {
    height: 120px !important;
    text-align: center !important;
    color: #888 !important;
    font-size: 12px !important;
}

/* =========================================================
   RESPONSIVE
   ========================================================= */

@media (max-width: 1150px) {

    .top-grid {
        grid-template-columns: 1fr;
    }

    .bottom-grid {
        grid-template-columns: 1fr;
    }

    .quick-card {
        width: 100%;
    }
}
@media (max-width: 800px) {

    .delivery-header-right {
        gap: 10px;
    }

    .delivery-manager-profile-header {
        min-width: auto;
    }

    .delivery-manager-dropdown-icon {
        display: none;
    }

}

@media (max-width: 600px) {

    .page-title {
        font-size: 20px;
    }

    .page-subtitle {
        font-size: 11px;
    }

    .manager-avatar {
        width: 34px;
        height: 34px;
    }

    .notification-button {
        width: 34px;
        height: 34px;
    }

    .detail-grid {
        grid-template-columns: 1fr;
    }

    .detail-item.full {
        grid-column: auto;
    }

    .sms-actions {
        flex-direction: column;
    }

    .btn-edit,
    .btn-sms {
        width: 100%;
    }
}

</style>

</head>

<body
    class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen"
>

<?php include('manager_panel.php'); ?>
<div class="main-content">

<div class="delivery-page">

<div class="delivery-outer-card">

<!-- =========================================================
     DELIVERY PAGE HEADER
========================================================= -->

<div class="delivery-page-header">


    <!-- =====================================================
         HEADER LEFT
    ====================================================== -->

    <div class="delivery-header-left">

        <h1>
            Delivery Management
        </h1>

    </div>


    <!-- =====================================================
         HEADER RIGHT
    ====================================================== -->

    <div class="delivery-header-right">


        <!-- =================================================
             NOTIFICATION
        ================================================== -->

        <div
            class="delivery-notification-wrapper"
            id="notificationWrapper"
        >

            <button
                type="button"
                id="notificationButton"
                class="delivery-notification-icon"
                aria-label="Notifications"
                aria-expanded="false"
            >

                <i class="fa-regular fa-bell"></i>


                <?php if ($notification_count > 0): ?>

                    <span
                        id="notificationBadge"
                        class="delivery-notification-badge"
                    >

                        <?php

                        if (
                            $notification_count > 99
                        ) {

                            echo '99+';

                        } else {

                            echo $notification_count;

                        }

                        ?>

                    </span>

                <?php endif; ?>

            </button>


            <!-- =============================================
                 NOTIFICATION DROPDOWN
            ============================================== -->

            <div
                id="notificationDropdown"
                class="delivery-notification-dropdown"
            >

                <!-- DROPDOWN HEADER -->

                <div
                    class="delivery-notification-dropdown-header"
                >

                    <div>

                        <p>
                            Notifications
                        </p>

                        <span>
                            Pending reservation requests
                        </span>

                    </div>


                    <?php if ($notification_count > 0): ?>

                        <strong>

                            <?php
                            echo $notification_count;
                            ?>

                            pending

                        </strong>

                    <?php endif; ?>

                </div>


                <!-- NOTIFICATION LIST -->

                <div
                    class="delivery-notification-list"
                >

                    <?php
                    if (
                        count(
                            $notification_items
                        ) > 0
                    ):
                    ?>

                        <?php
                        foreach (
                            $notification_items
                            as $notification
                        ):
                        ?>

                            <a
                                href="manager_reservation.php?view=<?php
                                echo urlencode(
                                    $notification[
                                        'reservation_code'
                                    ]
                                );
                                ?>"
                                class="delivery-notification-item"
                            >

                                <div>

                                    <div
                                        class="delivery-notification-item-name"
                                    >

                                        <?php

                                        $notification_customer =
                                            $notification[
                                                'customer_name'
                                            ];

                                        if (
                                            $notification_customer ==
                                            ''
                                        ) {

                                            $notification_customer =
                                                'Customer';
                                        }

                                        echo htmlspecialchars(
                                            $notification_customer
                                        );

                                        ?>

                                    </div>


                                    <div
                                        class="delivery-notification-item-text"
                                    >

                                        New reservation
                                        waiting for review.

                                    </div>

                                </div>

                            </a>

                        <?php
                        endforeach;
                        ?>


                    <?php else: ?>


                        <div
                            class="delivery-notification-empty"
                        >

                            No new notifications.

                        </div>


                    <?php endif; ?>

                </div>


                <!-- DROPDOWN FOOTER -->

                <a
                    href="manager_reservation.php"
                    class="delivery-notification-footer"
                >
                    View Reservations
                </a>

            </div>

        </div>


        <!-- =================================================
             HEADER DIVIDER
        ================================================== -->

        <div
            class="delivery-header-divider"
        ></div>


        <!-- =================================================
             MANAGER PROFILE
        ================================================== -->

        <div
            class="delivery-manager-profile-header"
        >


            <!-- MANAGER AVATAR -->

            <div
                class="delivery-manager-avatar"
            >

                <i class="fa-solid fa-user"></i>

            </div>


            <!-- MANAGER INFORMATION -->

            <div
                class="delivery-manager-account"
            >

                <strong>
                    Manager
                </strong>


                <!-- DATE AND TIME -->

                <div
                    class="delivery-manager-date-time"
                >

                    <span
                        id="managerDate"
                        class="delivery-manager-date"
                    >

                        <?php
                        echo date(
                            'F d, Y'
                        );
                        ?>

                    </span>


                    <span
                        id="managerTime"
                        class="delivery-manager-time"
                    >

                        <?php
                        echo date(
                            'h:i:s A'
                        );
                        ?>

                    </span>

                </div>

            </div>


            <!-- DROPDOWN ICON -->

            <div
                class="delivery-manager-dropdown-icon"
            >

                <i
                    class="fa-solid fa-chevron-down"
                ></i>

            </div>


        </div>


    </div>


</div>


<!-- =====================================================
     TOP SECTION
     ===================================================== -->

<div class="top-grid">

    <!-- BULK SMS -->

    <div class="card sms-card">

        <div class="card-header">

            <span class="card-header-icon">
                ▰
            </span>

            <span class="card-header-title">
                BULK SMS NOTIFICATION
            </span>

        </div>


        <div class="sms-body">

            <div class="sms-info-row">

                <!-- DATE -->

                <div class="info-box">

                    <span class="info-label">
                        SELECT DELIVERY DATE
                    </span>

                 <form
    method="GET"
    action="manager_delivery.php"
    id="dateForm"
    class="date-form"
>

    <?php if ($search != ''): ?>

        <input
            type="hidden"
            name="search"
            value="<?php echo htmlspecialchars($search); ?>"
        >

    <?php endif; ?>


    <?php if ($status_filter != ''): ?>

        <input
            type="hidden"
            name="status"
            value="<?php echo htmlspecialchars($status_filter); ?>"
        >

    <?php endif; ?>


    <input
        type="date"
        name="date"
        id="deliveryDatePicker"
        class="date-select"
        min="<?php echo htmlspecialchars($today); ?>"
        value="<?php echo htmlspecialchars($selected_date); ?>"
        onchange="
            document
            .getElementById('dateForm')
            .submit();
        "
    >


    <?php if ($selected_date != ''): ?>

        <a
            href="manager_delivery.php"
            class="show-all-date-button"
        >
            Show All
        </a>

    <?php endif; ?>

</form>

                </div>


                <!-- CUSTOMERS SCHEDULED -->

                <div class="info-box">

                    <span class="info-label">
                        CUSTOMERS SCHEDULED
                    </span>

                    <div
                        class="customer-count-wrap"
                    >

                        <span
                            class="customer-count"
                        >
                            <?php
                            echo $total_scheduled;
                            ?>
                        </span>

                        <span
                            class="customer-label"
                        >
                            customers
                        </span>

                    </div>

                </div>


                <!-- SMS STATUS -->

                <div class="info-box">

                    <span class="info-label">
                        SMS STATUS
                    </span>

                    <span
                        class="sms-status-pill"
                    >
                        NOT SENT YET
                    </span>

                </div>

            </div>


            <!-- MESSAGE -->

            <label
                class="message-label"
                for="smsMessage"
            >
                MESSAGE TEMPLATE
            </label>

            <textarea
                class="message-box"
                id="smsMessage"
                readonly
            >Hello! This is a reminder from VDVC Egg Farm.
Your egg order is scheduled for delivery on <?php
echo htmlspecialchars(
    $display_date
);
?>.
Please make sure someone is available to receive your order.
Thank you!</textarea>


            <!-- BUTTONS -->

            <div class="sms-actions">

                <button
                    type="button"
                    class="btn-edit"
                    onclick="
                        enableMessageEditing();
                    "
                >
                    ✎ &nbsp; Edit Message
                </button>

<button
    type="button"
    class="btn-sms"
    id="sendSmsButton"
    <?php
    if ($total_recipients <= 0) {
        echo 'disabled';
    }
    ?>
>
    ➤ &nbsp; Send SMS to All
    (<span id="smsRecipientCount"><?php
        echo $total_recipients;
    ?></span>)
</button>
            </div>

        </div>

    </div>


    <!-- SMS SUMMARY + HISTORY -->

    <div>

        <div class="card summary-card">

            <div class="summary-content">

                <div class="summary-title">
                    SMS SUMMARY
                </div>


                <div class="summary-layout">

                    <div class="donut-wrap">

                        <div class="donut"></div>

                    </div>


                    <div class="summary-legend">

                        <div class="legend-item">

                            <div class="legend-left">

                                <span
                                    class="legend-dot
                                    dot-success"
                                ></span>

                                <span>
                                    Successful
                                </span>

                            </div>

                            <span
                                class="legend-number"
                            >
                                <?php
                                echo $successful_sms;
                                ?>
                            </span>

                        </div>


                        <div class="legend-item">

                            <div class="legend-left">

                                <span
                                    class="legend-dot
                                    dot-failed"
                                ></span>

                                <span>
                                    Failed
                                </span>

                            </div>

                            <span
                                class="legend-number"
                            >
                                <?php
                                echo $failed_sms;
                                ?>
                            </span>

                        </div>


                        <div class="legend-item">

                            <div class="legend-left">

                                <span
                                    class="legend-dot
                                    dot-pending"
                                ></span>

                                <span>
                                    Pending
                                </span>

                            </div>

                            <span
                                class="legend-number"
                            >
                                <?php
                                echo $pending_sms;
                                ?>
                            </span>

                        </div>


                        <div class="total-recipients">

                            <span>
                                Total Recipients
                            </span>

                            <span>
                                <?php
                                echo $total_recipients;
                                ?>
                            </span>

                        </div>

                    </div>

                </div>

            </div>

        </div>


        <!-- SMS HISTORY -->

        <div class="card history-card">

            <div class="history-header">

                <div class="history-title">

                    <span
                        class="history-title-icon"
                    >
                        ◴
                    </span>

                    SMS HISTORY
                    (<?php
                    echo strtoupper(
                        $display_date
                    );
                    ?>)

                </div>


                <a
                    href="manager_sms_history.php"
                    class="view-all"
                >
                    View All
                </a>

            </div>


            <div class="history-empty">

                <span
                    class="history-empty-icon"
                >
                    ◇
                </span>

                No SMS sent yet for this date.
                <br>

                Send SMS to notify customers.

            </div>

        </div>

    </div>

</div>


<!-- =====================================================
     BOTTOM SECTION
     ===================================================== -->

<div class="bottom-grid">


    <!-- SCHEDULED DELIVERIES -->

    <div class="card delivery-table-card">

        <div class="delivery-table-header">

            <div class="delivery-title">

                CONFIRMED DELIVERIES —
<?php
echo strtoupper(
    $selected_date != ''
        ? $display_date
        : 'ALL DATES'
);
?>
(<?php echo $total_scheduled; ?> CUSTOMERS)

            </div>

<form
    method="GET"
    action="manager_delivery.php"
    class="table-tools"
>

    <?php if ($selected_date != ''): ?>

        <input
            type="hidden"
            name="date"
            value="<?php echo htmlspecialchars($selected_date); ?>"
        >

    <?php endif; ?>


                <div class="search-box">

                    <span class="search-icon">
                        ⌕
                    </span>

                    <input
                        type="text"
                        name="search"
                        class="search-input"
                        placeholder="Search customer or code..."
                        value="<?php
                        echo htmlspecialchars(
                            $search
                        );
                        ?>"
                    >

                </div>


                <select
                    name="status"
                    class="filter-select"
                    onchange="
                        this.form.submit();
                    "
                >

                    <option value="">
                        Filter
                    </option>

                    <option
                        value="Confirmed"
                        <?php
                        if (
                            $status_filter ==
                            'Confirmed'
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        Confirmed
                    </option>

                    <!--
                        Existing filter options retained
                        for compatibility with the current UI.
                        The actual delivery source remains
                        Confirmed reservations only.
                    -->

                    <option
                        value="Scheduled"
                        <?php
                        if (
                            $status_filter ==
                            'Scheduled'
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        Scheduled
                    </option>

                    <option
                        value="Completed"
                        <?php
                        if (
                            $status_filter ==
                            'Completed'
                        ) {
                            echo 'selected';
                        }
                        ?>
                    >
                        Completed
                    </option>

                </select>

            </form>

        </div>


        <div class="table-wrap">

            <table class="delivery-table">

                <thead>

                    <tr>
<th style="width:45px;">
    ...
</th>

<th style="width:150px;">
    CUSTOMER
</th>

<th style="width:125px;">
    CONTACT NUMBER
</th>

<th style="width:145px;">
    RESERVATION CODE
</th>

<th style="width:210px;">
    ADDRESS
</th>

<th style="width:90px;">
    TRAYS / EGGS
</th>

<th style="width:135px;">
    DELIVERY / PICK-UP DATE
</th>

<th style="width:100px;">
    DELIVERY TIME
</th>

<th style="width:95px;">
    STATUS
</th>

<th style="width:95px;">
    SMS STATUS
</th>

<th style="width:80px;">
    ACTION
</th>

                    </tr>

                </thead>


                <tbody>

                <?php
                if (
                    count(
                        $page_reservations
                    ) > 0
                ):
                ?>

                    <?php
                    foreach (
                        $page_reservations
                        as $reservation
                    ):
                    ?>

                        <tr>

                            <!-- CHECK -->

                            <td>

                             <input
    type="checkbox"
    class="checkbox delivery-checkbox"
    value="<?php
    echo htmlspecialchars(
        $reservation[
            'reservation_code'
        ]
    );
    ?>"
    data-contact="<?php
    echo htmlspecialchars(
        $reservation[
            'contact_number'
        ]
    );
    ?>"
    data-customer="<?php
    echo htmlspecialchars(
        $reservation[
            'customer_name'
        ]
    );
    ?>"
    checked
>

                            </td>


                            <!-- CUSTOMER -->

                            <td
                                class="customer-cell"
                            >

                                <?php

                                $customer =
                                    $reservation[
                                        'customer_name'
                                    ];

                                if (
                                    $customer ==
                                    ''
                                ) {

                                    $customer =
                                        'Customer';
                                }

                                echo htmlspecialchars(
                                    $customer
                                );

                                ?>

                            </td>


                            <!-- CONTACT -->

                            <td
                                class="contact-cell"
                            >

                                <?php

                                $contact =
                                    $reservation[
                                        'contact_number'
                                    ];

                                if (
                                    $contact ==
                                    ''
                                ) {

                                    $contact =
                                        '—';
                                }

                                echo htmlspecialchars(
                                    $contact
                                );

                                ?>

                            </td>


                            <!-- CODE -->

                            <td
                                class="code-cell"
                            >

                                <?php

                                $code =
                                    $reservation[
                                        'reservation_code'
                                    ];

                                if (
                                    $code ==
                                    ''
                                ) {

                                    $code =
                                        '—';
                                }

                                echo htmlspecialchars(
                                    $code
                                );

                                ?>

                            </td>


                            <!-- ADDRESS -->

                            <td
                                class="address-cell"
                                title="<?php
                                echo htmlspecialchars(
                                    $reservation[
                                        'delivery_address'
                                    ]
                                );
                                ?>"
                            >

                                <?php

                                $address =
                                    $reservation[
                                        'delivery_address'
                                    ];

                                if (
                                    $address ==
                                    ''
                                ) {

                                    $address =
                                        '—';
                                }

                                echo htmlspecialchars(
                                    $address
                                );

                                ?>

                            </td>


                            <!-- TRAYS -->

                            <td class="trays-cell">

                                <?php

                                $trays =
                                    intval(
                                        $reservation[
                                            'total_trays'
                                        ]
                                    );

                                echo $trays .
                                    ' Tray';

                                if (
                                    $trays !=
                                    1
                                ) {

                                    echo 's';
                                }

                                ?>

                            </td>


                            <!-- DELIVERY TIME -->

                          <!-- DELIVERY / PICK-UP DATE -->
<!-- DELIVERY / PICK-UP DATE -->

<td
    class="delivery-date-cell"
>

    <?php

    $delivery_date =
        $reservation[
            'reservation_date'
        ];

    if (
        $delivery_date != ''
    ) {

        echo htmlspecialchars(
            date(
                'M d, Y',
                strtotime(
                    $delivery_date
                )
            )
        );

    } else {

        echo '—';

    }

    ?>

</td>


<!-- DELIVERY TIME -->

<td
    class="delivery-time"
>

    Scheduled

</td>


                            <!-- STATUS -->

                            <td>

                                <span
                                    class="status-badge
                                    <?php
                                    echo deliveryStatusClass(
                                        $reservation[
                                            'status'
                                        ]
                                    );
                                    ?>"
                                >

                                    <?php

                                    echo htmlspecialchars(
                                        deliveryStatusText(
                                            $reservation[
                                                'status'
                                            ]
                                        )
                                    );

                                    ?>

                                </span>

                            </td>


                            <!-- SMS -->

                            <td>

                                <span
                                    class="sms-badge"
                                >
                                    Not Sent
                                </span>

                            </td>


                            <!-- ACTION -->

                            <td>

                                <button
                                    type="button"
                                    class="view-button"
                                    onclick='viewDelivery(
                                        <?php
                                        echo json_encode(
                                            $reservation
                                        );
                                        ?>
                                    )'
                                >
                                    View
                                </button>

                            </td>

                        </tr>

                    <?php
                    endforeach;
                    ?>

                <?php else: ?>

                  <tr>

    <td
        colspan="11"
        class="empty-row"
    >

        <?php if ($selected_date != ''): ?>

            No confirmed deliveries found
            for
            <?php
            echo htmlspecialchars(
                $display_date
            );
            ?>.

        <?php else: ?>

            No confirmed deliveries found.

        <?php endif; ?>

    </td>

</tr>

                <?php endif; ?>

                </tbody>

            </table>

        </div>


        <!-- TABLE FOOTER -->

        <div class="table-footer">

            <div class="entries-text">

                <?php

                $show_start = 0;
                $show_end = 0;

                if (
                    $total_scheduled >
                    0
                ) {

                    $show_start =
                        $start_index + 1;

                    $show_end =
                        min(
                            $start_index +
                            $per_page,
                            $total_scheduled
                        );
                }

                echo 'Showing ' .
                    $show_start .
                    ' to ' .
                    $show_end .
                    ' of ' .
                    $total_scheduled .
                    ' entries';

                ?>

            </div>


            <div class="pagination">

                <?php
                if ($page > 1):
                ?>

                    <a
                        class="page-button"
                        href="<?php
                        echo pageUrl(
                            $page - 1,
                            $selected_date,
                            $search,
                            $status_filter
                        );
                        ?>"
                    >
                        ‹
                    </a>

                <?php endif; ?>


                <?php

                if ($total_pages > 0) {

                    for (
                        $p = 1;
                        $p <= $total_pages;
                        $p++
                    ) {

                        echo
                            '<a class="page-button ';

                        if (
                            $p ==
                            $page
                        ) {

                            echo 'active';
                        }

                        echo '" href="' .
                            pageUrl(
                                $p,
                                $selected_date,
                                $search,
                                $status_filter
                            ) .
                            '">' .
                            $p .
                            '</a>';
                    }
                }

                ?>


                <?php
                if (
                    $page <
                    $total_pages
                ):
                ?>

                    <a
                        class="page-button"
                        href="<?php
                        echo pageUrl(
                            $page + 1,
                            $selected_date,
                            $search,
                            $status_filter
                        );
                        ?>"
                    >
                        ›
                    </a>

                <?php endif; ?>

            </div>

        </div>

    </div>




</div>

</div>
<!-- /.delivery-outer-card -->

</div>
<!-- /.delivery-page -->


<!-- =========================================================
     DELIVERY DETAILS MODAL
     ========================================================= -->

<div
    class="modal-overlay"
    id="deliveryModal"
>

    <div class="modal">

        <div class="modal-header">

            <span class="modal-title">
                Delivery Details
            </span>

            <button
                type="button"
                class="modal-close"
                onclick="
                    closeDeliveryModal();
                "
            >
                ×
            </button>

        </div>


        <div class="modal-body">

            <div class="detail-grid">

                <div class="detail-item">

                    <span class="detail-label">
                        Customer
                    </span>

                    <span
                        class="detail-value"
                        id="modalCustomer"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Contact Number
                    </span>

                    <span
                        class="detail-value"
                        id="modalContact"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Reservation Code
                    </span>

                    <span
                        class="detail-value"
                        id="modalCode"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Trays / Eggs
                    </span>

                    <span
                        class="detail-value"
                        id="modalTrays"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item full">

                    <span class="detail-label">
                        Delivery Address
                    </span>

                    <span
                        class="detail-value"
                        id="modalAddress"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Delivery Date
                    </span>

                    <span
                        class="detail-value"
                        id="modalDate"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item">

                    <span class="detail-label">
                        Status
                    </span>

                    <span
                        class="detail-value"
                        id="modalStatus"
                    >
                        —
                    </span>

                </div>


                <div class="detail-item full">

                    <span class="detail-label">
                        SMS Status
                    </span>

                    <span class="detail-value">
                        Not Sent —
                        SMS feature will be connected later.
                    </span>

                </div>

            </div>

        </div>


        <div class="modal-footer">

            <button
                type="button"
                class="close-btn"
                onclick="
                    closeDeliveryModal();
                "
            >
                Close
            </button>

        </div>

    </div>

</div>


<script>

/* =========================================================
   MESSAGE EDIT
   ========================================================= */

function enableMessageEditing() {

    var message =
        document.getElementById(
            'smsMessage'
        );

    message.removeAttribute(
        'readonly'
    );

    message.focus();

    message.style.borderColor =
        '#42a968';
}


/* =========================================================
   NOTIFICATION DROPDOWN
   ========================================================= */

var notificationButton =
    document.getElementById(
        'notificationButton'
    );

var notificationDropdown =
    document.getElementById(
        'notificationDropdown'
    );

if (
    notificationButton &&
    notificationDropdown
) {

    notificationButton.onclick =
        function(event) {

            event.stopPropagation();

            notificationDropdown.classList.toggle(
                'show'
            );
        };
}


/* =========================================================
   CLOSE NOTIFICATION WHEN CLICKING OUTSIDE
   ========================================================= */

document.addEventListener(
    'click',
    function(event) {

        if (
            notificationDropdown &&
            notificationButton
        ) {

            if (
                !notificationDropdown.contains(
                    event.target
                ) &&
                !notificationButton.contains(
                    event.target
                )
            ) {

                notificationDropdown.classList.remove(
                    'show'
                );
            }
        }
    }
);


/* =========================================================
   MANAGER CLOCK
   ========================================================= */

function updateManagerClock() {

    var now =
        new Date();

    var dateElement =
        document.getElementById(
            'managerDate'
        );

    var timeElement =
        document.getElementById(
            'managerTime'
        );

    /*
     * Use Asia/Manila so the displayed
     * time follows the system timezone
     * regardless of browser timezone.
     */

    try {

        var dateFormatter =
            new Intl.DateTimeFormat(
                'en-US',
                {
                    timeZone:
                        'Asia/Manila',
                    month:
                        'long',
                    day:
                        '2-digit',
                    year:
                        'numeric'
                }
            );

        var timeFormatter =
            new Intl.DateTimeFormat(
                'en-US',
                {
                    timeZone:
                        'Asia/Manila',
                    hour:
                        '2-digit',
                    minute:
                        '2-digit',
                    second:
                        '2-digit',
                    hour12:
                        true
                }
            );

        if (dateElement) {

            dateElement.innerHTML =
                dateFormatter.format(
                    now
                );
        }

        if (timeElement) {

            timeElement.innerHTML =
                timeFormatter.format(
                    now
                );
        }

    } catch (error) {

        /*
         * Fallback for browsers that do not
         * support the timezone formatter.
         */

        if (dateElement) {

            dateElement.innerHTML =
                now.toLocaleDateString(
                    'en-US'
                );
        }

        if (timeElement) {

            dateElement.innerHTML =
                now.toLocaleTimeString(
                    'en-US'
                );
        }
    }
}

updateManagerClock();

setInterval(
    updateManagerClock,
    1000
);


/* =========================================================
   VIEW DELIVERY
   ========================================================= */

function viewDelivery(data) {

    document.getElementById(
        'modalCustomer'
    ).innerHTML =
        escapeHtml(
            data.customer_name ||
            'Customer'
        );

    document.getElementById(
        'modalContact'
    ).innerHTML =
        escapeHtml(
            data.contact_number ||
            '—'
        );

    document.getElementById(
        'modalCode'
    ).innerHTML =
        escapeHtml(
            data.reservation_code ||
            '—'
        );

    var trays =
        parseInt(
            data.total_trays ||
            0,
            10
        );

    document.getElementById(
        'modalTrays'
    ).innerHTML =
        trays +
        (
            trays == 1
                ? ' Tray'
                : ' Trays'
        );

    document.getElementById(
        'modalAddress'
    ).innerHTML =
        escapeHtml(
            data.delivery_address ||
            '—'
        );

    document.getElementById(
        'modalDate'
    ).innerHTML =
        escapeHtml(
            data.reservation_date ||
            '—'
        );

    document.getElementById(
        'modalStatus'
    ).innerHTML =
        escapeHtml(
            data.status ||
            'Confirmed'
        );

    document.getElementById(
        'deliveryModal'
    ).className =
        'modal-overlay show';
}


/* =========================================================
   CLOSE MODAL
   ========================================================= */

function closeDeliveryModal() {

    document.getElementById(
        'deliveryModal'
    ).className =
        'modal-overlay';
}


/* =========================================================
   ESCAPE HTML
   ========================================================= */

function escapeHtml(value) {

    var div =
        document.createElement(
            'div'
        );

    div.appendChild(
        document.createTextNode(
            value
        )
    );

    return div.innerHTML;
}


/* =========================================================
   CLICK OUTSIDE MODAL
   ========================================================= */

document.getElementById(
    'deliveryModal'
).onclick =
    function(event) {

        if (
            event.target ===
            document.getElementById(
                'deliveryModal'
            )
        ) {

            closeDeliveryModal();
        }
    };


/* =========================================================
   SMS RECIPIENT SELECTION
   ========================================================= */

function updateSmsRecipientCount() {

    var checkboxes =
        document.querySelectorAll(
            '.delivery-checkbox:checked'
        );

    var count =
        checkboxes.length;

    var countElement =
        document.getElementById(
            'smsRecipientCount'
        );

    var sendButton =
        document.getElementById(
            'sendSmsButton'
        );

    if (countElement) {

        countElement.innerHTML =
            count;
    }

    if (sendButton) {

        sendButton.disabled =
            count === 0;
    }
}


/* =========================================================
   CHECKBOX CHANGE
   ========================================================= */

var deliveryCheckboxes =
    document.querySelectorAll(
        '.delivery-checkbox'
    );

for (
    var i = 0;
    i < deliveryCheckboxes.length;
    i++
) {

    deliveryCheckboxes[i].onclick =
        function() {

            updateSmsRecipientCount();

        };
}


/* =========================================================
   INITIAL SMS RECIPIENT COUNT
   ========================================================= */

updateSmsRecipientCount();
</script>

</div>
<!-- /.main-content -->

</body>
</html>