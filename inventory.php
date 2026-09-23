<?php
ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

include 'db.php';

/* =========================================================
   SECURITY - MANAGER/OWNER ACCESS
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

/* =========================================================
   SETTINGS
========================================================= */

$eggs_per_tray = 30;


/* =========================================================
   1. HANDLE CRUD FORM SUBMISSIONS
========================================================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $current_date = date('Y-m-d H:i:s');


    /* =====================================================
       EGG INVENTORY CRUD
    ===================================================== */

    if (isset($_POST['action_egg'])) {

        $movement = mysqli_real_escape_string(
            $conn,
            $_POST['action_egg']
        );


        /* =================================================
           AUTOMATIC REASON
        ================================================= */

        if ($movement == "Stock In") {

            $reason = "Harvest";

        } elseif ($movement == "Stock Out") {

            $reason = "Adjustment";

        } else {

            $reason = "Adjustment";

        }


        /* =================================================
           STOCK ADJUSTMENT
        ================================================= */

        if ($movement == "Stock Adjustment") {

            $selected_id = intval(
                $_POST['selected_egg_id']
            );

            $adjustment = intval(
                $_POST['quantity']
            );


            if ($selected_id > 0 && $adjustment != 0) {

                $selected_query = mysqli_query(
                    $conn,
                    "
                    SELECT *
                    FROM egg_inventory
                    WHERE id = '$selected_id'
                    LIMIT 1
                    "
                );


                if (
                    $selected_query &&
                    mysqli_num_rows($selected_query) > 0
                ) {

                    $selected_row = mysqli_fetch_assoc(
                        $selected_query
                    );


                    $egg_size = mysqli_real_escape_string(
                        $conn,
                        $selected_row['egg_size']
                    );


                    $current_stock = intval(
                        $selected_row['current_stock']
                    );


                    $new_stock =
                        $current_stock + $adjustment;


                    /*
                       Prevent negative stock
                    */

                    if ($new_stock < 0) {

                        $new_stock = 0;

                    }


                    /*
                       Get latest stock for this egg size.
                       This prevents an old selected row from
                       replacing newer inventory information.
                    */

                    $latest_stock_query = mysqli_query(
                        $conn,
                        "
                        SELECT current_stock
                        FROM egg_inventory
                        WHERE egg_size = '$egg_size'
                        ORDER BY id DESC
                        LIMIT 1
                        "
                    );


                    if (
                        $latest_stock_query &&
                        mysqli_num_rows(
                            $latest_stock_query
                        ) > 0
                    ) {

                        $latest_stock_row =
                            mysqli_fetch_assoc(
                                $latest_stock_query
                            );


                        $current_latest_stock =
                            intval(
                                $latest_stock_row[
                                    'current_stock'
                                ]
                            );

                    } else {

                        $current_latest_stock = 0;

                    }


                    $new_stock =
                        $current_latest_stock
                        +
                        $adjustment;


                    if ($new_stock < 0) {

                        $new_stock = 0;

                    }


                    /*
                       Generate new adjustment batch ID
                    */

                    $today_str = date('Ymd');

                    $prefix =
                        "EG"
                        .
                        $today_str
                        .
                        "-";


                    $seq_query = mysqli_query(
                        $conn,
                        "
                        SELECT batch_id
                        FROM egg_inventory
                        WHERE batch_id LIKE '$prefix%'
                        ORDER BY id DESC
                        LIMIT 1
                        "
                    );


                    if (
                        $seq_query &&
                        mysqli_num_rows(
                            $seq_query
                        ) > 0
                    ) {

                        $last_row =
                            mysqli_fetch_assoc(
                                $seq_query
                            );


                        $last_num =
                            (int)substr(
                                $last_row['batch_id'],
                                -3
                            );


                        $next_num =
                            str_pad(
                                $last_num + 1,
                                3,
                                '0',
                                STR_PAD_LEFT
                            );

                    } else {

                        $next_num = "001";

                    }


                    $batch_id =
                        $prefix
                        .
                        $next_num;


                    /*
                       Insert adjustment as a new
                       inventory history record
                    */

                    mysqli_query(
                        $conn,
                        "
                        INSERT INTO egg_inventory
                        (
                            batch_id,
                            harvest_date,
                            harvest_time,
                            egg_size,
                            quantity,
                            current_stock,
                            movement_type,
                            reason,
                            date_logged
                        )
                        VALUES
                        (
                            '$batch_id',
                            '" . date('Y-m-d') . "',
                            '" . date('H:i:s') . "',
                            '$egg_size',
                            '$adjustment',
                            '$new_stock',
                            'Adjustment',
                            'Adjustment',
                            '$current_date'
                        )
                        "
                    );

                }

            }


        /* =================================================
           NORMAL EGG STOCK IN / STOCK OUT
        ================================================= */

        } else {

            $batch_id = mysqli_real_escape_string(
                $conn,
                $_POST['batch_id']
            );


            $harvest_date =
                $_POST['harvest_date'];


            $harvest_time =
                $_POST['harvest_time'];


            $egg_size = mysqli_real_escape_string(
                $conn,
                $_POST['egg_size']
            );


            $quantity = intval(
                $_POST['quantity']
            );


            /*
               Get latest current stock
            */

            $stock_query = mysqli_query(
                $conn,
                "
                SELECT current_stock
                FROM egg_inventory
                WHERE egg_size = '$egg_size'
                ORDER BY id DESC
                LIMIT 1
                "
            );


            if (
                $stock_query &&
                mysqli_num_rows(
                    $stock_query
                ) > 0
            ) {

                $stock_row =
                    mysqli_fetch_assoc(
                        $stock_query
                    );


                $current_stock =
                    intval(
                        $stock_row['current_stock']
                    );

            } else {

                $current_stock = 0;

            }


            /*
               Compute new stock
            */

            if ($movement == "Stock In") {

                $new_stock =
                    $current_stock
                    +
                    $quantity;

            } elseif ($movement == "Stock Out") {

                $new_stock =
                    $current_stock
                    -
                    $quantity;


                if ($new_stock < 0) {

                    $new_stock = 0;

                }

            }


            /*
               Insert inventory record
            */

            mysqli_query(
                $conn,
                "
                INSERT INTO egg_inventory
                (
                    batch_id,
                    harvest_date,
                    harvest_time,
                    egg_size,
                    quantity,
                    current_stock,
                    movement_type,
                    reason,
                    date_logged
                )
                VALUES
                (
                    '$batch_id',
                    '$harvest_date',
                    '$harvest_time',
                    '$egg_size',
                    '$quantity',
                    '$new_stock',
                    '$movement',
                    '$reason',
                    '$current_date'
                )
                "
            );

        }

    }


    /* =====================================================
       SUPPLY INVENTORY CRUD
    ===================================================== */

    if (isset($_POST['action_supply'])) {

        $movement = mysqli_real_escape_string(
            $conn,
            $_POST['action_supply']
        );


        /* =================================================
           AUTOMATIC REASON
        ================================================= */

        if ($movement == "Stock In") {

            $reason = "Stock In";

        } elseif ($movement == "Stock Out") {

            $reason = "Stock Out";

        } else {

            $reason = "Stock Adjustment";

        }


        /* =================================================
           SUPPLY STOCK ADJUSTMENT
        ================================================= */

        if ($movement == "Stock Adjustment") {

            $selected_id = intval(
                $_POST['selected_supply_id']
            );

            $adjustment = intval(
                $_POST['quantity']
            );


            if ($selected_id > 0 && $adjustment != 0) {

                $selected_query = mysqli_query(
                    $conn,
                    "
                    SELECT *
                    FROM supply_inventory
                    WHERE id = '$selected_id'
                    LIMIT 1
                    "
                );


                if (
                    $selected_query &&
                    mysqli_num_rows(
                        $selected_query
                    ) > 0
                ) {

                    $selected_row =
                        mysqli_fetch_assoc(
                            $selected_query
                        );


                    $category =
                        mysqli_real_escape_string(
                            $conn,
                            $selected_row[
                                'item_category'
                            ]
                        );


                    $item_name =
                        mysqli_real_escape_string(
                            $conn,
                            $selected_row[
                                'item_name'
                            ]
                        );


                    /*
                       Get latest stock for the
                       selected supply item
                    */

                    $latest_stock_query =
                        mysqli_query(
                            $conn,
                            "
                            SELECT current_stock
                            FROM supply_inventory
                            WHERE item_category =
                            '$category'
                            AND item_name =
                            '$item_name'
                            ORDER BY id DESC
                            LIMIT 1
                            "
                        );


                    if (
                        $latest_stock_query &&
                        mysqli_num_rows(
                            $latest_stock_query
                        ) > 0
                    ) {

                        $latest_stock_row =
                            mysqli_fetch_assoc(
                                $latest_stock_query
                            );


                        $current_stock =
                            intval(
                                $latest_stock_row[
                                    'current_stock'
                                ]
                            );

                    } else {

                        $current_stock = 0;

                    }


                    $new_stock =
                        $current_stock
                        +
                        $adjustment;


                    if ($new_stock < 0) {

                        $new_stock = 0;

                    }


                    /*
                       Generate supply batch ID
                    */

                    if ($category == "Feeds") {

                        $code = "FD";

                    } elseif ($category == "Trays") {

                        $code = "TR";

                    } else {

                        $code = "MD";

                    }


                    $today = date("Ymd");

                    $prefix =
                        $code
                        .
                        $today
                        .
                        "-";


                    $batch_query = mysqli_query(
                        $conn,
                        "
                        SELECT batch_id
                        FROM supply_inventory
                        WHERE batch_id LIKE '$prefix%'
                        ORDER BY id DESC
                        LIMIT 1
                        "
                    );


                    if (
                        $batch_query &&
                        mysqli_num_rows(
                            $batch_query
                        ) > 0
                    ) {

                        $row =
                            mysqli_fetch_assoc(
                                $batch_query
                            );


                        $last =
                            (int)substr(
                                $row['batch_id'],
                                -3
                            );


                        $next =
                            str_pad(
                                $last + 1,
                                3,
                                '0',
                                STR_PAD_LEFT
                            );

                    } else {

                        $next = "001";

                    }


                    $batch_id =
                        $prefix
                        .
                        $next;


                    /*
                       Insert adjustment history
                    */

                    mysqli_query(
                        $conn,
                        "
                        INSERT INTO supply_inventory
                        (
                            batch_id,
                            item_category,
                            item_name,
                            quantity,
                            current_stock,
                            action_type,
                            reason,
                            date_logged
                        )
                        VALUES
                        (
                            '$batch_id',
                            '$category',
                            '$item_name',
                            '$adjustment',
                            '$new_stock',
                            'Adjustment',
                            'Adjustment',
                            '$current_date'
                        )
                        "
                    );

                }

            }


        /* =================================================
           NORMAL SUPPLY STOCK IN / STOCK OUT
        ================================================= */

        } else {

            $category = mysqli_real_escape_string(
                $conn,
                $_POST['category']
            );


            $item_name =
                mysqli_real_escape_string(
                    $conn,
                    $_POST['item_name']
                );


            $quantity = intval(
                $_POST['quantity']
            );


            /*
               Generate batch ID
            */

            if ($category == "Feeds") {

                $code = "FD";

            } elseif ($category == "Trays") {

                $code = "TR";

            } else {

                $code = "MD";

            }


            $today = date("Ymd");

            $prefix =
                $code
                .
                $today
                .
                "-";


            $batch_query = mysqli_query(
                $conn,
                "
                SELECT batch_id
                FROM supply_inventory
                WHERE batch_id LIKE '$prefix%'
                ORDER BY id DESC
                LIMIT 1
                "
            );


            if (
                $batch_query &&
                mysqli_num_rows(
                    $batch_query
                ) > 0
            ) {

                $row =
                    mysqli_fetch_assoc(
                        $batch_query
                    );


                $last =
                    (int)substr(
                        $row['batch_id'],
                        -3
                    );


                $next =
                    str_pad(
                        $last + 1,
                        3,
                        '0',
                        STR_PAD_LEFT
                    );

            } else {

                $next = "001";

            }


            $batch_id =
                $prefix
                .
                $next;


            /*
               Get latest stock
            */

            $stock_query = mysqli_query(
                $conn,
                "
                SELECT current_stock
                FROM supply_inventory
                WHERE item_category =
                '$category'
                AND item_name =
                '$item_name'
                ORDER BY id DESC
                LIMIT 1
                "
            );


            if (
                $stock_query &&
                mysqli_num_rows(
                    $stock_query
                ) > 0
            ) {

                $stock =
                    mysqli_fetch_assoc(
                        $stock_query
                    );


                $current_stock =
                    intval(
                        $stock['current_stock']
                    );

            } else {

                $current_stock = 0;

            }


            /*
               Compute new stock
            */

            if ($movement == "Stock In") {

                $new_stock =
                    $current_stock
                    +
                    $quantity;

            } elseif ($movement == "Stock Out") {

                $new_stock =
                    $current_stock
                    -
                    $quantity;


                if ($new_stock < 0) {

                    $new_stock = 0;

                }

            }


            /*
               Insert supply inventory
            */

            mysqli_query(
                $conn,
                "
                INSERT INTO supply_inventory
                (
                    batch_id,
                    item_category,
                    item_name,
                    quantity,
                    current_stock,
                    action_type,
                    reason,
                    date_logged
                )
                VALUES
                (
                    '$batch_id',
                    '$category',
                    '$item_name',
                    '$quantity',
                    '$new_stock',
                    '$movement',
                    '$reason',
                    '$current_date'
                )
                "
            );

        }

    }


    /* PREVENT DOUBLE FORM SUBMISSION */

    header("Location: inventory.php");

    exit();

}


/* =========================================================
   2. GET ACTIVE CATEGORY FILTER
========================================================= */

$filter_category =
    isset($_GET['filter_cat'])
    ? mysqli_real_escape_string(
        $conn,
        $_GET['filter_cat']
    )
    : 'All';


$egg_search =
    isset($_GET['egg_search'])
    ? mysqli_real_escape_string(
        $conn,
        $_GET['egg_search']
    )
    : "";


$supply_search =
    isset($_GET['supply_search'])
    ? mysqli_real_escape_string(
        $conn,
        $_GET['supply_search']
    )
    : "";


$history_search =
    isset($_GET['history_search'])
    ? mysqli_real_escape_string(
        $conn,
        $_GET['history_search']
    )
    : "";


/* =========================================================
   3. GET NEXT EGG BATCH ID
========================================================= */

$today_str = date('Ymd');

$prefix =
    "EG"
    .
    $today_str
    .
    "-";


$seq_query = mysqli_query(
    $conn,
    "
    SELECT batch_id
    FROM egg_inventory
    WHERE batch_id LIKE '$prefix%'
    ORDER BY id DESC
    LIMIT 1
    "
);


if (
    $seq_query &&
    mysqli_num_rows(
        $seq_query
    ) > 0
) {

    $last_row =
        mysqli_fetch_assoc(
            $seq_query
        );


    $last_num =
        (int)substr(
            $last_row['batch_id'],
            -3
        );


    $next_num =
        str_pad(
            $last_num + 1,
            3,
            '0',
            STR_PAD_LEFT
        );

} else {

    $next_num = '001';

}


$next_batch_id =
    $prefix
    .
    $next_num;


/* =========================================================
   4. SUPPLY NEXT BATCH IDS
========================================================= */

function getNextSupplyBatch(
    $conn,
    $code
) {

    $today =
        date("Ymd");

    $prefix =
        $code
        .
        $today
        .
        "-";


    $query = mysqli_query(
        $conn,
        "
        SELECT batch_id
        FROM supply_inventory
        WHERE batch_id LIKE '$prefix%'
        ORDER BY id DESC
        LIMIT 1
        "
    );


    if (
        $query &&
        mysqli_num_rows(
            $query
        ) > 0
    ) {

        $row =
            mysqli_fetch_assoc(
                $query
            );


        $last =
            (int)substr(
                $row['batch_id'],
                -3
            );


        $next =
            str_pad(
                $last + 1,
                3,
                '0',
                STR_PAD_LEFT
            );

    } else {

        $next = "001";

    }


    return
        $prefix
        .
        $next;

}


$next_feed_batch =
    getNextSupplyBatch(
        $conn,
        "FD"
    );


$next_tray_batch =
    getNextSupplyBatch(
        $conn,
        "TR"
    );


$next_medicine_batch =
    getNextSupplyBatch(
        $conn,
        "MD"
    );


/* =========================================================
   5. INVENTORY NOTIFICATIONS
========================================================= */

$low_stock_threshold = 10;

$inventory_notifications =
    array();


/* LOW EGG STOCK */

$egg_notification_query =
    mysqli_query(
        $conn,
        "
        SELECT egg_size, current_stock
        FROM egg_inventory
        WHERE id IN (
            SELECT MAX(id)
            FROM egg_inventory
            GROUP BY egg_size
        )
        AND current_stock <=
        $low_stock_threshold
        ORDER BY current_stock ASC
        "
    );


if ($egg_notification_query) {

    while (
        $row =
        mysqli_fetch_assoc(
            $egg_notification_query
        )
    ) {

        $inventory_notifications[] =
            array(
                'type' => 'Eggs',
                'item' => $row['egg_size'],
                'stock' =>
                    $row['current_stock']
            );

    }

}


/* LOW SUPPLY STOCK */

$supply_notification_query =
    mysqli_query(
        $conn,
        "
        SELECT
            item_category,
            item_name,
            current_stock

        FROM supply_inventory

        WHERE id IN (
            SELECT MAX(id)
            FROM supply_inventory
            GROUP BY
                item_category,
                item_name
        )

        AND current_stock <=
        $low_stock_threshold

        ORDER BY current_stock ASC
        "
    );


if ($supply_notification_query) {

    while (
        $row =
        mysqli_fetch_assoc(
            $supply_notification_query
        )
    ) {

        $inventory_notifications[] =
            array(
                'type' =>
                    $row['item_category'],

                'item' =>
                    $row['item_name'],

                'stock' =>
                    $row['current_stock']
            );

    }

}


$notification_count =
    count(
        $inventory_notifications
    );

/* =========================================================
   6. INVENTORY REPORT SUMMARY
   STOCK IN RECORDS ONLY
========================================================= */

/*
   IMPORTANT:

   These report summaries are based on STOCK IN
   transactions only.

   The JavaScript will further filter these totals
   based on the selected date.
*/


/* EGGS - STOCK IN ONLY */

$egg_summary_query =
    mysqli_query(
        $conn,
        "
        SELECT
            SUM(quantity)
            AS total_stock

        FROM egg_inventory

        WHERE movement_type =
        'Stock In'
        "
    );


$egg_summary = 0;


if ($egg_summary_query) {

    $egg_summary_row =
        mysqli_fetch_assoc(
            $egg_summary_query
        );


    if (
        $egg_summary_row
        &&
        $egg_summary_row['total_stock']
    ) {

        $egg_summary =
            intval(
                $egg_summary_row[
                    'total_stock'
                ]
            );

    }

}


/* FEEDS - STOCK IN ONLY */

$feeds_summary_query =
    mysqli_query(
        $conn,
        "
        SELECT
            SUM(quantity)
            AS total_stock

        FROM supply_inventory

        WHERE item_category =
        'Feeds'

        AND action_type =
        'Stock In'
        "
    );


$feeds_summary = 0;


if ($feeds_summary_query) {

    $feeds_summary_row =
        mysqli_fetch_assoc(
            $feeds_summary_query
        );


    if (
        $feeds_summary_row
        &&
        $feeds_summary_row['total_stock']
    ) {

        $feeds_summary =
            intval(
                $feeds_summary_row[
                    'total_stock'
                ]
            );

    }

}


/* TRAYS - STOCK IN ONLY */

$trays_summary_query =
    mysqli_query(
        $conn,
        "
        SELECT
            SUM(quantity)
            AS total_stock

        FROM supply_inventory

        WHERE item_category =
        'Trays'

        AND action_type =
        'Stock In'
        "
    );


$trays_summary = 0;


if ($trays_summary_query) {

    $trays_summary_row =
        mysqli_fetch_assoc(
            $trays_summary_query
        );


    if (
        $trays_summary_row
        &&
        $trays_summary_row['total_stock']
    ) {

        $trays_summary =
            intval(
                $trays_summary_row[
                    'total_stock'
                ]
            );

    }

}


/* MEDICINE - STOCK IN ONLY */

$medicine_summary_query =
    mysqli_query(
        $conn,
        "
        SELECT
            SUM(quantity)
            AS total_stock

        FROM supply_inventory

        WHERE item_category =
        'Medicine'

        AND action_type =
        'Stock In'
        "
    );


$medicine_summary = 0;


if ($medicine_summary_query) {

    $medicine_summary_row =
        mysqli_fetch_assoc(
            $medicine_summary_query
        );


    if (
        $medicine_summary_row
        &&
        $medicine_summary_row['total_stock']
    ) {

        $medicine_summary =
            intval(
                $medicine_summary_row[
                    'total_stock'
                ]
            );

    }

}


/* TOTAL SUPPLY STOCK IN */

$supply_summary =
    $feeds_summary
    +
    $trays_summary
    +
    $medicine_summary;


/* =========================================================
   7. INVENTORY HISTORY QUERY
========================================================= */

$union_query = "

SELECT

    date_logged,

    'Eggs'
    AS category,

    movement_type
    AS action_type,

    egg_size
    AS item_detail,

    batch_id,

    quantity,

    reason

FROM egg_inventory


UNION ALL


SELECT

    date_logged,

    item_category
    AS category,

    action_type,

    item_name
    AS item_detail,

    batch_id,

    quantity,

    reason

FROM supply_inventory

";


if ($filter_category == "All") {

    $report_query = "

        SELECT *

        FROM (

            $union_query

        ) AS combined_ledger

    ";

} else {

    $report_query = "

        SELECT *

        FROM (

            $union_query

        ) AS combined_ledger

        WHERE category =
        '$filter_category'

    ";

}


$report_query .=
    " ORDER BY date_logged DESC";


$report_res =
    mysqli_query(
        $conn,
        $report_query
    );


$total_report_records = 0;


if ($report_res) {

    $total_report_records =
        mysqli_num_rows(
            $report_res
        );

}


/* =========================================================
   DAILY EGG HARVEST SUMMARY
   JAVASCRIPT WILL SHOW/HIDE THIS BASED ON DATE FILTER
========================================================= */

$daily_harvest_query =
    mysqli_query(
        $conn,
        "
        SELECT
            harvest_date,
            egg_size,
            SUM(quantity)
            AS total_eggs

        FROM egg_inventory

        WHERE movement_type =
        'Stock In'

        AND reason =
        'Harvest'

        GROUP BY
            harvest_date,
            egg_size

        ORDER BY
            harvest_date DESC,
            egg_size ASC
        "
    );

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <title>
        VDVC Manager Access -
        Full Inventory Control & Logs
    </title>


    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >


    <style>

/* =========================================================
   GENERAL
========================================================= */

html,
body {

    margin: 0;

    padding: 0;

}


body {

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    background:
        linear-gradient(
            135deg,
            #eeece6 0%,
            #f5f3ed 100%
        );

    color: #3f4b45;

}


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

.inventory-outer-card {

    width: 100%;

    min-width: 0;

    min-height:
        calc(
            100vh - 36px
        );

    margin: 0;

    padding: 0;

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

}


.inventory-outer-card
> .container-fluid {

    padding-top: 18px;

    padding-bottom: 18px;

}


/* =========================================================
   MAIN INVENTORY CARDS
========================================================= */

.row
> .col-lg-6 {

    display: flex;

}


.row
> .col-lg-6
> .card {

    width: 100%;

    height: 100%;

    min-height: 500px;

    display: flex;

    flex-direction: column;

    border:
        1px solid #e5e7eb;

    border-radius: 10px;

    box-shadow:
        0 2px 5px
        rgba(
            0,
            0,
            0,
            0.04
        );

    margin-bottom: 18px;

}


.row
> .col-lg-6
> .card
.card-body {

    flex: 1;

}


.card-header {

    background-color:
        #ffffff;

    border-bottom:
        1px solid #f0f1f3;

    font-weight: 600;

    font-size: 0.95rem;

    padding:
        12px 14px;

    border-top-left-radius:
        10px !important;

    border-top-right-radius:
        10px !important;

}


.card-header small {

    font-size:
        0.75rem;

    font-weight: 400;

}


.card-body {

    padding: 14px;

}


/* =========================================================
   TEXT
========================================================= */

body,
.form-control,
.form-select,
.btn {

    font-size: 0.82rem;

}


h6 {

    font-size: 0.88rem;

}


.small {

    font-size:
        0.75rem !important;

}


/* =========================================================
   TABLE
========================================================= */

.table {

    margin-bottom: 0;

}


.table th {

    background-color:
        #f9fafb;

    color: #4b5563;

    font-weight: 600;

    font-size: 0.72rem;

    text-transform:
        uppercase;

    white-space:
        nowrap;

    padding:
        8px 9px;

}


.table td {

    vertical-align:
        middle;

    font-size:
        0.78rem;

    padding:
        8px 9px;

}


.selectable-row {

    cursor: pointer;

}


.selectable-row.selected {

    background:
        #eaf4ec !important;

    outline:
        2px solid
        rgba(
            82,
            125,
            89,
            0.35
        );

}


/* =========================================================
   SEARCH BOX
========================================================= */

.input-group
.form-control {

    font-size: 0.8rem;

    padding:
        7px 10px;

}


.input-group
.btn {

    font-size:
        0.78rem;

    padding:
        7px 11px;

}


/* =========================================================
   BUTTONS
========================================================= */

.btn-sm {

    font-size:
        0.76rem !important;

    padding:
        6px 10px;

}


.btn-custom-blue {

    background-color:
        #4682b4;

    color: white;

}


.btn-custom-blue:hover {

    background-color:
        #356a93;

    color: white;

}


.btn-custom-danger {

    background-color:
        #cd5c5c;

    color: white;

}


.btn-custom-danger:hover {

    background-color:
        #b04f4f;

    color: white;

}


/* =========================================================
   SUPPLY ICON CARDS
========================================================= */

.supply-icon-card {

    text-align: center;

    padding: 10px;

    border:
        1px solid #e5e7eb;

    border-radius: 8px;

    background:
        #fafafa;

}


.supply-icon-card i {

    font-size:
        1.45rem;

    color:
        #4b5563;

}


.supply-icon-card
.small {

    font-size:
        0.74rem !important;

}


/* =========================================================
   BADGES
========================================================= */

.badge {

    font-size:
        0.68rem;

    font-weight: 500;

    padding:
        4px 7px;

}


.status-badge-fan {

    background-color:
        #e6f7ed;

    color:
        #1f9254;

    padding:
        3px 10px;

    border-radius:
        20px;

    font-size:
        0.72rem;

    font-weight: 600;

}


/* =========================================================
   MODAL
========================================================= */

.modal-title {

    font-size:
        0.95rem;

}


.modal-body,
.modal-footer {

    font-size:
        0.82rem;

}


.form-label {

    font-size:
        0.78rem;

    font-weight: 600;

}


/* =========================================================
   PAGE HEADER
========================================================= */



/* =========================================================
   NOTIFICATIONS
========================================================= */



.notification-badge {

    position: absolute;

    top: -4px;

    right: -2px;

    min-width: 18px;

    height: 18px;

    padding:
        1px 5px;

    border-radius:
        20px;

    background:
        #ef4444;

    color:
        #ffffff;

    font-size:
        0.65rem;

    font-weight: 700;

    text-align: center;

    line-height:
        16px;

}


.notification-dropdown {

    display: none;

    position: absolute;

    top: 58px;

    right: 0;

    width: 350px;

    background:
        #ffffff;

    border:
        1px solid #e5e7eb;

    border-radius:
        12px;

    box-shadow:
        0 10px 30px
        rgba(
            0,
            0,
            0,
            0.12
        );

    z-index: 9999;

    overflow: hidden;

}


.notification-dropdown.show {

    display: block;

}


.notification-header {

    display: flex;

    justify-content:
        space-between;

    align-items:
        center;

    padding:
        14px 16px;

    border-bottom:
        1px solid #f0f1f3;

}


.notification-header strong {

    display: block;

    color:
        #172033;

    font-size:
        0.85rem;

}


.notification-header small {

    display: block;

    color:
        #64748b;

    font-size:
        0.7rem;

    margin-top: 2px;

}


.notification-total {

    min-width: 24px;

    height: 24px;

    border-radius:
        50%;

    background:
        #fee2e2;

    color:
        #dc2626;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size:
        0.7rem;

    font-weight: 700;

}


.notification-list {

    max-height: 320px;

    overflow-y: auto;

}


.notification-item {

    display: flex;

    align-items: center;

    gap: 10px;

    padding:
        12px 14px;

    border-bottom:
        1px solid #f1f5f9;

}


.notification-item:hover {

    background:
        #f8fafc;

}


.notification-item-icon {

    width: 34px;

    height: 34px;

    min-width: 34px;

    border-radius:
        50%;

    background:
        #fff7ed;

    color:
        #f97316;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size:
        0.8rem;

}


.notification-item-content {

    display: flex;

    flex-direction:
        column;

    min-width: 0;

    flex: 1;

}


.notification-item-content strong {

    color:
        #172033;

    font-size:
        0.78rem;

}


.notification-item-content span {

    color:
        #64748b;

    font-size:
        0.68rem;

    margin-top: 1px;

}


.notification-item-content small {

    color:
        #475569;

    font-size:
        0.68rem;

    margin-top: 2px;

}


.low-stock-label {

    font-size:
        0.58rem;

    font-weight: 700;

    color:
        #dc2626;

    background:
        #fee2e2;

    padding:
        4px 6px;

    border-radius:
        6px;

    white-space:
        nowrap;

}


.notification-empty {

    padding:
        28px 18px;

    text-align:
        center;

}


.notification-empty i {

    display: block;

    font-size:
        1.8rem;

    color:
        #22c55e;

    margin-bottom:
        8px;

}


.notification-empty strong {

    display: block;

    font-size:
        0.8rem;

    color:
        #172033;

}


.notification-empty span {

    display: block;

    font-size:
        0.68rem;

    color:
        #64748b;

    margin-top:
        4px;

}


.notification-footer {

    padding:
        9px 14px;

    border-top:
        1px solid #f0f1f3;

    background:
        #f9fafb;

    color:
        #64748b;

    font-size:
        0.65rem;

}


/* =========================================================
   MANAGER ACCOUNT
========================================================= */
/* =========================================================
   INVENTORY PAGE HEADER
========================================================= */

.inventory-page-header {

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
   HEADER LEFT
========================================================= */

.inventory-header-left {

    display:
        flex;

    align-items:
        center;

}


.inventory-header-left h1 {

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


/* HIDE THE OLD DESCRIPTION */

.inventory-header-left p {

    display:
        none;

}


/* =========================================================
   HEADER RIGHT
========================================================= */

.inventory-header-right {

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

.notification-wrapper {

    position:
        relative;

    display:
        flex;

    align-items:
        center;

}


.notification-icon {

    width:
        40px;

    height:
        40px;

    border:
        none;

    background:
        transparent;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

    position:
        relative;

    cursor:
        pointer;

    color:
        #65716b;

    font-size:
        1.2rem;

    transition:
        0.2s ease;

}


.notification-icon:hover {

    color:
        #527d59;

}


.notification-badge {

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

.inventory-header-divider {

    width:
        1px;

    height:
        42px;

    background:
        #deded8;

}


/* =========================================================
   MANAGER PROFILE AREA
========================================================= */

.manager-profile-header {

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    min-width:
        190px;

}


/* =========================================================
   MANAGER AVATAR
========================================================= */

.manager-avatar {

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
   MANAGER ACCOUNT DETAILS
========================================================= */

.manager-account {

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    min-width:
        100px;

}


.manager-account strong {

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


.manager-account > span {

    display:
        block;

    margin-top:
        3px;

    color:
        #7c8781;

    font-size:
        0.72rem;

    line-height:
        1.2;

}


/* =========================================================
   DATE AND TIME
========================================================= */

.manager-date-time {

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

.manager-date {

    font-size:
        0.63rem;

    color:
        #8a9590;

}

.manager-time {

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

.manager-dropdown-icon {

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

    cursor:
        pointer;

}


/* =========================================================
   RESPONSIVE HEADER
========================================================= */

@media (max-width: 768px) {

    .inventory-page-header {

        padding:
            15px 20px;

        min-height:
            auto;

    }


    .inventory-header-left h1 {

        font-size:
            1.1rem;

    }


    .manager-profile-header {

        min-width:
            auto;

    }


    .manager-date-time {

        display:
            none;

    }


    .inventory-header-divider {

        display:
            none;

    }

}


@media (max-width: 576px) {

    .inventory-page-header {

        flex-direction:
            column;

        align-items:
            flex-start;

        gap:
            15px;

    }


    .inventory-header-right {

        width:
            100%;

        justify-content:
            flex-end;

    }

}
.account-arrow {

    color:
        #475569;

    font-size:
        0.75rem;

    margin-left:
        2px;

}


/* =========================================================
   INVENTORY HISTORY & REPORTS
========================================================= */

.inventory-reports-section {

    margin-top:
        18px;

    border:
        1px solid #e1e5e8;

    border-radius:
        14px;

    background:
        #ffffff;

    overflow:
        hidden;

    box-shadow:
        0 3px 12px
        rgba(
            0,
            0,
            0,
            0.04
        );

}


.inventory-reports-header {

    padding:
        18px 20px 14px;

    background:
        #ffffff;

    border-bottom:
        1px solid #edf0f2;

    display: flex;

    justify-content:
        space-between;

    align-items:
        center;

    gap:
        20px;

}


.inventory-reports-title {

    display: flex;

    align-items:
        flex-start;

    gap:
        12px;

}


.inventory-reports-title-icon {

    width: 40px;

    height: 40px;

    min-width: 40px;

    border-radius:
        10px;

    background:
        #f6f1e7;

    color:
        #9b7440;

    display: flex;

    align-items: center;

    justify-content:
        center;

    font-size:
        1.15rem;

}


.inventory-reports-title h5 {

    margin: 0;

    font-size:
        1.15rem;

    font-weight: 700;

    color:
        #34443d;

}


.inventory-reports-title p {

    margin:
        3px 0 0;

    font-size:
        0.78rem;

    color:
        #7b858c;

}


.report-search-wrapper {

    width: 310px;

    max-width: 100%;

    position:
        relative;

}


.report-search-wrapper i {

    position:
        absolute;

    left:
        11px;

    top:
        50%;

    transform:
        translateY(-50%);

    color:
        #a0a8ad;

    font-size:
        0.7rem;

    z-index:
        2;

}


.report-search-wrapper input {

    width:
        100%;

    height:
        34px;

    padding:
        7px 10px 7px 30px;

    border:
        1px solid #dce1e5;

    border-radius:
        7px;

    outline:
        none;

    font-size:
        0.8rem;

    color:
        #4b5563;

    background:
        #fbfcfc;

}


.report-filter-area {

    padding:
        14px 20px;

    background:
        #fafbf9;

    border-bottom:
        1px solid #e9edeb;

}


.report-date-card {

    height:
        54px;

    display:
        flex;

    align-items:
        center;

    gap:
        11px;

    padding:
        9px 12px;

    border:
        1px solid #dfe5e1;

    border-radius:
        8px;

    background:
        #ffffff;

}


.report-date-icon {

    width:
        30px;

    height:
        30px;

    border-radius:
        7px;

    background:
        #edf5ef;

    color:
        #527d59;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.report-date-content {

    display:
        flex;

    flex-direction:
        column;

    flex:
        1;

}


.report-date-content label {

    font-size:
        0.72rem;

    color:
        #8a9590;

    margin-bottom:
        1px;

}


.report-date-content input {

    border:
        none;

    outline:
        none;

    padding:
        0;

    font-size:
        0.78rem;

    color:
        #3f4b45;

    font-weight:
        600;

    background:
        transparent;

    width:
        100%;

}


.report-clear-date {

    border:
        none;

    background:
        transparent;

    color:
        #9aa3a8;

    padding:
        3px;

    cursor:
        pointer;

}


.report-category-card {

    height:
        54px;

    width:
        100%;

    display:
        flex;

    align-items:
        center;

    gap:
        9px;

    padding:
        8px 10px;

    text-decoration:
        none;

    background:
        #ffffff;

    border:
        1px solid #dfe5e1;

    border-radius:
        8px;

    color:
        #3f4b45;

}


.report-category-card.active {

    border-color:
        #527d59;

    background:
        #f5faf6;

}


.report-category-icon {

    width:
        34px;

    height:
        34px;

    min-width:
        34px;

    border-radius:
        50%;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.report-category-info {

    min-width: 0;

    flex: 1;

}


.report-category-info strong {

    display:
        block;

    font-size:
        0.78rem;

    color:
        #3f4b45;

}


.report-category-info small {

    display:
        block;

    margin-top:
        2px;

    font-size:
        0.63rem;

    color:
        #88928e;

}


.report-category-check {

    font-size:
        0.6rem;

    color:
        #527d59;

}


.category-all
.report-category-icon {

    background:
        #eef1f0;

}


.category-eggs
.report-category-icon {

    background:
        #e9f4eb;

}


.category-feeds
.report-category-icon {

    background:
        #fff6df;

}


.category-trays
.report-category-icon {

    background:
        #edf2f4;

}


.category-medicine
.report-category-icon {

    background:
        #fceaea;

}


.report-summary-bar {

    margin:
        0 20px;

    padding:
        12px 15px;

    background:
        #f4faf5;

    border:
        1px solid #dce9df;

    border-radius:
        9px;

    display:
        flex;

    align-items:
        center;

    justify-content:
        space-between;

    gap:
        15px;

}


.report-summary-left {

    display:
        flex;

    align-items:
        center;

    gap:
        10px;

}


.report-summary-icon {

    width:
        32px;

    height:
        32px;

    border-radius:
        8px;

    background:
        #e4f1e7;

    color:
        #527d59;

    display:
        flex;

    align-items:
        center;

    justify-content:
        center;

}


.report-summary-text strong {

    display:
        block;

    color:
        #46634e;

    font-size:
        0.8rem;

}


.report-summary-text span {

    display:
        block;

    color:
        #7a8a80;

    font-size:
        0.68rem;

    margin-top:
        2px;

}


.report-summary-stats {

    display:
        flex;

    align-items:
        center;

}


.report-summary-stat {

    padding:
        0 22px;

    text-align:
        center;

    border-left:
        1px solid #dce7de;

}


.report-summary-stat:first-child {

    border-left:
        none;

}


.report-summary-stat small {

    display:
        block;

    font-size:
        0.65rem;

    color:
        #8b9690;

}


.report-summary-stat strong {

    display:
        block;

    color:
        #3f4b45;

    font-size:
        0.92rem;

}


/* DAILY HARVEST */

.daily-harvest-wrapper {

    margin:
        0 20px 15px;

    display:
        none;

    border:
        1px solid #dce9df;

    border-radius:
        9px;

    overflow:
        hidden;

}


.daily-harvest-wrapper.show {

    display:
        block;

}


.daily-harvest-title {

    padding:
        12px 15px;

    font-size:
        0.88rem;

    font-weight:
        700;

    background:
        #f4faf5;

    color:
        #46634e;

}


.daily-harvest-table {

    margin:
        0;

}


.daily-harvest-table th {

    font-size:
        0.75rem;

}


.daily-harvest-table td {

    font-size:
        0.8rem;

}


/* REPORT TABLE */
/* =========================================================
   INVENTORY HISTORY TABLE BOX
========================================================= */

.report-table-wrapper {

    margin:
        0 20px 20px;

    height:
        360px;

    overflow-y:
        auto;

    overflow-x:
        auto;

    background:
        #ffffff;

    border:
        1px solid #dfe5e1;

    border-radius:
        10px;

    box-shadow:
        0 3px 10px
        rgba(
            0,
            0,
            0,
            0.04
        );

}


.report-history-table {

    margin-bottom:
        0;

    min-width:
        950px;

    background:
        #ffffff;

}
.report-history-table thead th {

    position:
        sticky;

    top:
        0;

    z-index:
        5;

    background:
        #f3f5f4;

    color:
        #65716b;

    border-bottom:
        1px solid #dfe4e1;

    font-size:
        0.7rem;

    font-weight:
        700;

    text-transform:
        uppercase;

    padding:
        9px 12px;

    white-space:
        nowrap;

}


.report-history-table tbody td {

    padding:
        9px 12px;

    font-size:
        0.75rem;

    color:
        #43504a;

    border-color:
        #edf0ee;

    white-space:
        nowrap;

}


.report-category-badge {

    display:
        inline-block;

    font-weight:
        600;

    color:
        #3f4b45;

}


.report-action-text {

    color:
        #46564d;

    font-weight:
        500;

}


.report-quantity {

    font-weight:
        700;

    color:
        #405a48;

}


.report-empty-state {

    padding:
        35px !important;

    text-align:
        center;

    color:
        #8b9590 !important;

}


@media (max-width: 991px) {

    .main-content {

        margin-left:
            0;

    }

}



    </style>

</head>


<body>


<?php include('manager_panel.php'); ?>


<div class="main-content">

<div class="inventory-outer-card">

<!-- =========================================================
     PAGE HEADER
========================================================= -->

<div class="inventory-page-header">

    <!-- LEFT SIDE -->

    <div class="inventory-header-left">

        <h1>
            Inventory Management
        </h1>

    </div>


    <!-- RIGHT SIDE -->

    <div class="inventory-header-right">


        <!-- NOTIFICATION -->

        <div class="notification-wrapper">

            <button
                type="button"
                class="notification-icon"
                id="notificationBell"
                aria-label="Inventory Notifications"
            >

                <i class="fa-regular fa-bell"></i>


                <?php if ($notification_count > 0) { ?>

                    <span class="notification-badge">

                        <?php
                        echo $notification_count;
                        ?>

                    </span>

                <?php } ?>

            </button>


            <!-- NOTIFICATION DROPDOWN -->

            <div
                class="notification-dropdown"
                id="notificationDropdown"
            >

                <div class="notification-header">

                    <div>

                        <strong>
                            Inventory Notifications
                        </strong>

                        <small>
                            Low stock alerts
                        </small>

                    </div>


                    <span class="notification-total">

                        <?php
                        echo $notification_count;
                        ?>

                    </span>

                </div>


                <div class="notification-list">

                    <?php

                    if ($notification_count > 0) {

                        foreach (
                            $inventory_notifications
                            as $notification
                        ) {

                    ?>

                        <div class="notification-item">

                            <div
                                class="notification-item-icon"
                            >

                                <i
                                    class="fa-solid fa-box"
                                ></i>

                            </div>


                            <div
                                class="notification-item-content"
                            >

                                <strong>

                                    <?php
                                    echo htmlspecialchars(
                                        $notification['item']
                                    );
                                    ?>

                                </strong>


                                <span>

                                    <?php
                                    echo htmlspecialchars(
                                        $notification['type']
                                    );
                                    ?>

                                </span>


                                <small>

                                    Current stock:

                                    <b>

                                        <?php
                                        echo number_format(
                                            $notification['stock']
                                        );
                                        ?>

                                    </b>

                                </small>

                            </div>


                            <div class="low-stock-label">

                                Low Stock

                            </div>

                        </div>

                    <?php

                        }

                    } else {

                    ?>

                        <div
                            class="notification-empty"
                        >

                            <i
                                class="fa-solid fa-circle-check"
                            ></i>

                            <strong>
                                No low stock alerts
                            </strong>

                            <span>
                                All inventory stocks are
                                currently sufficient.
                            </span>

                        </div>

                    <?php } ?>

                </div>

            </div>

        </div>


        <!-- HEADER DIVIDER -->

        <div class="inventory-header-divider"></div>


        <!-- MANAGER PROFILE -->

        <div class="manager-profile-header">


            <!-- AVATAR -->

            <div class="manager-avatar">

                <i class="fa-solid fa-user"></i>

            </div>


            <!-- ACCOUNT DETAILS -->

            <div class="manager-account">

                <strong>
                    Manager
                </strong>

               


                <!-- DATE AND TIME -->

           <div class="manager-date-time">

    <span class="manager-date">

        <?php
        echo date('F d, Y');
        ?>

    </span>

    <span class="manager-time">

        <span
            id="inventoryClock"
        ></span>

    </span>

</div>
            </div>


            <!-- DROPDOWN ICON -->

            <div class="manager-dropdown-icon">

                <i class="fa-solid fa-chevron-down"></i>

            </div>

        </div>

    </div>

</div>

<div class="container-fluid px-4">

<div class="row">


<!-- =========================================================
     EGG INVENTORY
========================================================= -->

<div class="col-lg-6">

<div class="card">

<div
    class="card-header d-flex justify-content-between align-items-center"
>

    <span>

        EGGS

        <small class="text-muted">

            Comprehensive Egg Inventory & CRUD Logs

        </small>

    </span>

</div>


<div class="card-body">

<h6 class="fw-bold mb-3">

    Detailed Egg Stock Ledger

</h6>


<form
    id="eggSearchForm"
    class="mb-3"
>

    <div class="input-group">

        <input
            type="search"
            id="egg_search"
            class="form-control"
            placeholder="Search Batch ID, Egg Size, Movement, Harvest Date or Reason..."
        >

        <button
            type="button"
            id="eggSearchBtn"
            class="btn btn-primary"
        >

            <i
                class="fa-solid fa-search"
            ></i>

        </button>

    </div>

</form>


<div
    class="table-responsive"
    style="
        height: 375px;
        overflow-y: auto;
    "
>

<table
    id="eggTable"
    class="table table-hover align-middle"
>

<thead>

<tr>

    <th>Batch ID</th>

    <th>Harvest Date</th>

    <th>Harvest Time</th>

    <th>Egg Size</th>

    <th>Movement</th>

    <th>Quantity</th>

    <th>Reason</th>

</tr>

</thead>


<tbody>

<?php

$egg_res =
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM egg_inventory
        ORDER BY id DESC
        "
    );


while (
    $row =
    mysqli_fetch_assoc(
        $egg_res
    )
) {

?>

<tr
    class="selectable-row egg-row"
    data-id="<?php echo $row['id']; ?>"
    data-size="<?php echo htmlspecialchars($row['egg_size']); ?>"
    data-stock="<?php echo intval($row['current_stock']); ?>"
>

    <td>

        <?php
        echo htmlspecialchars(
            $row['batch_id']
        );
        ?>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['harvest_date']
        );
        ?>

    </td>


    <td>

        <?php
        echo date(
            'h:i A',
            strtotime(
                $row['harvest_time']
            )
        );
        ?>

    </td>


    <td>

        <span class="badge bg-secondary">

            <?php
            echo htmlspecialchars(
                $row['egg_size']
            );
            ?>

        </span>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['movement_type']
        );
        ?>

    </td>


    <td>

        <?php
        echo number_format(
            $row['quantity']
        );
        ?>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['reason']
        );
        ?>

    </td>

</tr>

<?php } ?>

</tbody>

</table>

</div>


<div
    class="mt-3 d-flex flex-wrap gap-2"
>

    <button
        class="btn btn-success btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#eggModal"
        onclick="setupEggModal('Stock In')"
    >

        Harvest Eggs

    </button>


    <button
        class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#eggModal"
        onclick="setupEggModal('Stock Out')"
    >

        Stock Out

    </button>


    <button
        class="btn btn-primary btn-sm"
        type="button"
        onclick="openEggAdjustment()"
    >

        Stock Adjustment

    </button>

</div>

</div>

</div>

</div>


<!-- =========================================================
     SUPPLY INVENTORY
========================================================= -->

<div class="col-lg-6">

<div class="card">

<div
    class="card-header d-flex justify-content-between align-items-center"
>

    <span>

        SUPPLIES

        <small class="text-muted">

            Detailed Supply Inventory & CRUD Logs

        </small>

    </span>

</div>


<div class="card-body">

<div
    class="row g-2 mb-3 text-center"
>

    <div class="col-4">

        <div class="supply-icon-card">

            <i
                class="fa-solid fa-wheat-awn mb-1 text-warning"
            ></i>

            <div class="small fw-bold">

                Feeds

            </div>

        </div>

    </div>


    <div class="col-4">

        <div class="supply-icon-card">

            <i
                class="fa-solid fa-boxes-stacked mb-1 text-secondary"
            ></i>

            <div class="small fw-bold">

                Trays

            </div>

        </div>

    </div>


    <div class="col-4">

        <div class="supply-icon-card">

            <i
                class="fa-solid fa-prescription-bottle-medical mb-1 text-danger"
            ></i>

            <div class="small fw-bold">

                Medicine

            </div>

        </div>

    </div>

</div>


<h6 class="fw-bold mb-2">

    Unified Supply Stock Logs

</h6>


<div class="input-group mb-3">

    <input
        type="search"
        id="supply_search"
        class="form-control"
        placeholder="Search Batch ID, Category, Item Name, Movement or Reason..."
    >

    <button
        type="button"
        class="btn btn-primary"
    >

        <i
            class="fa-solid fa-search"
        ></i>

    </button>

</div>


<div
    class="table-responsive"
    style="
        height: 300px;
        overflow-y: auto;
    "
>

<table
    id="supplyTable"
    class="table table-hover align-middle"
>

<thead>

<tr>

    <th>Batch ID</th>

    <th>Category</th>

    <th>Item Name</th>

    <th>Movement</th>

    <th>Quantity</th>

    <th>Current Stock</th>

    <th>Reason</th>

</tr>

</thead>


<tbody>

<?php

$supply_res =
    mysqli_query(
        $conn,
        "
        SELECT *
        FROM supply_inventory
        ORDER BY id DESC
        "
    );


while (
    $row =
    mysqli_fetch_assoc(
        $supply_res
    )
) {

?>

<tr
    class="selectable-row supply-row"
    data-id="<?php echo $row['id']; ?>"
    data-category="<?php echo htmlspecialchars($row['item_category']); ?>"
    data-item="<?php echo htmlspecialchars($row['item_name']); ?>"
    data-stock="<?php echo intval($row['current_stock']); ?>"
>

    <td>

        <?php
        echo htmlspecialchars(
            $row['batch_id']
        );
        ?>

    </td>


    <td>

        <span class="badge bg-secondary">

            <?php
            echo htmlspecialchars(
                $row['item_category']
            );
            ?>

        </span>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['item_name']
        );
        ?>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['action_type']
        );
        ?>

    </td>


    <td>

        <?php
        echo number_format(
            $row['quantity']
        );
        ?>

    </td>


    <td>

        <?php
        echo number_format(
            $row['current_stock']
        );
        ?>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['reason']
        );
        ?>

    </td>

</tr>

<?php } ?>

</tbody>

</table>

</div>


<div
    class="mt-3 d-flex flex-wrap gap-2"
>

    <button
        class="btn btn-success btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#supplyModal"
        onclick="setupSupplyModal('Stock In')"
    >

        Stock In

    </button>


    <button
        class="btn btn-warning btn-sm"
        data-bs-toggle="modal"
        data-bs-target="#supplyModal"
        onclick="setupSupplyModal('Stock Out')"
    >

        Stock Out

    </button>


    <button
        class="btn btn-primary btn-sm"
        type="button"
        onclick="openSupplyAdjustment()"
    >

        Stock Adjustment

    </button>

</div>

</div>

</div>

</div>

</div>


<!-- =========================================================
     INVENTORY HISTORY & REPORTS
========================================================= -->

<div class="inventory-reports-section">


<div class="inventory-reports-header">

    <div class="inventory-reports-title">

        <div
            class="inventory-reports-title-icon"
        >

            <i
                class="fa-solid fa-chart-line"
            ></i>

        </div>


        <div>

            <h5>
                INVENTORY HISTORY & REPORTS
            </h5>

            <p>

                View and track your egg stock,
                harvests and transactions.
                Filter by date and category
                to see detailed reports.

            </p>

        </div>

    </div>


    <div class="report-search-wrapper">

        <i
            class="fa-solid fa-magnifying-glass"
        ></i>

        <input
            type="search"
            id="history_search"
            placeholder="Search categories or action types..."
        >

    </div>

</div>


<div class="report-filter-area">

<div
    class="row g-2 align-items-stretch"
>


<div
    class="col-xl-3 col-lg-4 col-md-6"
>

<div class="report-date-card">

    <div class="report-date-icon">

        <i
            class="fa-regular fa-calendar"
        ></i>

    </div>


    <div class="report-date-content">

        <label>
            Select Date
        </label>

        <input
            type="date"
            id="report_date_filter"
        >

    </div>


    <button
        type="button"
        class="report-clear-date"
        id="clearReportDate"
    >

        <i
            class="fa-solid fa-xmark"
        ></i>

    </button>

</div>

</div>


<!-- ALL -->

<div
    class="col-xl col-lg-4 col-md-6"
>

<a
    href="inventory.php?filter_cat=All"
    class="report-category-card category-all <?php echo $filter_category == 'All' ? 'active' : ''; ?>"
>

    <div class="report-category-icon">

        <i
            class="fa-solid fa-table-list"
        ></i>

    </div>


    <div class="report-category-info">

        <strong>
            All
        </strong>

        <small>
            View all records
        </small>

    </div>

</a>

</div>


<!-- EGGS -->

<div
    class="col-xl col-lg-4 col-md-6"
>

<a
    href="inventory.php?filter_cat=Eggs"
    class="report-category-card category-eggs <?php echo $filter_category == 'Eggs' ? 'active' : ''; ?>"
>

    <div class="report-category-icon">

        <i
            class="fa-solid fa-egg"
        ></i>

    </div>


    <div class="report-category-info">

        <strong>
            Eggs
        </strong>

        <small>
<?php
echo number_format(
    $egg_summary
);
?>

total stock in

        </small>

    </div>

</a>

</div>


<!-- FEEDS -->

<div
    class="col-xl col-lg-4 col-md-6"
>

<a
    href="inventory.php?filter_cat=Feeds"
    class="report-category-card category-feeds <?php echo $filter_category == 'Feeds' ? 'active' : ''; ?>"
>

    <div class="report-category-icon">

        <i
            class="fa-solid fa-wheat-awn"
        ></i>

    </div>


    <div class="report-category-info">

        <strong>
            Feeds
        </strong>

        <small>

            <?php
            echo number_format(
                $feeds_summary
            );
            ?>

          total stock in

        </small>

    </div>

</a>

</div>


<!-- TRAYS -->

<div
    class="col-xl col-lg-4 col-md-6"
>

<a
    href="inventory.php?filter_cat=Trays"
    class="report-category-card category-trays <?php echo $filter_category == 'Trays' ? 'active' : ''; ?>"
>

    <div class="report-category-icon">

        <i
            class="fa-solid fa-boxes-stacked"
        ></i>

    </div>


    <div class="report-category-info">

        <strong>
            Trays
        </strong>

        <small>

            <?php
            echo number_format(
                $trays_summary
            );
            ?>

       total stock in

        </small>

    </div>

</a>

</div>


<!-- MEDICINE -->

<div
    class="col-xl col-lg-4 col-md-6"
>

<a
    href="inventory.php?filter_cat=Medicine"
    class="report-category-card category-medicine <?php echo $filter_category == 'Medicine' ? 'active' : ''; ?>"
>

    <div class="report-category-icon">

        <i
            class="fa-solid fa-prescription-bottle-medical"
        ></i>

    </div>


    <div class="report-category-info">

        <strong>
            Medicine
        </strong>

        <small>

            <?php
            echo number_format(
                $medicine_summary
            );
            ?>

      total stock in

        </small>

    </div>

</a>

</div>

</div>

</div>


<!-- SUMMARY -->

<div class="p-3">

<div class="report-summary-bar">

<div class="report-summary-left">

    <div class="report-summary-icon">

        <i
            class="fa-solid fa-boxes-stacked"
        ></i>

    </div>


    <div class="report-summary-text">

        <strong
            id="reportSummaryTitle"
        >

            <?php

            if (
                $filter_category == "All"
            ) {

                echo
                    "All Inventory Records";

            } else {

                echo
                    $filter_category
                    .
                    " Inventory Records";

            }

            ?>

        </strong>


        <span
            id="reportSummaryDescription"
        >

            Showing all available inventory records
            for the selected category

        </span>

    </div>

</div>

<div class="report-summary-stats">

    <!-- TOTAL RECORDS -->

    <div class="report-summary-stat">

        <small>
            Total Records
        </small>

        <strong
            id="visibleRecordCount"
        >

            <?php
            echo number_format(
                $total_report_records
            );
            ?>

        </strong>

    </div>


    <!-- EGG STOCK -->

    <div class="report-summary-stat">

        <small>
            Egg Stock
        </small>

        <strong
            id="visibleEggStock"
        >

            <?php
            echo number_format(
                $egg_summary
            );
            ?>

        </strong>

    </div>


    <!-- SUPPLY STOCK -->

    <div class="report-summary-stat">

        <small>
            Supply Stock
        </small>

        <strong
            id="visibleSupplyStock"
        >

            <?php
            echo number_format(
                $supply_summary
            );
            ?>

        </strong>

    </div>

</div>

</div>

</div>


<!-- =========================================================
     DAILY EGG HARVEST MONITORING
========================================================= -->

<div
    class="daily-harvest-wrapper"
    id="dailyHarvestWrapper"
>

<div class="daily-harvest-title">

    Daily Egg Harvest Monitoring

</div>


<div class="table-responsive">

<table
    class="table daily-harvest-table"
>

<thead>

<tr>

    <th>
        Egg Size
    </th>

    <th>
        Total Eggs per Piece
    </th>

    <th>
        Total Trays
    </th>

</tr>

</thead>


<tbody>

<?php

if (
    $daily_harvest_query &&
    mysqli_num_rows(
        $daily_harvest_query
    ) > 0
) {

    while (
        $harvest_row =
        mysqli_fetch_assoc(
            $daily_harvest_query
        )
    ) {

        $total_eggs =
            intval(
                $harvest_row[
                    'total_eggs'
                ]
            );


        $total_trays =
            $total_eggs
            /
            $eggs_per_tray;

?>

<tr
    class="daily-harvest-row"
    data-harvest-date="<?php echo htmlspecialchars($harvest_row['harvest_date']); ?>"
>

    <td>

        <?php
        echo htmlspecialchars(
            $harvest_row[
                'egg_size'
            ]
        );
        ?>

    </td>


    <td>

        <?php
        echo number_format(
            $total_eggs
        );
        ?>

    </td>


    <td>

        <?php
        echo number_format(
            $total_trays,
            2
        );
        ?>

    </td>

</tr>

<?php

    }

} else {

?>

<tr>

    <td
        colspan="3"
        class="text-center text-muted"
    >

        No harvest records found.

    </td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>


<!-- HISTORY TABLE -->

<div class="report-table-wrapper">

<table
    id="historyTable"
    class="table report-history-table align-middle text-start"
>

<thead>

<tr>

    <th>Date</th>

    <th>Category</th>

    <th>Action Type</th>

    <th>Egg Size / Item</th>

    <th>Batch ID</th>

    <th>Quantity</th>

    <th>Reason</th>

</tr>

</thead>


<tbody>

<?php

if (
    $report_res &&
    mysqli_num_rows(
        $report_res
    ) > 0
) {

    while (
        $row =
        mysqli_fetch_assoc(
            $report_res
        )
    ) {

?>

<tr
    data-report-date="<?php echo date('Y-m-d', strtotime($row['date_logged'])); ?>"
    data-category="<?php echo htmlspecialchars($row['category']); ?>"
    data-action="<?php echo htmlspecialchars($row['action_type']); ?>"
    data-quantity="<?php echo intval($row['quantity']); ?>"
>

    <td>

        <?php

        echo date(
            'M d, Y',
            strtotime(
                $row['date_logged']
            )
        );

        ?>

        <br>

        <small class="text-muted">

            <?php

            echo date(
                'h:i A',
                strtotime(
                    $row['date_logged']
                )
            );

            ?>

        </small>

    </td>


    <td>

        <span
            class="report-category-badge"
        >

            <?php
            echo htmlspecialchars(
                $row['category']
            );
            ?>

        </span>

    </td>


    <td>

        <span
            class="report-action-text"
        >

            <?php
            echo htmlspecialchars(
                $row['action_type']
            );
            ?>

        </span>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['item_detail']
        );
        ?>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['batch_id']
        );
        ?>

    </td>


    <td>

        <span
            class="report-quantity"
        >

            <?php
            echo number_format(
                $row['quantity']
            );
            ?>

        </span>

    </td>


    <td>

        <?php
        echo htmlspecialchars(
            $row['reason']
        );
        ?>

    </td>

</tr>

<?php

    }

} else {

?>

<tr>

    <td
        colspan="7"
        class="report-empty-state"
    >

        No inventory records found.

    </td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

</div>


<!-- =========================================================
     EGG MODAL
========================================================= -->

<div
    class="modal fade"
    id="eggModal"
    tabindex="-1"
>

<div class="modal-dialog">

<form
    method="POST"
    class="modal-content"
>

<div class="modal-header">

    <h5
        class="modal-title"
        id="eggModalTitle"
    >

        Egg Inventory

    </h5>


    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>


<div class="modal-body">


<input
    type="hidden"
    name="action_egg"
    id="egg_action_type"
>


<input
    type="hidden"
    name="selected_egg_id"
    id="selected_egg_id"
    value=""
>


<div
    id="eggSelectedInfo"
    class="alert alert-success"
    style="display:none;"
></div>


<!-- BATCH ID -->

<div
    class="mb-3"
    id="eggBatchContainer"
>

<label class="form-label">

    Batch ID

</label>

<input
    type="text"
    name="batch_id"
    id="batch_id"
    class="form-control"
    readonly
>

</div>


<!-- HARVEST DATE -->

<div
    class="mb-3"
    id="eggDateContainer"
>

<label class="form-label">

    Harvest Date

</label>

<input
    type="date"
    name="harvest_date"
    class="form-control"
    value="<?php echo date('Y-m-d'); ?>"
    required
>

</div>


<!-- HARVEST TIME -->

<div
    class="mb-3"
    id="eggTimeContainer"
>

<label class="form-label">

    Harvest Time

</label>

<input
    type="time"
    name="harvest_time"
    class="form-control"
    value="<?php echo date('H:i'); ?>"
    required
>

</div>


<!-- EGG SIZE -->

<div
    class="mb-3"
    id="eggSizeContainer"
>

<label class="form-label">

    Egg Size

</label>

<select
    name="egg_size"
    id="eggSizeSelect"
    class="form-select"
    required
>

    <option value="XS">
        XS
    </option>

    <option value="Small">
        Small
    </option>

    <option value="Medium">
        Medium
    </option>

    <option value="Large">
        Large
    </option>

    <option value="XL">
        XL
    </option>

    <option value="Jumbo">
        Jumbo
    </option>

    <option value="Super Jumbo">
        Super Jumbo
    </option>

    <option value="Double Yolk">
        Double Yolk
    </option>

</select>

</div>


<!-- QUANTITY -->

<div class="mb-3">

<label
    class="form-label"
    id="eggQuantityLabel"
>

    Quantity

</label>

<input
    type="number"
    name="quantity"
    id="eggQuantityInput"
    class="form-control"
    required
>

<small
    id="eggQuantityHelp"
    class="text-muted"
    style="display:none;"
>

    Use +20 to add or -20 to subtract.

</small>

</div>


<!-- AUTOMATIC REASON -->

<div class="mb-3">

<label class="form-label">

    Reason

</label>

<input
    type="text"
    name="reason"
    id="eggReason"
    class="form-control"
    readonly
>

</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="btn btn-secondary"
    data-bs-dismiss="modal"
>

    Cancel

</button>


<button
    type="submit"
    id="eggSubmitBtn"
    class="btn btn-primary"
>

    Save

</button>

</div>

</form>

</div>

</div>


<!-- =========================================================
     SUPPLY MODAL
========================================================= -->

<div
    class="modal fade"
    id="supplyModal"
    tabindex="-1"
>

<div class="modal-dialog">

<form
    method="POST"
    class="modal-content"
>

<div class="modal-header">

    <h5
        class="modal-title"
        id="supplyModalTitle"
    >

        Supply Inventory

    </h5>

    <button
        type="button"
        class="btn-close"
        data-bs-dismiss="modal"
    ></button>

</div>


<div class="modal-body">

<input
    type="hidden"
    name="action_supply"
    id="supply_action_type"
>


<input
    type="hidden"
    name="selected_supply_id"
    id="selected_supply_id"
    value=""
>


<div
    id="supplySelectedInfo"
    class="alert alert-success"
    style="display:none;"
></div>


<!-- BATCH ID -->

<div
    class="mb-3"
    id="supplyBatchContainer"
>

<label class="form-label">

    Batch ID

</label>

<input
    type="text"
    id="supply_batch_id"
    class="form-control"
    readonly
>

</div>


<!-- CATEGORY -->

<div
    class="mb-3"
    id="supplyCategoryContainer"
>

<label class="form-label">

    Item Category

</label>

<select
    name="category"
    id="supplyCategorySelect"
    class="form-select"
    required
>

    <option value="Feeds">
        Feeds
    </option>

    <option value="Trays">
        Trays
    </option>

    <option value="Medicine">
        Medicine
    </option>

</select>

</div>


<!-- ITEM NAME -->

<div
    class="mb-3"
    id="supplyItemContainer"
>

<label class="form-label">

    Item Name

</label>

<input
    type="text"
    name="item_name"
    id="supplyItemName"
    class="form-control"
    placeholder="Enter item name"
    required
>

</div>


<!-- QUANTITY -->

<div class="mb-3">

<label
    class="form-label"
    id="supplyQuantityLabel"
>

    Quantity

</label>

<input
    type="number"
    name="quantity"
    id="supplyQuantityInput"
    class="form-control"
    required
>

<small
    id="supplyQuantityHelp"
    class="text-muted"
    style="display:none;"
>

    Use +20 to add or -20 to subtract.

</small>

</div>


<!-- AUTOMATIC REASON -->

<div class="mb-3">

<label class="form-label">

    Reason

</label>

<input
    type="text"
    name="reason"
    id="supplyReason"
    class="form-control"
    readonly
>

</div>

</div>


<div class="modal-footer">

<button
    type="button"
    class="btn btn-secondary"
    data-bs-dismiss="modal"
>

    Cancel

</button>


<button
    type="submit"
    id="supplySubmitBtn"
    class="btn btn-primary"
>

    Save

</button>

</div>

</form>

</div>

</div>


<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
></script>


<script>

/* =========================================================
   SELECTED EGG RECORD
========================================================= */

var selectedEgg = null;


document.querySelectorAll(
    ".egg-row"
).forEach(
    function(row) {

        row.addEventListener(
            "click",
            function() {

                document.querySelectorAll(
                    ".egg-row"
                ).forEach(
                    function(item) {

                        item.classList.remove(
                            "selected"
                        );

                    }
                );


                this.classList.add(
                    "selected"
                );


                selectedEgg = {

                    id:
                        this.getAttribute(
                            "data-id"
                        ),

                    size:
                        this.getAttribute(
                            "data-size"
                        ),

                    stock:
                        this.getAttribute(
                            "data-stock"
                        )

                };

            }
        );

    }
);


/* =========================================================
   SELECTED SUPPLY RECORD
========================================================= */

var selectedSupply = null;


document.querySelectorAll(
    ".supply-row"
).forEach(
    function(row) {

        row.addEventListener(
            "click",
            function() {

                document.querySelectorAll(
                    ".supply-row"
                ).forEach(
                    function(item) {

                        item.classList.remove(
                            "selected"
                        );

                    }
                );


                this.classList.add(
                    "selected"
                );


                selectedSupply = {

                    id:
                        this.getAttribute(
                            "data-id"
                        ),

                    category:
                        this.getAttribute(
                            "data-category"
                        ),

                    item:
                        this.getAttribute(
                            "data-item"
                        ),

                    stock:
                        this.getAttribute(
                            "data-stock"
                        )

                };

            }
        );

    }
);


/* =========================================================
   EGG MODAL
========================================================= */

function setupEggModal(action) {

    document.getElementById(
        "egg_action_type"
    ).value = action;


    document.getElementById(
        "selected_egg_id"
    ).value = "";


    document.getElementById(
        "eggModalTitle"
    ).innerHTML =
        action == "Stock In"
        ? "Harvest Eggs"
        : action;


    document.getElementById(
        "eggSubmitBtn"
    ).innerHTML =
        action == "Stock In"
        ? "Harvest"
        : action;


    document.getElementById(
        "eggSelectedInfo"
    ).style.display = "none";


    document.getElementById(
        "eggBatchContainer"
    ).style.display = "";


    document.getElementById(
        "eggDateContainer"
    ).style.display = "";


    document.getElementById(
        "eggTimeContainer"
    ).style.display = "";


    document.getElementById(
        "eggSizeContainer"
    ).style.display = "";


    document.getElementById(
        "eggQuantityLabel"
    ).innerHTML = "Quantity";


    document.getElementById(
        "eggQuantityHelp"
    ).style.display = "none";


    document.getElementById(
        "eggQuantityInput"
    ).value = "";


    document.getElementById(
        "batch_id"
    ).value =
        "<?php echo $next_batch_id; ?>";


    if (action == "Stock In") {

        document.getElementById(
            "eggReason"
        ).value =
            "Harvest";

    } else {

        document.getElementById(
            "eggReason"
        ).value =
            "Stock Out";

    }

}


/* =========================================================
   OPEN EGG ADJUSTMENT
========================================================= */

function openEggAdjustment() {

    if (!selectedEgg) {

        alert(
            "Please select an egg record from the Detailed Egg Stock Ledger first."
        );

        return;

    }


    var eggModal =
        new bootstrap.Modal(
            document.getElementById(
                "eggModal"
            )
        );


    document.getElementById(
        "egg_action_type"
    ).value =
        "Stock Adjustment";


    document.getElementById(
        "selected_egg_id"
    ).value =
        selectedEgg.id;


    document.getElementById(
        "eggModalTitle"
    ).innerHTML =
        "Stock Adjustment";


    document.getElementById(
        "eggSubmitBtn"
    ).innerHTML =
        "Save Adjustment";


    document.getElementById(
        "eggReason"
    ).value =
        "Stock Adjustment";


    document.getElementById(
        "eggSelectedInfo"
    ).style.display =
        "";


    document.getElementById(
        "eggSelectedInfo"
    ).innerHTML =
        "<strong>Selected Egg Record:</strong> "
        +
        selectedEgg.size
        +
        "<br><strong>Current Stock:</strong> "
        +
        Number(
            selectedEgg.stock
        ).toLocaleString();


    document.getElementById(
        "eggBatchContainer"
    ).style.display =
        "none";


    document.getElementById(
        "eggDateContainer"
    ).style.display =
        "none";


    document.getElementById(
        "eggTimeContainer"
    ).style.display =
        "none";


    document.getElementById(
        "eggSizeContainer"
    ).style.display =
        "none";


    document.getElementById(
        "eggQuantityLabel"
    ).innerHTML =
        "Stock Adjustment";


    document.getElementById(
        "eggQuantityHelp"
    ).style.display =
        "";


    document.getElementById(
        "eggQuantityInput"
    ).value = "";


    eggModal.show();

}


/* =========================================================
   SUPPLY MODAL
========================================================= */

function setupSupplyModal(action) {

    document.getElementById(
        "supply_action_type"
    ).value = action;


    document.getElementById(
        "selected_supply_id"
    ).value = "";


    document.getElementById(
        "supplyModalTitle"
    ).innerHTML =
        action;


    document.getElementById(
        "supplySubmitBtn"
    ).innerHTML =
        action;


    document.getElementById(
        "supplySelectedInfo"
    ).style.display =
        "none";


    document.getElementById(
        "supplyBatchContainer"
    ).style.display =
        "";


    document.getElementById(
        "supplyCategoryContainer"
    ).style.display =
        "";


    document.getElementById(
        "supplyItemContainer"
    ).style.display =
        "";


    document.getElementById(
        "supplyQuantityLabel"
    ).innerHTML =
        "Quantity";


    document.getElementById(
        "supplyQuantityHelp"
    ).style.display =
        "none";


    document.getElementById(
        "supplyQuantityInput"
    ).value = "";


    if (action == "Stock In") {

        document.getElementById(
            "supplyReason"
        ).value =
            "Stock In";

    } else {

        document.getElementById(
            "supplyReason"
        ).value =
            "Stock Out";

    }


    updateSupplyBatchID();

}


/* =========================================================
   OPEN SUPPLY ADJUSTMENT
========================================================= */

function openSupplyAdjustment() {

    if (!selectedSupply) {

        alert(
            "Please select a supply record from the Unified Supply Stock Logs first."
        );

        return;

    }


    var supplyModal =
        new bootstrap.Modal(
            document.getElementById(
                "supplyModal"
            )
        );


    document.getElementById(
        "supply_action_type"
    ).value =
        "Stock Adjustment";


    document.getElementById(
        "selected_supply_id"
    ).value =
        selectedSupply.id;


    document.getElementById(
        "supplyModalTitle"
    ).innerHTML =
        "Stock Adjustment";


    document.getElementById(
        "supplySubmitBtn"
    ).innerHTML =
        "Save Adjustment";


    document.getElementById(
        "supplyReason"
    ).value =
        "Stock Adjustment";


    document.getElementById(
        "supplySelectedInfo"
    ).style.display =
        "";


    document.getElementById(
        "supplySelectedInfo"
    ).innerHTML =
        "<strong>Selected Supply:</strong> "
        +
        selectedSupply.item
        +
        "<br><strong>Category:</strong> "
        +
        selectedSupply.category
        +
        "<br><strong>Current Stock:</strong> "
        +
        Number(
            selectedSupply.stock
        ).toLocaleString();


    document.getElementById(
        "supplyBatchContainer"
    ).style.display =
        "none";


    document.getElementById(
        "supplyCategoryContainer"
    ).style.display =
        "none";


    document.getElementById(
        "supplyItemContainer"
    ).style.display =
        "none";


    document.getElementById(
        "supplyQuantityLabel"
    ).innerHTML =
        "Stock Adjustment";


    document.getElementById(
        "supplyQuantityHelp"
    ).style.display =
        "";


    document.getElementById(
        "supplyQuantityInput"
    ).value = "";


    supplyModal.show();

}


/* =========================================================
   SUPPLY BATCH ID
========================================================= */

function updateSupplyBatchID() {

    var category =
        document.getElementById(
            "supplyCategorySelect"
        ).value;


    var batch = "";


    if (category == "Feeds") {

        batch =
            "<?php echo $next_feed_batch; ?>";

    } else if (
        category == "Trays"
    ) {

        batch =
            "<?php echo $next_tray_batch; ?>";

    } else {

        batch =
            "<?php echo $next_medicine_batch; ?>";

    }


    document.getElementById(
        "supply_batch_id"
    ).value =
        batch;

}


var supplyCategorySelect =
    document.getElementById(
        "supplyCategorySelect"
    );


if (supplyCategorySelect) {

    supplyCategorySelect.addEventListener(
        "change",
        updateSupplyBatchID
    );

}


/* =========================================================
   SUPPLY LIVE SEARCH
========================================================= */

var supplySearch =
    document.getElementById(
        "supply_search"
    );


if (supplySearch) {

    supplySearch.addEventListener(
        "input",
        function() {

            var keyword =
                this.value
                .toLowerCase()
                .trim();


            var rows =
                document.querySelectorAll(
                    "#supplyTable tbody tr"
                );


            rows.forEach(
                function(row) {

                    var text =
                        row.textContent
                        .toLowerCase();


                    row.style.display =
                        text.includes(
                            keyword
                        )
                        ? ""
                        : "none";

                }
            );

        }
    );

}


/* =========================================================
   INVENTORY HISTORY FILTER
========================================================= */

var historySearch =
    document.getElementById(
        "history_search"
    );


var reportDateFilter =
    document.getElementById(
        "report_date_filter"
    );


var clearReportDate =
    document.getElementById(
        "clearReportDate"
    );


var visibleRecordCount =
    document.getElementById(
        "visibleRecordCount"
    );


var reportSummaryTitle =
    document.getElementById(
        "reportSummaryTitle"
    );


var reportSummaryDescription =
    document.getElementById(
        "reportSummaryDescription"
    );


var dailyHarvestWrapper =
    document.getElementById(
        "dailyHarvestWrapper"
    );

function filterHistoryTable() {

    var keyword = "";

    var selectedDate = "";


    if (historySearch) {

        keyword =
            historySearch.value
            .toLowerCase()
            .trim();

    }


    if (reportDateFilter) {

        selectedDate =
            reportDateFilter.value;

    }


    var rows =
        document.querySelectorAll(
            "#historyTable tbody tr"
        );


    var visibleCount = 0;

    var eggStockTotal = 0;

    var supplyStockTotal = 0;


    rows.forEach(
        function(row) {

            /*
               Ignore empty-state row
            */

            if (
                row.querySelectorAll(
                    "td"
                ).length == 1
            ) {

                return;

            }


            var rowText =
                row.textContent
                .toLowerCase();


            var rowDate =
                row.getAttribute(
                    "data-report-date"
                );


            var rowCategory =
                row.getAttribute(
                    "data-category"
                );


            var rowAction =
                row.getAttribute(
                    "data-action"
                );


            var rowQuantity =
                parseInt(
                    row.getAttribute(
                        "data-quantity"
                    )
                );


            if (
                isNaN(
                    rowQuantity
                )
            ) {

                rowQuantity = 0;

            }


            var matchesSearch =
                rowText.includes(
                    keyword
                );


            var matchesDate = true;


            if (selectedDate != "") {

                matchesDate =
                    rowDate == selectedDate;

            }


            /*
               Show matching record
            */

            if (
                matchesSearch
                &&
                matchesDate
            ) {

                row.style.display = "";

                visibleCount++;


                /*
                   ONLY STOCK IN TRANSACTIONS
                   ARE INCLUDED IN THE STOCK TOTALS
                */

                if (
                    rowAction
                    ==
                    "Stock In"
                ) {

                    /*
                       EGG STOCK
                    */

                    if (
                        rowCategory
                        ==
                        "Eggs"
                    ) {

                        eggStockTotal +=
                            rowQuantity;

                    }


                    /*
                       SUPPLY STOCK

                       Includes:
                       - Feeds
                       - Trays
                       - Medicine
                    */

                    if (
                        rowCategory
                        ==
                        "Feeds"
                        ||
                        rowCategory
                        ==
                        "Trays"
                        ||
                        rowCategory
                        ==
                        "Medicine"
                    ) {

                        supplyStockTotal +=
                            rowQuantity;

                    }

                }

            } else {

                row.style.display = "none";

            }

        }
    );


    /*
       UPDATE TOTAL RECORD COUNT
    */

    if (visibleRecordCount) {

        visibleRecordCount.innerHTML =
            visibleCount.toLocaleString();

    }


    /*
       UPDATE EGG STOCK

       Stock In only
    */

    var visibleEggStock =
        document.getElementById(
            "visibleEggStock"
        );


    if (visibleEggStock) {

        visibleEggStock.innerHTML =
            eggStockTotal.toLocaleString();

    }


    /*
       UPDATE SUPPLY STOCK

       Stock In only
    */

    var visibleSupplyStock =
        document.getElementById(
            "visibleSupplyStock"
        );


    if (visibleSupplyStock) {

        visibleSupplyStock.innerHTML =
            supplyStockTotal.toLocaleString();

    }


    /*
       UPDATE SUMMARY TEXT
    */

    if (selectedDate != "") {

        var dateObject =
            new Date(
                selectedDate
                +
                "T00:00:00"
            );


        var formattedDate =
            dateObject.toLocaleDateString(
                "en-US",
                {
                    month: "short",
                    day: "2-digit",
                    year: "numeric"
                }
            );


        if (reportSummaryTitle) {

            reportSummaryTitle.innerHTML =
                "Selected Date Summary - "
                +
                formattedDate;

        }


        if (reportSummaryDescription) {

            reportSummaryDescription.innerHTML =
                "Showing "
                +
                visibleCount
                +
                " inventory record(s). Egg and supply totals include Stock In transactions only.";

        }

    } else {

        if (reportSummaryTitle) {

            <?php if ($filter_category == "All") { ?>

                reportSummaryTitle.innerHTML =
                    "All Inventory Records";

            <?php } else { ?>

                reportSummaryTitle.innerHTML =
                    "<?php echo $filter_category; ?> Inventory Records";

            <?php } ?>

        }


        if (reportSummaryDescription) {

            reportSummaryDescription.innerHTML =
                "Showing all available inventory records. Egg and supply totals include Stock In transactions only.";

        }

    }


    /* =============================================
       DAILY HARVEST FILTER
    ============================================= */

    var harvestRows =
        document.querySelectorAll(
            ".daily-harvest-row"
        );


    var visibleHarvestCount = 0;


    harvestRows.forEach(
        function(row) {

            var harvestDate =
                row.getAttribute(
                    "data-harvest-date"
                );


            /*
               Daily harvest appears only when:

               1. A date is selected
               2. Eggs category is selected
               3. Harvest belongs to the selected date
            */

            var showHarvest =
                selectedDate != ""
                &&
                "<?php echo $filter_category; ?>"
                ==
                "Eggs"
                &&
                harvestDate
                ==
                selectedDate;


            if (showHarvest) {

                row.style.display = "";

                visibleHarvestCount++;

            } else {

                row.style.display = "none";

            }

        }
    );


    if (dailyHarvestWrapper) {

        if (

            selectedDate != ""

            &&

            "<?php echo $filter_category; ?>"
            ==
            "Eggs"

            &&

            visibleHarvestCount > 0

        ) {

            dailyHarvestWrapper.classList.add(
                "show"
            );

        } else {

            dailyHarvestWrapper.classList.remove(
                "show"
            );

        }

    }

}


/* HISTORY LIVE SEARCH */

if (historySearch) {

    historySearch.addEventListener(
        "input",
        filterHistoryTable
    );

}


/* DATE FILTER */

if (reportDateFilter) {

    reportDateFilter.addEventListener(
        "change",
        filterHistoryTable
    );

}


/* CLEAR DATE */

if (clearReportDate) {

    clearReportDate.addEventListener(
        "click",
        function() {

            reportDateFilter.value = "";

            filterHistoryTable();

        }
    );

}


/* INITIAL LOAD */

filterHistoryTable();


/* =========================================================
   EGG LIVE SEARCH
========================================================= */

var eggSearch =
    document.getElementById(
        "egg_search"
    );


var eggSearchBtn =
    document.getElementById(
        "eggSearchBtn"
    );


function filterEggTable() {

    var keyword =
        eggSearch.value
        .toLowerCase()
        .trim();


    var rows =
        document.querySelectorAll(
            "#eggTable tbody tr"
        );


    rows.forEach(
        function(row) {

            var text =
                row.textContent
                .toLowerCase();


            row.style.display =
                text.includes(
                    keyword
                )
                ? ""
                : "none";

        }
    );

}


if (eggSearch) {

    eggSearch.addEventListener(
        "input",
        filterEggTable
    );

}


if (eggSearchBtn) {

    eggSearchBtn.addEventListener(
        "click",
        filterEggTable
    );

}


/* =========================================================
   CLOCK
========================================================= */

function updateInventoryClock() {

    var now =
        new Date();


    var time =
        now.toLocaleTimeString(
            'en-US',
            {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }
        );


    var clock =
        document.getElementById(
            'inventoryClock'
        );


    if (clock) {

        clock.innerHTML =
            time;

    }

}


updateInventoryClock();


setInterval(
    updateInventoryClock,
    1000
);


/* =========================================================
   NOTIFICATION BELL
========================================================= */

var notificationBell =
    document.getElementById(
        "notificationBell"
    );


var notificationDropdown =
    document.getElementById(
        "notificationDropdown"
    );


if (
    notificationBell &&
    notificationDropdown
) {

    notificationBell.addEventListener(
        "click",
        function(event) {

            event.stopPropagation();

            notificationDropdown.classList.toggle(
                "show"
            );

        }
    );


    document.addEventListener(
        "click",
        function(event) {

            if (
                !notificationDropdown.contains(
                    event.target
                )
                &&
                !notificationBell.contains(
                    event.target
                )
            ) {

                notificationDropdown.classList.remove(
                    "show"
                );

            }

        }
    );

}

</script>

</body>

</html>