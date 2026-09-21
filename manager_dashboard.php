<?php

ob_start();

// Include standardized session configuration FIRST
require_once 'session_config.php';

require_once('db.php');


/* =========================================================
   SECURITY
========================================================= */

if (!isset($_SESSION['user_id'])) {

    header("Location: login.php");
    exit();

}


if (
    isset($_SESSION['role']) &&
    $_SESSION['role'] != 'manager'
) {

    header("Location: login.php");
    exit();

}


/* =========================================================
   MANAGER INFORMATION
========================================================= */

$manager_name = 'Manager';


if (
    isset($_SESSION['name']) &&
    $_SESSION['name'] != ''
) {

    $manager_name = $_SESSION['name'];

} elseif (
    isset($_SESSION['username']) &&
    $_SESSION['username'] != ''
) {

    $manager_name = $_SESSION['username'];

}


/* =========================================================
   HELPER FUNCTIONS
========================================================= */

function table_exists($conn, $table)
{
    $table = mysqli_real_escape_string(
        $conn,
        $table
    );

    $query = "SHOW TABLES LIKE '$table'";

    $result = mysqli_query(
        $conn,
        $query
    );


    if (
        $result &&
        mysqli_num_rows($result) > 0
    ) {

        return true;

    }


    return false;
}



function column_exists(
    $conn,
    $table,
    $column
)
{

    $table = mysqli_real_escape_string(
        $conn,
        $table
    );

    $column = mysqli_real_escape_string(
        $conn,
        $column
    );


    $query = "
        SHOW COLUMNS
        FROM `$table`
        LIKE '$column'
    ";


    $result = mysqli_query(
        $conn,
        $query
    );


    if (
        $result &&
        mysqli_num_rows($result) > 0
    ) {

        return true;

    }


    return false;
}



function get_total_value(
    $conn,
    $query
)
{

    $result = mysqli_query(
        $conn,
        $query
    );


    if ($result) {

        $row = mysqli_fetch_assoc(
            $result
        );


        if (isset($row['total'])) {

            return $row['total'];

        }

    }


    return 0;

}



/* =========================================================
   GET SUPPLY ICON
========================================================= */

function get_supply_icon(
    $category,
    $item_name
)
{

    $category = strtolower(
        trim($category)
    );


    $item_name = strtolower(
        trim($item_name)
    );


    /* FEEDS */

    if (
        strpos($category, 'feed') !== false ||
        strpos($item_name, 'feed') !== false
    ) {

        return 'fa-solid fa-sack-dollar';

    }


    /* BEDDING */

    if (
        strpos($category, 'bedding') !== false ||
        strpos($item_name, 'bedding') !== false
    ) {

        return 'fa-solid fa-layer-group';

    }


    /* TRAYS */

    if (
        strpos($category, 'tray') !== false ||
        strpos($item_name, 'tray') !== false
    ) {

        return 'fa-solid fa-box';

    }


    /* CARTONS */

    if (
        strpos($category, 'carton') !== false ||
        strpos($category, 'packaging') !== false
    ) {

        return 'fa-solid fa-box-open';

    }


    /* MEDICINE */

    if (
        strpos($category, 'medicine') !== false ||
        strpos($category, 'medicines') !== false ||
        strpos($item_name, 'medicine') !== false
    ) {

        return
            'fa-solid fa-prescription-bottle-medical';

    }


    /* VITAMINS */

    if (
        strpos($category, 'vitamin') !== false ||
        strpos($item_name, 'vitamin') !== false
    ) {

        return 'fa-solid fa-flask';

    }


    /* DEFAULT */

    return 'fa-solid fa-cube';

}



/* =========================================================
   GET SUPPLY ICON CLASS
========================================================= */

function get_supply_icon_type($category)
{

    $category = strtolower(
        trim($category)
    );


    if (
        strpos($category, 'feed') !== false
    ) {

        return 'feed';

    }


    if (
        strpos($category, 'bedding') !== false
    ) {

        return 'bedding';

    }


    if (
        strpos($category, 'tray') !== false ||
        strpos($category, 'carton') !== false
    ) {

        return 'tray';

    }


    if (
        strpos($category, 'medicine') !== false ||
        strpos($category, 'vitamin') !== false
    ) {

        return 'medicine';

    }


    return 'default';

}



/* =========================================================
   FORMAT EGG SIZE
========================================================= */

function normalize_egg_size($size)
{

    $size = strtolower(
        trim($size)
    );


    /* EXTRA SMALL */

    if (
        $size == 'xs' ||
        $size == 'extra small' ||
        $size == 'extra small (xs)'
    ) {

        return 'Extra Small (XS)';

    }


    /* SMALL */

    if (
        $size == 's' ||
        $size == 'small' ||
        $size == 'small (s)'
    ) {

        return 'Small (S)';

    }


    /* MEDIUM */

    if (
        $size == 'm' ||
        $size == 'medium' ||
        $size == 'medium (m)'
    ) {

        return 'Medium (M)';

    }


    /* LARGE */

    if (
        $size == 'l' ||
        $size == 'large' ||
        $size == 'large (l)'
    ) {

        return 'Large (L)';

    }


    /* EXTRA LARGE */

    if (
        $size == 'xl' ||
        $size == 'extra large' ||
        $size == 'extra large (xl)'
    ) {

        return 'Extra Large (XL)';

    }


    /* JUMBO */

    if (
        $size == 'j' ||
        $size == 'jumbo' ||
        $size == 'jumbo (j)'
    ) {

        return 'Jumbo (J)';

    }


    /* SUPER JUMBO */

    if (
        $size == 'sj' ||
        $size == 'super jumbo' ||
        $size == 'super jumbo (sj)'
    ) {

        return 'Super Jumbo (SJ)';

    }


    /* DOUBLE YOLK */

    if (
        $size == 'dy' ||
        $size == 'double yolk' ||
        $size == 'double yolk (dy)'
    ) {

        return 'Double Yolk (DY)';

    }


    return $size;

}



/* =========================================================
   DEFAULT DASHBOARD VALUES
========================================================= */

$total_inventory_items = 0;

$total_egg_stock = 0;

$total_supply_stock = 0;

$pending_reservations = 0;

$today_deliveries = 0;

$completed_deliveries = 0;

$reports_generated = 0;


$egg_data = array();

$supply_data = array();

$recent_activities = array();


/* =========================================================
   FIXED EGG SIZE ORDER
========================================================= */

$egg_sizes = array(

    'Extra Small (XS)',
    'Small (S)',
    'Medium (M)',
    'Large (L)',
    'Extra Large (XL)',
    'Jumbo (J)',
    'Super Jumbo (SJ)',
    'Double Yolk (DY)'

);



/* =========================================================
   EGG INVENTORY
========================================================= */

if (
    table_exists(
        $conn,
        'egg_inventory'
    )
) {


    /* =========================================
       COUNT INVENTORY RECORDS
    ========================================= */

    $total_inventory_items +=
        get_total_value(

            $conn,

            "
            SELECT COUNT(*) AS total
            FROM egg_inventory
            "

        );


    /* =========================================
       DETECT STOCK COLUMN
    ========================================= */

    $stock_column = '';


    if (
        column_exists(
            $conn,
            'egg_inventory',
            'available_stock'
        )
    ) {

        $stock_column =
            'available_stock';

    } elseif (
        column_exists(
            $conn,
            'egg_inventory',
            'quantity'
        )
    ) {

        $stock_column =
            'quantity';

    } elseif (
        column_exists(
            $conn,
            'egg_inventory',
            'current_stock'
        )
    ) {

        $stock_column =
            'current_stock';

    }


    /* =========================================
       DETECT EGG SIZE COLUMN
    ========================================= */

    $size_column = '';


    if (
        column_exists(
            $conn,
            'egg_inventory',
            'egg_size'
        )
    ) {

        $size_column =
            'egg_size';

    } elseif (
        column_exists(
            $conn,
            'egg_inventory',
            'category'
        )
    ) {

        $size_column =
            'category';

    } elseif (
        column_exists(
            $conn,
            'egg_inventory',
            'size'
        )
    ) {

        $size_column =
            'size';

    }


    /* =========================================
       GET TOTAL EGG STOCK
    ========================================= */

    if ($stock_column != '') {

        $total_egg_stock =
            get_total_value(

                $conn,

                "
                SELECT
                    COALESCE(
                        SUM($stock_column),
                        0
                    ) AS total

                FROM egg_inventory
                "

            );

    }


    /* =========================================
       GET EGG STOCKS
    ========================================= */

    if (
        $stock_column != '' &&
        $size_column != ''
    ) {


        $egg_query = "

            SELECT

                $size_column
                    AS egg_size,

                COALESCE(
                    SUM($stock_column),
                    0
                ) AS available_stock

            FROM egg_inventory

            GROUP BY $size_column

        ";


        $egg_result = mysqli_query(
            $conn,
            $egg_query
        );


        $database_eggs = array();


        if ($egg_result) {

            while (
                $row =
                mysqli_fetch_assoc(
                    $egg_result
                )
            ) {

                $normalized_size =
                    normalize_egg_size(
                        $row['egg_size']
                    );


                $database_eggs[
                    $normalized_size
                ] =
                    $row['available_stock'];

            }

        }


        /* =====================================
           BUILD COMPLETE EGG LIST
        ===================================== */

        foreach (
            $egg_sizes
            as $size
        ) {


            $stock = 0;


            if (
                isset(
                    $database_eggs[$size]
                )
            ) {

                $stock =
                    $database_eggs[$size];

            }


            $egg_data[] = array(

                'egg_size' =>
                    $size,

                'available_stock' =>
                    $stock

            );

        }

    }


}



/* =========================================================
   SUPPLY INVENTORY
========================================================= */

if (
    table_exists(
        $conn,
        'supply_inventory'
    )
) {


    /* =========================================
       COUNT SUPPLY RECORDS
    ========================================= */

    $total_inventory_items +=
        get_total_value(

            $conn,

            "
            SELECT COUNT(*) AS total
            FROM supply_inventory
            "

        );


    /* =========================================
       DETECT STOCK COLUMN
    ========================================= */

    $supply_stock_column = '';


    if (
        column_exists(
            $conn,
            'supply_inventory',
            'available_stock'
        )
    ) {

        $supply_stock_column =
            'available_stock';

    } elseif (
        column_exists(
            $conn,
            'supply_inventory',
            'quantity'
        )
    ) {

        $supply_stock_column =
            'quantity';

    } elseif (
        column_exists(
            $conn,
            'supply_inventory',
            'current_stock'
        )
    ) {

        $supply_stock_column =
            'current_stock';

    }


    /* =========================================
       GET TOTAL SUPPLY STOCK
    ========================================= */

    if (
        $supply_stock_column != ''
    ) {

        $total_supply_stock =
            get_total_value(

                $conn,

                "
                SELECT

                    COALESCE(
                        SUM($supply_stock_column),
                        0
                    ) AS total

                FROM supply_inventory
                "

            );

    }


    /* =========================================
       GET SUPPLIES
    ========================================= */

    $supply_query = "

        SELECT *

        FROM supply_inventory

        ORDER BY id DESC

        LIMIT 50

    ";


    $supply_result =
        mysqli_query(
            $conn,
            $supply_query
        );


    if ($supply_result) {

        while (
            $row =
            mysqli_fetch_assoc(
                $supply_result
            )
        ) {

            $supply_data[] = $row;

        }

    }


}



/* =========================================================
   RESERVATIONS
========================================================= */

if (
    table_exists(
        $conn,
        'reservations'
    )
) {


    /* =========================================
       PENDING RESERVATIONS
    ========================================= */

    if (
        column_exists(
            $conn,
            'reservations',
            'status'
        )
    ) {

        $pending_reservations =
            get_total_value(

                $conn,

                "

                SELECT

                    COUNT(
                        DISTINCT reservation_code
                    ) AS total

                FROM reservations

                WHERE status = 'Pending'

                "

            );


        /* =====================================
           COMPLETED DELIVERIES
        ===================================== */

        $completed_deliveries =
            get_total_value(

                $conn,

                "

                SELECT

                    COUNT(
                        DISTINCT reservation_code
                    ) AS total

                FROM reservations

                WHERE status IN (

                    'Delivered',
                    'Completed'

                )

                "

            );

    }


    /* =========================================
       TODAY'S DELIVERIES
    ========================================= */

    if (
        column_exists(
            $conn,
            'reservations',
            'delivery_date'
        )
    ) {

        $today_deliveries =
            get_total_value(

                $conn,

                "

                SELECT

                    COUNT(
                        DISTINCT reservation_code
                    ) AS total

                FROM reservations

                WHERE

                    DATE(delivery_date)
                    = CURDATE()

                    AND delivery_method
                    = 'Delivery'

                "

            );

    }


    /* =========================================
       RECENT RESERVATIONS
    ========================================= */

    if (
        column_exists(
            $conn,
            'reservations',
            'reservation_code'
        )
    ) {


        $recent_query = "

            SELECT

                reservation_code,

                MAX(customer_name)
                    AS customer_name,

                MAX(reserved_at)
                    AS activity_date

            FROM reservations

            GROUP BY
                reservation_code

            ORDER BY
                MAX(reserved_at) DESC

            LIMIT 5

        ";


        $recent_result =
            mysqli_query(
                $conn,
                $recent_query
            );


        if ($recent_result) {

            while (
                $row =
                mysqli_fetch_assoc(
                    $recent_result
                )
            ) {


                $description =
                    $row[
                        'reservation_code'
                    ];


                if (
                    isset(
                        $row[
                            'customer_name'
                        ]
                    ) &&

                    $row[
                        'customer_name'
                    ] != ''
                ) {

                    $description .=
                        ' by ' .

                        $row[
                            'customer_name'
                        ];

                }


                $recent_activities[] =
                    array(

                        'type' =>
                            'reservation',

                        'title' =>
                            'New Reservation Received',

                        'description' =>
                            $description,

                        'date' =>
                            $row[
                                'activity_date'
                            ]

                    );

            }

        }

    }


}



/* =========================================================
   ADD RECENT SUPPLY ACTIVITIES
========================================================= */

if (
    table_exists(
        $conn,
        'supply_inventory'
    )
) {


    $recent_supply_query = "

        SELECT *

        FROM supply_inventory

        ORDER BY id DESC

        LIMIT 5

    ";


    $recent_supply_result =
        mysqli_query(
            $conn,
            $recent_supply_query
        );


    if (
        $recent_supply_result
    ) {

        while (
            $row =
            mysqli_fetch_assoc(
                $recent_supply_result
            )
        ) {


            $item_name = 'Supply';


            if (
                isset(
                    $row['item_name']
                ) &&

                $row['item_name'] != ''
            ) {

                $item_name =
                    $row['item_name'];

            } elseif (
                isset(
                    $row['item']
                ) &&

                $row['item'] != ''
            ) {

                $item_name =
                    $row['item'];

            }


            $activity_date = '';


            if (
                isset(
                    $row['created_at']
                )
            ) {

                $activity_date =
                    $row['created_at'];

            } elseif (
                isset(
                    $row['date_added']
                )
            ) {

                $activity_date =
                    $row['date_added'];

            }


            $recent_activities[] =
                array(

                    'type' =>
                        'inventory',

                    'title' =>
                        'Supply Added to Inventory',

                    'description' =>
                        $item_name,

                    'date' =>
                        $activity_date

                );

        }

    }


}



/* =========================================================
   SORT RECENT ACTIVITIES
========================================================= */

if (
    count($recent_activities) > 0
) {

    usort(
        $recent_activities,

        function(
            $a,
            $b
        )
        {

            $date_a =
                isset($a['date'])
                ? strtotime($a['date'])
                : 0;


            $date_b =
                isset($b['date'])
                ? strtotime($b['date'])
                : 0;


            return
                $date_b - $date_a;

        }

    );


    $recent_activities =
        array_slice(
            $recent_activities,
            0,
            5
        );

}



/* =========================================================
   REPORTS
========================================================= */

if (
    table_exists(
        $conn,
        'reports'
    )
) {

    $reports_generated =
        get_total_value(

            $conn,

            "
            SELECT COUNT(*) AS total
            FROM reports
            "

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


<title>
    Manager Dashboard | VDVC Egg Farm
</title>


<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
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
   DASHBOARD OUTER CARD
========================================================= */

.dashboard-outer-card {

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

}
/* =========================================================
   DASHBOARD HEADER - SAME AS INVENTORY HEADER
========================================================= */

.dashboard-topbar {

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

.dashboard-page-title {

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

.dashboard-topbar-right {

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

.top-notification {

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


.top-notification:hover {

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

.topbar-divider {

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

.manager-top-profile {

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

.manager-account-details {

    display:
        flex;

    flex-direction:
        column;

    justify-content:
        center;

    min-width:
        100px;

}


/* MANAGER NAME */

.manager-top-name {

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


/* DATE */

.manager-role {

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


/* TIME */

.manager-time {

    display:
        block;

    margin-top:
        3px;

    color:
        #527d59;

    font-size:
        0.63rem;

    font-weight:
        600;

    line-height:
        1.2;

    white-space:
        nowrap;

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

}
/* =========================================================
   DASHBOARD CONTENT
========================================================= */

.dashboard-content {

    width: 100%;

    padding:
        22px 28px 28px;

}


/* =========================================================
   HEADER
========================================================= */

.dashboard-header {

    margin-bottom: 22px;

}

/* =========================================================
   DASHBOARD GREETING - UPDATED STYLE
========================================================= */

.dashboard-header {
    margin-bottom: 24px;
}


/* Greeting container */

.greeting-wrapper {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}


/* Leaf icon */

.greeting-icon {
    color: #315f4a;
    font-size: 22px;
    margin-top: 4px;
}


/* Text container */

.greeting-text-container {
    display: flex;
    flex-direction: column;
}


/* Main greeting */

.greeting {

    margin: 0;

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    font-size: 25px;

    font-weight: 700;

    font-style: normal;

    color: #315f4a;

    letter-spacing: 0.5px;

    line-height: 1.1;

}


/* Subtitle */

.dashboard-subtitle {

    margin: 5px 0 0;

    font-family:
        Arial,
        Helvetica,
        sans-serif;

    font-size: 13px;

    font-weight: 400;

    color: #77817b;

    letter-spacing: 0.2px;

}


/* =========================================================
   STATISTICS GRID
========================================================= */

.statistics-grid {

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    gap: 14px;

    margin-bottom: 14px;

}


/* =========================================================
   STAT CARD
========================================================= */

.stat-card {

    min-height: 98px;

    padding: 15px;

    border-radius: 10px;

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

    transition:
        0.2s ease;

}


.stat-card:hover {

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


.stat-card-top {

    display: flex;

    align-items: flex-start;

    gap: 12px;

}


.stat-icon {

    width: 42px;

    height: 42px;

    min-width: 42px;

    border-radius: 50%;

    display: flex;

    align-items: center;

    justify-content: center;

    font-size: 18px;

}


.stat-icon.reservation {

    background: #e9f1e6;

    color: #527d59;

}


.stat-icon.inventory {

    background: #fff2df;

    color: #a87932;

}


.stat-icon.delivery {

    background: #eee9f7;

    color: #70558c;

}


.stat-icon.completed {

    background: #e6f0f4;

    color: #3e7182;

}


.stat-info {

    min-width: 0;

}


.stat-title {

    font-size: 13px;

    font-weight: 700;

    color: #59635c;

}


.stat-number {

    margin-top: 6px;

    font-size: 24px;

    font-weight: 700;

    color: #38433d;

}


.stat-description {

    margin-top: 6px;

    font-size: 11px;

    color: #8b9089;

}


/* =========================================================
   MAIN DASHBOARD GRID
========================================================= */

.dashboard-main-grid {

    display: grid;

    grid-template-columns:
        1.35fr 1fr;

    gap: 14px;

}


/* =========================================================
   RIGHT SIDE GRID
========================================================= */

.dashboard-right-column {

    display: grid;

    grid-template-rows:
        auto auto;

    gap: 14px;

}


/* =========================================================
   DASHBOARD PANEL
========================================================= */

.dashboard-panel {

    min-width: 0;

    overflow: hidden;

    border-radius: 10px;

    border:
        1px solid #e2dfd7;

    background: #ffffff;

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
   PANEL HEADER
========================================================= */

.panel-header {

    min-height: 50px;

    padding:
        0 16px;

    display: flex;

    align-items: center;

    justify-content:
        space-between;

    border-bottom:
        1px solid #e9e6df;

}


.panel-title-group {

    display: flex;

    align-items: center;

    gap: 10px;

}


.panel-title-icon {

    width: 30px;

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #eef3ed;

    color: #527d59;

    font-size: 14px;

}


.dashboard-panel-title {

    font-size: 14px;

    font-weight: 700;

    color: #3f4b45;

}


.view-all {

    font-size: 11px;

    font-weight: 700;

    color: #527d59;

    text-decoration: none;

}


.view-all:hover {

    text-decoration: underline;

}


/* =========================================================
   TABLE
========================================================= */

.table-wrapper {

    width: 100%;

    overflow-x: auto;

    padding:
        10px 12px;

}


.dashboard-table {

    width: 100%;

    border-collapse: collapse;

    font-size: 11px;

}


.dashboard-table thead {

    background: #edf0eb;

}


.dashboard-table thead th {

    padding:
        9px 8px;

    color: #627068;

    font-size: 11px;

    font-weight: 700;

    text-align: center;
white-space: nowrap;
}


.dashboard-table thead th:first-child {

    text-align: left;

}


.dashboard-table tbody td {

    padding:
        12px 10px;

    color: #59635c;

    border-bottom:
        1px solid #eceae4;

    text-align: center;

    font-size: 13px;

}


.dashboard-table tbody td:first-child {

    text-align: left;

}


.dashboard-table tbody tr:last-child td {

    border-bottom: none;

}


/* =========================================================
   EGG TABLE CELL
========================================================= */

.egg-table-cell {

    display: flex;

    align-items: center;

    gap: 9px;

}


.egg-small-icon {

    width: 28px;

    height: 28px;

    min-width: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    background: #fff5e5;

    color: #b98237;

    font-size: 13px;

}


.stock-number {

    font-weight: 700;

    color: #46554d;

}


/* =========================================================
   STOCK STATUS
========================================================= */

.stock-status {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    padding:
        5px 10px;

    border-radius: 20px;

    background: #e9f1e6;

    color: #527d59;

    font-size: 11px;

    font-weight: 700;

}


/* =========================================================
   SUPPLY LIST
========================================================= */

.supply-list {

    padding:
        7px 14px 10px;

}


.supply-item {

    min-height: 46px;

    display: flex;

    align-items: center;

    gap: 10px;

    border-bottom:
        1px solid #eceae4;

}


.supply-item:last-child {

    border-bottom: none;

}


.supply-small-icon {

    width: 28px;

    height: 28px;

    min-width: 28px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 7px;

    font-size: 12px;

}


.supply-small-icon.feed {

    background: #edf3e8;

    color: #547d52;

}


.supply-small-icon.bedding {

    background: #f1eee5;

    color: #847554;

}


.supply-small-icon.tray {

    background: #eef1eb;

    color: #62795f;

}


.supply-small-icon.medicine {

    background: #fff1e6;

    color: #b87639;

}


.supply-small-icon.default {

    background: #edf1f2;

    color: #54707b;

}


.supply-info {

    flex: 1;

    min-width: 0;

}


.supply-name {

    font-size: 12px;

    font-weight: 700;

    color: #556159;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.supply-category {

    margin-top: 3px;

    font-size: 10px;

    color: #92978f;

}


.supply-stock {

    font-size: 12px;

    font-weight: 700;

    color: #46534b;

}


.supply-status {

    margin-left: 8px;

    padding:
        4px 9px;

    border-radius: 20px;

    background: #e9f1e6;

    color: #527d59;

    font-size: 10px;

    font-weight: 700;

}


/* =========================================================
   RECENT ACTIVITIES
========================================================= */

.activity-list {

    padding:
        5px 14px 10px;

}


.activity-item {

    min-height: 48px;

    display: flex;

    align-items: center;

    gap: 10px;

    border-bottom:
        1px solid #eceae4;

}


.activity-item:last-child {

    border-bottom: none;

}


.activity-icon {

    width: 29px;

    height: 29px;

    min-width: 29px;

    display: flex;

    align-items: center;

    justify-content: center;

    border-radius: 50%;

    font-size: 12px;

}


.activity-icon.reservation {

    background: #eee9f7;

    color: #70558c;

}


.activity-icon.inventory {

    background: #edf3e8;

    color: #527d59;

}


.activity-icon.egg {

    background: #fff4e4;

    color: #b67b30;

}


.activity-content {

    flex: 1;

    min-width: 0;

}


.activity-title {

    font-size: 12px;

    font-weight: 700;

    color: #4d5851;

}


.activity-description {

    margin-top: 4px;

    font-size: 10px;

    color: #8a9089;

    white-space: nowrap;

    overflow: hidden;

    text-overflow: ellipsis;

}


.activity-time {

    font-size: 10px;

    color: #969a94;

    white-space: nowrap;

}


/* =========================================================
   EMPTY STATE
========================================================= */

.empty-state {

    padding:
        35px 15px;

    text-align: center;

    color: #92968f;

    font-size: 9px;

}


/* =========================================================
   RESPONSIVE
========================================================= */

@media screen and (max-width: 1300px) {

    .statistics-grid {

        grid-template-columns:
            repeat(2, 1fr);

    }


    .dashboard-main-grid {

        grid-template-columns:
            1fr;

    }

}


@media screen and (max-width: 1100px) {

    .main-content {

        margin-left: 270px;

        width:
            calc(100% - 270px);

    }

}


@media screen and (max-width: 900px) {

    .main-content {

        margin-left: 0;

        width: 100%;

        padding: 15px;

    }


    .dashboard-outer-card {

        border-radius: 18px;

    }


    .manager-account-details,
    .manager-dropdown-icon {

        display: none;

    }

}


@media screen and (max-width: 600px) {

    .dashboard-content {

        padding: 18px;

    }


    .statistics-grid {

        grid-template-columns:
            1fr;

    }


    .greeting {

        font-size: 22px;

    }


    .dashboard-topbar {

        padding:
            0 18px;

    }

}


</style>


</head>


<body>


<!-- =========================================================
     MANAGER PANEL
========================================================= -->

<?php include('manager_panel.php'); ?>


<!-- =========================================================
     MAIN CONTENT
========================================================= -->

<div class="main-content">


    <div class="dashboard-outer-card">
<!-- =============================================
     TOP NAVIGATION
============================================= -->

<div class="dashboard-topbar">


    <!-- PAGE TITLE -->

    <div class="dashboard-page-title">

        Dashboard

    </div>



    <!-- TOPBAR RIGHT -->

    <div class="dashboard-topbar-right">


        <!-- NOTIFICATION -->

        <a
            href="manager_reservation.php"
            class="top-notification"
            title="Pending Reservations"
        >

            <i class="fa-regular fa-bell"></i>


            <?php if ($pending_reservations > 0) { ?>

                <span class="notification-badge">

                    <?php

                    if (
                        $pending_reservations > 9
                    ) {

                        echo '9+';

                    } else {

                        echo
                            $pending_reservations;

                    }

                    ?>

                </span>

            <?php } ?>


        </a>



        <!-- VERTICAL DIVIDER -->

        <div class="topbar-divider"></div>



        <!-- MANAGER PROFILE -->

        <a
            href="manager_profile.php"
            class="manager-top-profile"
        >


            <!-- AVATAR -->

            <div class="manager-avatar">

                <i class="fa-solid fa-user"></i>

            </div>



            <!-- MANAGER INFORMATION -->

            <div class="manager-account-details">


                <!-- MANAGER -->

                <div class="manager-top-name">

                    Manager

                </div>


<!-- DATE AND TIME -->

<div class="manager-date-time">


    <!-- DATE -->

    <span
        class="manager-date"
        id="currentDate"
    >

        Loading date...

    </span>


    <!-- TIME -->

    <span
        class="manager-time"
        id="currentTime"
    >

        Loading time...

    </span>


</div>


            </div>



            <!-- DROPDOWN -->

            <div class="manager-dropdown-icon">

                <i
                    class="
                        fa-solid
                        fa-chevron-down
                    "
                ></i>

            </div>


        </a>


    </div>


</div>



        <!-- =============================================
             DASHBOARD CONTENT
        ============================================== -->

        <div class="dashboard-content">


            <!-- =========================================
                 GREETING
            ========================================== -->

          <div class="dashboard-header">

    <div class="greeting-wrapper">

        <!-- Leaf Icon -->

        <div class="greeting-icon">

            <i class="fa-solid fa-leaf"></i>

        </div>


        <!-- Greeting Text -->

        <div class="greeting-text-container">

            <h1 class="greeting">

                Good Morning,
                <?php echo htmlspecialchars($manager_name); ?>!

            </h1>


            <p class="dashboard-subtitle">

               Here's an overview of your farm operations.

            </p>

        </div>

    </div>

</div>



            <!-- =========================================
                 STATISTICS
            ========================================== -->

            <div class="statistics-grid">


                <!-- PENDING RESERVATIONS -->

                <div class="stat-card">


                    <div class="stat-card-top">


                        <div
                            class="
                                stat-icon
                                reservation
                            "
                        >

                            <i
                                class="
                                    fa-regular
                                    fa-calendar-days
                                "
                            ></i>

                        </div>


                        <div class="stat-info">


                            <div class="stat-title">

                                Pending Reservation

                            </div>


                            <div class="stat-number">

                                <?php
                                echo number_format(
                                    $pending_reservations
                                );
                                ?>

                                trays

                            </div>


                            <div class="stat-description">

                                Awaiting confirmation

                            </div>


                        </div>


                    </div>


                </div>



                <!-- EGG INVENTORY -->

                <div class="stat-card">


                    <div class="stat-card-top">


                        <div
                            class="
                                stat-icon
                                inventory
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-egg
                                "
                            ></i>

                        </div>


                        <div class="stat-info">


                            <div class="stat-title">

                                Egg Inventory

                            </div>


                            <div class="stat-number">

                                <?php
                                echo number_format(
                                    $total_egg_stock
                                );
                                ?>

                                trays

                            </div>


                            <div class="stat-description">

                                Available egg stocks

                            </div>


                        </div>


                    </div>


                </div>



                <!-- TODAY'S DELIVERIES -->

                <div class="stat-card">


                    <div class="stat-card-top">


                        <div
                            class="
                                stat-icon
                                delivery
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-truck
                                "
                            ></i>

                        </div>


                        <div class="stat-info">


                            <div class="stat-title">

                                Today's Deliveries

                            </div>


                            <div class="stat-number">

                                <?php
                                echo number_format(
                                    $today_deliveries
                                );
                                ?>

                            </div>


                            <div class="stat-description">

                                Scheduled for today

                            </div>


                        </div>


                    </div>


                </div>



                <!-- COMPLETED DELIVERIES -->

                <div class="stat-card">


                    <div class="stat-card-top">


                        <div
                            class="
                                stat-icon
                                completed
                            "
                        >

                            <i
                                class="
                                    fa-solid
                                    fa-check
                                "
                            ></i>

                        </div>


                        <div class="stat-info">


                            <div class="stat-title">

                                Completed Deliveries

                            </div>


                            <div class="stat-number">

                                <?php
                                echo number_format(
                                    $completed_deliveries
                                );
                                ?>

                            </div>


                            <div class="stat-description">

                                Successfully completed

                            </div>


                        </div>


                    </div>


                </div>


            </div>



            <!-- =========================================
                 MAIN DASHBOARD GRID
            ========================================== -->

            <div class="dashboard-main-grid">


                <!-- =====================================
                     LEFT SIDE - EGG STOCKS
                ====================================== -->

                <section
                    class="
                        dashboard-panel
                        egg-stocks-panel
                    "
                >


                    <div class="panel-header">


                        <div class="panel-title-group">


                            <div class="panel-title-icon">

                                <i
                                    class="
                                        fa-solid
                                        fa-egg
                                    "
                                ></i>

                            </div>


                            <div
                                class="
                                    dashboard-panel-title
                                "
                            >

                                Available Egg Stocks

                            </div>


                        </div>


                    </div>



                    <div class="table-wrapper">


                        <table class="dashboard-table">


                            <thead>


                                <tr>

                                    <th>
                                        Egg Size
                                    </th>

<th>
    Available Pieces
</th>

                                    <th>
                                        Available Trays
                                    </th>


                                    <th>
                                        Stock Status
                                    </th>

                                </tr>


                            </thead>


                            <tbody>


                                <?php

                                if (
                                    count($egg_data) > 0
                                ) {

                                ?>


                                    <?php

                                    foreach (
                                        $egg_data
                                        as $egg
                                    ) {

                                    ?>


                                        <tr>


                                            <td>


                                                <div
                                                    class="
                                                        egg-table-cell
                                                    "
                                                >


                                                    <div
                                                        class="
                                                            egg-small-icon
                                                        "
                                                    >

                                                        <i
                                                            class="
                                                                fa-solid
                                                                fa-egg
                                                            "
                                                        ></i>

                                                    </div>


                                                    <?php

                                                    echo
                                                        htmlspecialchars(

                                                            $egg[
                                                                'egg_size'
                                                            ]

                                                        );

                                                    ?>


                                                </div>


                                            </td>


                                    <!-- AVAILABLE PIECES -->

<td
    class="
        stock-number
        available-pieces
    "
>

    <?php

    $available_pieces =
        $egg[
            'available_stock'
        ] * 30;


    echo
        number_format(
            $available_pieces
        );

    ?>

    pcs

</td>


<!-- AVAILABLE TRAYS -->

<td
    class="
        stock-number
    "
>

    <?php

    echo
        number_format(

            $egg[
                'available_stock'
            ]

        );

    ?>

    trays

</td>


                                            <td>


                                                <span
                                                    class="
                                                        stock-status
                                                    "
                                                >

                                                    In Stock

                                                </span>


                                            </td>


                                        </tr>


                                    <?php } ?>


                                <?php

                                }

                                ?>


                            </tbody>


                        </table>


                    </div>


                </section>



                <!-- =====================================
                     RIGHT SIDE
                ====================================== -->

                <div
                    class="
                        dashboard-right-column
                    "
                >


                    <!-- =================================
                         SUPPLIES
                    ================================== -->

                    <section
                        class="dashboard-panel"
                    >


                        <div class="panel-header">


                            <div
                                class="
                                    panel-title-group
                                "
                            >


                                <div
                                    class="
                                        panel-title-icon
                                    "
                                >

                                    <i
                                        class="
                                            fa-solid
                                            fa-cube
                                        "
                                    ></i>

                                </div>


                                <div
                                    class="
                                        dashboard-panel-title
                                    "
                                >

                                    Available Stocks -
                                    Supplies

                                </div>


                            </div>


                            <a
                                href="inventory.php"
                                class="view-all"
                            >

                                View All

                            </a>


                        </div>



                        <div class="supply-list">


                            <?php


                            $display_supply_count = 0;


                            foreach (
                                $supply_data
                                as $supply
                            ) {


                                if (
                                    $display_supply_count >= 4
                                ) {

                                    break;

                                }


                                $category = 'Supply';

                                $item_name = 'Unnamed Item';

                                $stock = 0;

                                $unit = '';


                                /* CATEGORY */

                                if (
                                    isset(
                                        $supply['category']
                                    )
                                ) {

                                    $category =
                                        $supply[
                                            'category'
                                        ];

                                }


                                /* ITEM NAME */

                                if (
                                    isset(
                                        $supply['item_name']
                                    ) &&

                                    $supply[
                                        'item_name'
                                    ] != ''
                                ) {

                                    $item_name =
                                        $supply[
                                            'item_name'
                                        ];

                                } elseif (
                                    isset(
                                        $supply['item']
                                    ) &&

                                    $supply[
                                        'item'
                                    ] != ''
                                ) {

                                    $item_name =
                                        $supply[
                                            'item'
                                        ];

                                }


                                /* STOCK */

                                if (
                                    isset(
                                        $supply[
                                            'available_stock'
                                        ]
                                    )
                                ) {

                                    $stock =
                                        $supply[
                                            'available_stock'
                                        ];

                                } elseif (
                                    isset(
                                        $supply[
                                            'quantity'
                                        ]
                                    )
                                ) {

                                    $stock =
                                        $supply[
                                            'quantity'
                                        ];

                                }


                                /* UNIT */

                                if (
                                    isset(
                                        $supply['unit']
                                    )
                                ) {

                                    $unit =
                                        $supply['unit'];

                                }


                                $icon_class =
                                    get_supply_icon(

                                        $category,

                                        $item_name

                                    );


                                $icon_type =
                                    get_supply_icon_type(

                                        $category

                                    );


                                $display_supply_count++;

                            ?>


                                <div
                                    class="
                                        supply-item
                                    "
                                >


                                    <div
                                        class="
                                            supply-small-icon
                                            <?php
                                            echo $icon_type;
                                            ?>
                                        "
                                    >

                                        <i
                                            class="
                                                <?php
                                                echo $icon_class;
                                                ?>
                                            "
                                        ></i>

                                    </div>


                                    <div
                                        class="
                                            supply-info
                                        "
                                    >


                                        <div
                                            class="
                                                supply-name
                                            "
                                        >

                                            <?php

                                            echo
                                                htmlspecialchars(

                                                    $item_name

                                                );

                                            ?>

                                        </div>


                                        <div
                                            class="
                                                supply-category
                                            "
                                        >

                                            <?php

                                            echo
                                                htmlspecialchars(

                                                    $category

                                                );

                                            ?>

                                        </div>


                                    </div>


                                    <div
                                        class="
                                            supply-stock
                                        "
                                    >

                                        <?php

                                        echo
                                            number_format(
                                                $stock
                                            );

                                        ?>


                                        <?php

                                        if (
                                            $unit != ''
                                        ) {

                                            echo ' ' .
                                                htmlspecialchars(
                                                    $unit
                                                );

                                        }

                                        ?>


                                    </div>


                                    <span
                                        class="
                                            supply-status
                                        "
                                    >

                                        In Stock

                                    </span>


                                </div>


                            <?php } ?>


                            <?php

                            if (
                                $display_supply_count == 0
                            ) {

                            ?>


                                <div class="empty-state">

                                    No supply inventory found.

                                </div>


                            <?php } ?>


                        </div>


                    </section>



                    <!-- =================================
                         RECENT ACTIVITIES
                    ================================== -->

                    <section
                        class="dashboard-panel"
                    >


                        <div class="panel-header">


                            <div
                                class="
                                    panel-title-group
                                "
                            >


                                <div
                                    class="
                                        panel-title-icon
                                    "
                                >

                                    <i
                                        class="
                                            fa-regular
                                            fa-clock
                                        "
                                    ></i>

                                </div>


                                <div
                                    class="
                                        dashboard-panel-title
                                    "
                                >

                                    Recent Activities

                                </div>


                            </div>


                            <a
                                href="manager_reservation.php"
                                class="view-all"
                            >

                                View All

                            </a>


                        </div>



                        <div class="activity-list">


                            <?php

                            if (
                                count(
                                    $recent_activities
                                ) > 0
                            ) {

                            ?>


                                <?php

                                foreach (
                                    $recent_activities
                                    as $activity
                                ) {

                                ?>


                                    <div
                                        class="
                                            activity-item
                                        "
                                    >


                                        <!-- ACTIVITY ICON -->

                                        <div
                                            class="
                                                activity-icon
                                                <?php

                                                echo
                                                    $activity[
                                                        'type'
                                                    ];

                                                ?>
                                            "
                                        >


                                            <?php


                                            if (
                                                $activity[
                                                    'type'
                                                ] == 'reservation'
                                            ) {

                                            ?>


                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-calendar-plus
                                                    "
                                                ></i>


                                            <?php

                                            } elseif (
                                                $activity[
                                                    'type'
                                                ] == 'inventory'
                                            ) {

                                            ?>


                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-box
                                                    "
                                                ></i>


                                            <?php

                                            } else {

                                            ?>


                                                <i
                                                    class="
                                                        fa-solid
                                                        fa-circle-check
                                                    "
                                                ></i>


                                            <?php } ?>


                                        </div>


                                        <!-- ACTIVITY CONTENT -->

                                        <div
                                            class="
                                                activity-content
                                            "
                                        >


                                            <div
                                                class="
                                                    activity-title
                                                "
                                            >

                                                <?php

                                                echo
                                                    htmlspecialchars(

                                                        $activity[
                                                            'title'
                                                        ]

                                                    );

                                                ?>

                                            </div>


                                            <div
                                                class="
                                                    activity-description
                                                "
                                            >

                                                <?php

                                                echo
                                                    htmlspecialchars(

                                                        $activity[
                                                            'description'
                                                        ]

                                                    );

                                                ?>

                                            </div>


                                        </div>


                                        <!-- TIME -->

                                        <div
                                            class="
                                                activity-time
                                            "
                                        >


                                            <?php


                                            if (
                                                isset(
                                                    $activity[
                                                        'date'
                                                    ]
                                                ) &&

                                                $activity[
                                                    'date'
                                                ] != ''
                                            ) {


                                                echo
                                                    date(

                                                        'M d, h:i A',

                                                        strtotime(

                                                            $activity[
                                                                'date'
                                                            ]

                                                        )

                                                    );


                                            } else {

                                                echo 'Recent';

                                            }


                                            ?>


                                        </div>


                                    </div>


                                <?php } ?>


                            <?php

                            } else {

                            ?>


                                <div class="empty-state">

                                    No recent activity found.

                                </div>


                            <?php } ?>


                        </div>


                    </section>


                </div>


            </div>


        </div>


    </div>


</div>



<script>


/* =========================================================
   DYNAMIC GREETING
========================================================= */

function updateGreeting()
{

    var hour =
        new Date().getHours();


    var greeting =
        'Good morning';


    if (
        hour >= 12 &&
        hour < 18
    ) {

        greeting =
            'Good afternoon';

    }


    if (hour >= 18) {

        greeting =
            'Good evening';

    }


    var managerName =
        "<?php
        echo addslashes(
            $manager_name
        );
        ?>";


    var greetingElement =
        document.querySelector(
            '.greeting'
        );


    if (greetingElement) {

        greetingElement.innerHTML =

            greeting +

            ', ' +

            managerName +

            '!';

    }

}


updateGreeting();

/* =========================================================
   CURRENT DATE AND TIME
========================================================= */

function updateDateTime()
{

    var now =
        new Date();


    var dateOptions = {

        year: 'numeric',

        month: 'long',

        day: 'numeric'

    };


    var currentDate =
        now.toLocaleDateString(

            'en-US',

            dateOptions

        );


    var currentTime =
        now.toLocaleTimeString(

            'en-US',

            {

                hour:
                    'numeric',

                minute:
                    '2-digit',

                second:
                    '2-digit',

                hour12:
                    true

            }

        );


    var dateElement =
        document.getElementById(
            'currentDate'
        );


    var timeElement =
        document.getElementById(
            'currentTime'
        );


    if (dateElement) {

        dateElement.innerHTML =
            currentDate;

    }


    if (timeElement) {

        timeElement.innerHTML =
            currentTime;

    }

}


/* RUN IMMEDIATELY */

updateDateTime();


/* UPDATE EVERY SECOND */

setInterval(

    updateDateTime,

    1000

);
</script>


</body>


</html>