<?php
ob_start();

// Include standardized session configuration FIRST
require_once '../session_config.php';

date_default_timezone_set('Asia/Manila');

require '../db.php';

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

/* PHP 5.5 COMPATIBLE TOKEN COMPARISON */
function customerTokenEquals($known, $submitted)
{
    if (!is_string($known) || !is_string($submitted)) {
        return false;
    }

    if (strlen($known) !== strlen($submitted)) {
        return false;
    }

    $result = 0;
    $length = strlen($known);

    for ($i = 0; $i < $length; $i++) {
        $result |= ord($known[$i]) ^ ord($submitted[$i]);
    }

    return $result === 0;
}
/* =========================================================
   SECURE CUSTOMER LOGOUT
========================================================= */


/* Create a logout token for form validation. */
if (empty($_SESSION['customer_logout_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(
            random_bytes(32)
        );
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(
            openssl_random_pseudo_bytes(32)
        );
    } else {
        // Compatible fallback for older PHP installations.
        $_SESSION['customer_logout_token'] = hash(
            'sha256',
            uniqid(mt_rand(), true) . session_id()
        );
    }
}



/* Process logout only after the customer confirms. */
if (
    $_SERVER['REQUEST_METHOD'] == 'POST' &&
    isset($_POST['customer_logout'])
) {
    $submitted_token = isset($_POST['logout_token'])
        ? $_POST['logout_token']
        : '';

    $session_token = isset($_SESSION['customer_logout_token'])
        ? $_SESSION['customer_logout_token']
        : '';

    if (
        $submitted_token !== '' &&
        $session_token !== '' &&
        is_string($submitted_token) &&
        is_string($session_token) &&
        strlen($submitted_token) === strlen($session_token) &&
       customerTokenEquals($session_token, $submitted_token)
    ) {
        $_SESSION = array();

        if (ini_get('session.use_cookies')) {
            $cookie_params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $cookie_params['path'],
                $cookie_params['domain'],
                $cookie_params['secure'],
                $cookie_params['httponly']
            );
        }

        session_destroy();

        header("Location: login.php");
        exit();
    }

    header("Location: customer_dashboard.php");
    exit();
}

$customer_id = $_SESSION['user_id'];
$active_page = 'dashboard';

$unread_notifications = 0;

$notification_count_result = $conn->query(
    "SELECT COUNT(*) AS total
     FROM notifications
     WHERE user_id = " . (int)$customer_id . "
     AND is_read = 0"
);

if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
}

$customer_name = isset($_SESSION['fullname'])
    ? $_SESSION['fullname']
    : (isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer'));


/* =========================================================
   TOTAL RESERVATIONS
========================================================= */

$total_reservations = 0;

$reservation_count_sql = "
    SELECT COUNT(DISTINCT reservation_code) AS total
    FROM reservations
    WHERE user_id = ?
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
    WHERE user_id = ?
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
        e.egg_size,
        e.current_stock AS available_stock
    FROM egg_inventory e
    INNER JOIN (
        SELECT egg_size, MAX(id) AS id
        FROM egg_inventory
        GROUP BY egg_size
    ) latest ON latest.id = e.id
    ORDER BY 
        FIELD(
                e.egg_size,
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
        egg_type AS egg_size,
        quantity,
        status,
        reserved_at
    FROM reservations
    WHERE user_id = ?
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

    <link
        rel="stylesheet"
        href="customer_styles.css"
    >

    <style>
    /* Page-specific overrides for customer_dashboard.php */
    .customer-outer-card {
        margin-top: var(--vdvc-spacing-lg);
    }
    
    /* The dashboard uses customer-page inside customer-outer-card */
    .customer-outer-card .customer-page {
        padding: var(--vdvc-spacing-xl);
    }
    
    /* Hero section is separate from outer card */
    .customer-hero {
        border-radius: var(--vdvc-radius-xl);
        margin-bottom: var(--vdvc-spacing-lg);
    }
    </style>
</head>

<body>

<?php require '../customer_header.php'; ?>



<!-- =========================================================
     WELCOME HERO
========================================================= -->
<section class="customer-hero">

    <div class="customer-hero-content">


        <h1 class="customer-hero-title">
 Welcome back,
            <?php
            echo htmlspecialchars($first_name);
            ?>!

        </h1>


        <p class="customer-hero-subtitle">

            Fresh eggs, trusted farm, better for you.

        </p>

    </div>

</section>



<!-- =========================================================
     MAIN CUSTOMER CONTENT
========================================================= -->

<div class="customer-outer-card">
<main class="customer-page">


    <!-- =====================================================
         SUMMARY CARDS
    ====================================================== -->

    <section class="summary-grid">


        <!-- ACCOUNT TYPE -->

        <div class="summary-card">

            <div class="summary-icon account">

                <i class="fa-regular fa-user"></i>

            </div>


            <div class="summary-content">

                <span>
                    Account Type
                </span>

                <h2>
                    Customer
                </h2>

                <small>
                    Regular Customer
                </small>

            </div>

        </div>



        <!-- TOTAL RESERVATIONS -->

        <div class="summary-card">

            <div class="summary-icon reservation">

                <i class="fa-regular fa-calendar-check"></i>

            </div>


            <div class="summary-content">

                <span>
                    Total Reservations
                </span>

                <h2>

                    <?php
                    echo $total_reservations;
                    ?>

                </h2>

                <small>
                    All time
                </small>

            </div>

        </div>



        <!-- TRAYS ORDERED -->

        <div class="summary-card">

            <div class="summary-icon trays">

                <i class="fa-solid fa-cube"></i>

            </div>


            <div class="summary-content">

                <span>
                    Trays Ordered
                </span>

                <h2>

                    <?php
                    echo $total_trays;
                    ?>

                </h2>

                <small>
                    All time
                </small>

            </div>

        </div>


    </section>



    <!-- =====================================================
         MAIN DASHBOARD GRID
    ====================================================== -->

    <section class="dashboard-grid">


        <!-- =================================================
             AVAILABLE EGG STOCKS
        ================================================== -->

        <div class="dashboard-card">


            <!-- CARD HEADER -->

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


                                    <!-- EGG SIZE -->

                                    <td>

                                        <strong>

                                            <?php
                                            echo htmlspecialchars(
                                                $stock['egg_size']
                                            );
                                            ?>

                                        </strong>

                                    </td>



                                    <!-- AVAILABLE STOCK -->

                                    <td>

                                        <span class="stock-number">

                                            <?php
                                            echo $available_stock;
                                            ?>

                                        </span>

                                    </td>



                                    <!-- UNIT -->

                                    <td>

                                        trays

                                    </td>



                                    <!-- STATUS -->

                                    <td>


                                        <?php if ($available_stock > 0) { ?>


                                            <span class="stock-status">

                                                <span class="status-dot"></span>

                                                Available

                                            </span>


                                        <?php } else { ?>


                                            <span
                                                style="
                                                    color:#c65b4e;
                                                    font-size:9px;
                                                    font-weight:700;
                                                "
                                            >

                                                <i
                                                    class="fa-solid fa-circle"
                                                    style="
                                                        font-size:6px;
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

                        <i class="fa-solid fa-box-open"></i>

                        <p>
                            No egg stocks are currently available.
                        </p>

                    </div>


                <?php } ?>


            </div>



            <!-- STOCK FOOTER -->

            <div class="stock-footer">


                <div class="stock-info">

                    <i class="fa-solid fa-circle-info"></i>

                    <span>
                        Stocks are updated in real-time.
                        Reserve now to secure your preferred stock.
                    </span>

                </div>


                <a
                    href="customer_reservation.php"
                    class="reservation-button"
                >

                    <i class="fa-regular fa-calendar-plus"></i>

                    Make a Reservation

                </a>


            </div>


        </div>



        <!-- =================================================
             RECENT ACTIVITY
        ================================================== -->

        <div class="dashboard-card">


            <!-- CARD HEADER -->

            <div class="card-header">


                <div class="card-title">

                    <div class="card-title-icon">

                        <i class="fa-solid fa-clock-rotate-left"></i>

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



            <!-- ACTIVITY -->

            <div class="activity-list">


                <?php if (count($recent_activities) > 0) { ?>


                    <?php foreach ($recent_activities as $activity) { ?>


                        <?php

                        $icon_data =
                            getActivityIcon(
                                $activity['status']
                            );

                        ?>


                        <div class="activity-item">


                            <!-- ICON -->

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



                            <!-- CONTENT -->

                            <div class="activity-content">


                                <h4>

                                    Reservation
                                    <?php
                                    echo htmlspecialchars(
                                        $activity['reservation_code']
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
                                            $activity['reserved_at']
                                        )
                                    ) {

                                        echo date(
                                            "M d, Y",
                                            strtotime(
                                                $activity['reserved_at']
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
                                        $activity['reserved_at']
                                    )
                                ) {

                                    echo date(
                                        "g:i A",
                                        strtotime(
                                            $activity['reserved_at']
                                        )
                                    );

                                }

                                ?>


                            </div>


                        </div>


                    <?php } ?>


                <?php } else { ?>


                    <div class="empty-state">

                        <i class="fa-regular fa-calendar"></i>

                        <p>
                            No recent reservation activity.
                        </p>

                    </div>


                <?php } ?>


            </div>


        </div>


    </section>


</main>
</div>


<?php require '../customer_footer.php'; ?>

<?php require '../customer_modal.php'; ?>

</body>
</html>
