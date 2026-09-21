<?php
/*
|--------------------------------------------------------------------------
| MANAGER REPORTS
|--------------------------------------------------------------------------
| Compatible with PHP 5.5.x
| Also written using older PHP syntax for better compatibility.
|--------------------------------------------------------------------------
*/

ob_start();
session_start();

include 'db.php';

/*
|--------------------------------------------------------------------------
| BASIC SETTINGS
|--------------------------------------------------------------------------
*/

if (function_exists('date_default_timezone_set')) {
    date_default_timezone_set('Asia/Manila');
}

/*
|--------------------------------------------------------------------------
| HELPER FUNCTIONS
|--------------------------------------------------------------------------
*/

function h($value)
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}

function clean_date($date, $default)
{
    if (preg_match('/^[0-9]{4}-[0-9]{2}-[0-9]{2}$/', $date)) {
        return $date;
    }

    return $default;
}

function get_table_columns($conn, $table)
{
    $columns = array();

    $safe_table = $conn->real_escape_string($table);

    $result = $conn->query("SHOW COLUMNS FROM `" . $safe_table . "`");

    if ($result) {
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }

        $result->free();
    }

    return $columns;
}

function find_column($columns, $possible)
{
    $i = 0;

    while ($i < count($possible)) {

        $target = strtolower($possible[$i]);

        $j = 0;

        while ($j < count($columns)) {

            if (strtolower($columns[$j]) == $target) {
                return $columns[$j];
            }

            $j++;
        }

        $i++;
    }

    return '';
}

function sql_date_column($column)
{
    if ($column == '') {
        return '';
    }

    return '`' . $column . '`';
}

function format_date_time($value)
{
    if ($value == '' || $value == '0000-00-00 00:00:00') {
        return '-';
    }

    $time = strtotime($value);

    if ($time === false) {
        return h($value);
    }

    return date('M d, Y h:i A', $time);
}

function format_date_only($value)
{
    if ($value == '' || $value == '0000-00-00') {
        return '-';
    }

    $time = strtotime($value);

    if ($time === false) {
        return h($value);
    }

    return date('M d, Y', $time);
}

function number_value($value)
{
    if ($value === null || $value === '') {
        return 0;
    }

    return (float)$value;
}

function number_display($value)
{
    $value = number_value($value);

    if (floor($value) == $value) {
        return number_format($value, 0);
    }

    return number_format($value, 2);
}

function icon_svg($name, $size)
{
    $s = (int)$size;

    $common = 'width="' . $s . '" height="' . $s . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"';

    if ($name == 'file') {
        return '<svg ' . $common . '><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path><polyline points="14 2 14 8 20 8"></polyline><line x1="8" y1="13" x2="16" y2="13"></line><line x1="8" y1="17" x2="16" y2="17"></line></svg>';
    }

    if ($name == 'box') {
        return '<svg ' . $common . '><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13v8"></path></svg>';
    }

    if ($name == 'calendar') {
        return '<svg ' . $common . '><rect x="3" y="4" width="18" height="17" rx="2"></rect><line x1="16" y1="2" x2="16" y2="6"></line><line x1="8" y1="2" x2="8" y2="6"></line><line x1="3" y1="10" x2="21" y2="10"></line></svg>';
    }

    if ($name == 'truck') {
        return '<svg ' . $common . '><rect x="1" y="5" width="13" height="11"></rect><polygon points="14 8 19 8 23 12 23 16 14 16"></polygon><circle cx="6" cy="18" r="2"></circle><circle cx="18" cy="18" r="2"></circle></svg>';
    }

    if ($name == 'download') {
        return '<svg ' . $common . '><path d="M12 3v12"></path><polyline points="7 10 12 15 17 10"></polyline><path d="M5 21h14"></path></svg>';
    }

    if ($name == 'search') {
        return '<svg ' . $common . '><circle cx="11" cy="11" r="7"></circle><line x1="16.5" y1="16.5" x2="21" y2="21"></line></svg>';
    }

    if ($name == 'refresh') {
        return '<svg ' . $common . '><polyline points="23 4 23 10 17 10"></polyline><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"></path></svg>';
    }

    if ($name == 'arrow-down') {
        return '<svg ' . $common . '><line x1="12" y1="4" x2="12" y2="20"></line><polyline points="6 14 12 20 18 14"></polyline></svg>';
    }

    if ($name == 'arrow-up') {
        return '<svg ' . $common . '><line x1="12" y1="20" x2="12" y2="4"></line><polyline points="6 10 12 4 18 10"></polyline></svg>';
    }

    if ($name == 'layers') {
        return '<svg ' . $common . '><polygon points="12 2 22 7 12 12 2 7 12 2"></polygon><polyline points="2 12 12 17 22 12"></polyline><polyline points="2 17 12 22 22 17"></polyline></svg>';
    }

    if ($name == 'check') {
        return '<svg ' . $common . '><polyline points="20 6 9 17 4 12"></polyline></svg>';
    }

    if ($name == 'clock') {
        return '<svg ' . $common . '><circle cx="12" cy="12" r="9"></circle><polyline points="12 7 12 12 15 14"></polyline></svg>';
    }

    if ($name == 'x') {
        return '<svg ' . $common . '><line x1="6" y1="6" x2="18" y2="18"></line><line x1="18" y1="6" x2="6" y2="18"></line></svg>';
    }

    if ($name == 'package') {
        return '<svg ' . $common . '><path d="M21 8l-9-5-9 5 9 5 9-5z"></path><path d="M3 8v8l9 5 9-5V8"></path><path d="M12 13l9-5"></path><path d="M12 13L3 8"></path></svg>';
    }
    if ($name == 'bell') {
        return '<svg ' . $common . '><path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9"></path><path d="M10 21h4"></path></svg>';
    }
    return '';
}

/*
|--------------------------------------------------------------------------
| DATE RANGE
|--------------------------------------------------------------------------
*/

$current_year  = date('Y');
$current_month = date('m');

$default_from = date('Y-m-01');
$default_to   = date('Y-m-t');

$report_type = isset($_GET['report_type']) ? $_GET['report_type'] : 'inventory';

if ($report_type != 'inventory' &&
    $report_type != 'reservation' &&
    $report_type != 'delivery') {

    $report_type = 'inventory';
}

$date_from = isset($_GET['date_from']) ?
    clean_date($_GET['date_from'], $default_from) :
    $default_from;

$date_to = isset($_GET['date_to']) ?
    clean_date($_GET['date_to'], $default_to) :
    $default_to;

$search = isset($_GET['search']) ? trim($_GET['search']) : '';

$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($page < 1) {
    $page = 1;
}

$per_page = 7;

/*
|--------------------------------------------------------------------------
| TABLE COLUMN DETECTION
|--------------------------------------------------------------------------
| This helps the report adapt to your existing database structure.
|--------------------------------------------------------------------------
*/

$egg_columns = get_table_columns($conn, 'egg_inventory');
$supply_columns = get_table_columns($conn, 'supply_inventory');
$reservation_columns = get_table_columns($conn, 'reservations');

/*
|--------------------------------------------------------------------------
| COMMON COLUMN NAMES
|--------------------------------------------------------------------------
*/

$egg_date = find_column($egg_columns, array(
    'date_logged',
    'recorded_at',
    'created_at',
    'date',
    'transaction_date'
));

$egg_type = find_column($egg_columns, array(
    'egg_type',
    'item_name',
    'egg_size',
    'size',
    'item'
));

$egg_quantity = find_column($egg_columns, array(
    'quantity',
    'qty'
));

$egg_unit = find_column($egg_columns, array(
    'unit',
    'unit_type'
));

$egg_reason = find_column($egg_columns, array(
    'reason',
    'notes',
    'remarks'
));

$egg_type_movement = find_column($egg_columns, array(
    'type',
    'movement_type',
    'transaction_type',
    'stock_type',
    'movement',
    'transaction',
    'action',
    'stock_movement',
    'inventory_type'
));

$supply_date = find_column($supply_columns, array(
    'date_logged',
    'recorded_at',
    'created_at',
    'date',
    'transaction_date'
));

$supply_category = find_column($supply_columns, array(
    'category',
    'item_category',
    'supply_category'
));

$supply_item = find_column($supply_columns, array(
    'item_name',
    'item',
    'description',
    'supply_name'
));

$supply_quantity = find_column($supply_columns, array(
    'quantity',
    'qty'
));

$supply_unit = find_column($supply_columns, array(
    'unit',
    'unit_type'
));

$supply_reason = find_column($supply_columns, array(
    'reason',
    'notes',
    'remarks'
));

$supply_type = find_column($supply_columns, array(
    'type',
    'movement_type',
    'transaction_type',
    'stock_type',
    'movement',
    'transaction',
    'action',
    'stock_movement',
    'inventory_type'
));

$res_date = find_column($reservation_columns, array(
    'reservation_date',
    'reserved_at',
    'created_at',
    'date'
));

$res_code = find_column($reservation_columns, array(
    'reservation_code',
    'reservation_id',
    'code'
));

$res_customer = find_column($reservation_columns, array(
    'customer_name',
    'name',
    'customer'
));

$res_egg = find_column($reservation_columns, array(
    'egg_type',
    'egg_size',
    'size',
    'item_name'
));

$res_quantity = find_column($reservation_columns, array(
    'quantity',
    'qty',
    'trays',
    'tray_quantity'
));

$res_status = find_column($reservation_columns, array(
    'status'
));

$res_delivery = find_column($reservation_columns, array(
    'delivery_method',
    'delivery_type',
    'method'
));

$res_price = find_column($reservation_columns, array(
    'total_price',
    'price',
    'amount'
));

$res_recorded = find_column($reservation_columns, array(
    'reserved_at',
    'created_at',
    'date_created'
));
/*
|--------------------------------------------------------------------------
| MANAGER HEADER NOTIFICATIONS
|--------------------------------------------------------------------------
*/

$notification_count = 0;
$notifications = array();

if (count($reservation_columns) > 0 &&
    $res_status != '') {

    /*
    |--------------------------------------------------------------------------
    | Notification count
    |--------------------------------------------------------------------------
    */

    if ($res_code != '') {

        $notification_sql =
            "SELECT COUNT(DISTINCT `" . $res_code . "`) AS total
             FROM `reservations`
             WHERE LOWER(`" . $res_status . "`) = 'pending'";

    } else {

        $notification_sql =
            "SELECT COUNT(*) AS total
             FROM `reservations`
             WHERE LOWER(`" . $res_status . "`) = 'pending'";
    }

    $notification_result =
        $conn->query($notification_sql);

    if ($notification_result) {

        $notification_row =
            $notification_result->fetch_assoc();

        if ($notification_row &&
            isset($notification_row['total'])) {

            $notification_count =
                (int)$notification_row['total'];
        }

        $notification_result->free();
    }


    /*
    |--------------------------------------------------------------------------
    | Notification list
    |--------------------------------------------------------------------------
    */

    if ($res_code != '' &&
        $res_customer != '') {

        $notification_date_column = '';

        if ($res_recorded != '') {
            $notification_date_column = $res_recorded;
        } else if ($res_date != '') {
            $notification_date_column = $res_date;
        }

        if ($notification_date_column != '') {

            $notification_sql =
                "SELECT
                    `" . $res_code . "` AS notification_code,
                    MAX(`" . $res_customer . "`) AS notification_customer,
                    MAX(`" . $notification_date_column . "`) AS notification_date
                 FROM `reservations`
                 WHERE LOWER(`" . $res_status . "`) = 'pending'
                 GROUP BY `" . $res_code . "`
                 ORDER BY MAX(`" . $notification_date_column . "`) DESC
                 LIMIT 8";

            $notification_result =
                $conn->query($notification_sql);

            if ($notification_result) {

                while ($notification_row =
                       $notification_result->fetch_assoc()) {

                    $notifications[] = array(
                        'code' =>
                            isset($notification_row['notification_code']) ?
                            $notification_row['notification_code'] :
                            'Reservation',

                        'customer' =>
                            isset($notification_row['notification_customer']) ?
                            $notification_row['notification_customer'] :
                            'Customer',

                        'date' =>
                            isset($notification_row['notification_date']) ?
                            $notification_row['notification_date'] :
                            ''
                    );
                }

                $notification_result->free();
            }
        }
    }
}
/*
|--------------------------------------------------------------------------
| VARIABLES
|--------------------------------------------------------------------------
*/

$summary = array(
    'card1' => 0,
    'card2' => 0,
    'card3' => 0,
    'card4' => 0,
    'card5' => 0
);

$summary_labels = array(
    'card1' => '',
    'card2' => '',
    'card3' => '',
    'card4' => '',
    'card5' => ''
);

$summary_icons = array(
    'card1' => 'arrow-down',
    'card2' => 'arrow-up',
    'card3' => 'layers',
    'card4' => 'box',
    'card5' => 'box'
);

$summary_colors = array(
    'card1' => 'green',
    'card2' => 'red',
    'card3' => 'blue',
    'card4' => 'green',
    'card5' => 'red'
);

$rows = array();
$total_rows = 0;
$total_pages = 1;

$error_message = '';

/*
|--------------------------------------------------------------------------
| INVENTORY REPORT
|--------------------------------------------------------------------------
*/

if ($report_type == 'inventory') {

    $summary_labels['card1'] = 'Stock In Transactions';
    $summary_labels['card2'] = 'Stock Out Transactions';
    $summary_labels['card3'] = 'Items Affected';
    $summary_labels['card4'] = 'Egg Stock In';
    $summary_labels['card5'] = 'Egg Stock Out';

    $summary_icons['card1'] = 'arrow-down';
    $summary_icons['card2'] = 'arrow-up';
    $summary_icons['card3'] = 'layers';
    $summary_icons['card4'] = 'box';
    $summary_icons['card5'] = 'box';

    /*
    |--------------------------------------------------------------------------
    | EGG INVENTORY
    |--------------------------------------------------------------------------
    */

    if (count($egg_columns) > 0 && $egg_date != '' && $egg_quantity != '') {

        $sql = "SELECT * FROM `egg_inventory`
                WHERE DATE(`" . $egg_date . "`) >= '" . $conn->real_escape_string($date_from) . "'
                AND DATE(`" . $egg_date . "`) <= '" . $conn->real_escape_string($date_to) . "'";

        if ($search != '') {

            $search_safe = $conn->real_escape_string($search);

            $conditions = array();

            if ($egg_type != '') {
                $conditions[] = "`" . $egg_type . "` LIKE '%" . $search_safe . "%'";
            }

            if ($egg_reason != '') {
                $conditions[] = "`" . $egg_reason . "` LIKE '%" . $search_safe . "%'";
            }

            if ($egg_type_movement != '') {
                $conditions[] = "`" . $egg_type_movement . "` LIKE '%" . $search_safe . "%'";
            }

            if (count($conditions) > 0) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }

        $sql .= " ORDER BY `" . $egg_date . "` DESC";

        $result = $conn->query($sql);

        if ($result) {

            while ($row = $result->fetch_assoc()) {

                $movement = '';

                if ($egg_type_movement != '') {
                    $movement = isset($row[$egg_type_movement]) ? $row[$egg_type_movement] : '';
                }

           /* ---------------------------------------------------------------
   DETERMINE STOCK MOVEMENT
   First check TYPE.
   If TYPE is empty, determine it from REASON / NOTES.
   --------------------------------------------------------------- */

$movement = trim($movement);

/*
|--------------------------------------------------------------------------
| Get the reason / notes
|--------------------------------------------------------------------------
*/

$reason_value = '';

if ($egg_reason != '' && isset($row[$egg_reason])) {
    $reason_value = trim($row[$egg_reason]);
}

$movement_lower = strtolower($movement);
$reason_lower = strtolower($reason_value);


/*
|--------------------------------------------------------------------------
| 1. Check TYPE first
|--------------------------------------------------------------------------
*/

if (
    $movement_lower == 'in' ||
    $movement_lower == 'stock in' ||
    $movement_lower == 'stock_in' ||
    $movement_lower == 'stock-in' ||
    $movement_lower == 'stockin' ||
    $movement_lower == 'add' ||
    $movement_lower == 'added' ||
    $movement_lower == 'increase' ||
    $movement_lower == 'increased'
) {

    $movement = 'Stock In';

}


/*
|--------------------------------------------------------------------------
| 2. Check STOCK OUT
|--------------------------------------------------------------------------
*/

else if (
    $movement_lower == 'out' ||
    $movement_lower == 'stock out' ||
    $movement_lower == 'stock_out' ||
    $movement_lower == 'stock-out' ||
    $movement_lower == 'stockout' ||
    $movement_lower == 'remove' ||
    $movement_lower == 'removed' ||
    $movement_lower == 'decrease' ||
    $movement_lower == 'decreased'
) {

    $movement = 'Stock Out';

}


/*
|--------------------------------------------------------------------------
| 3. TYPE IS EMPTY
|    Determine movement from REASON / NOTES
|--------------------------------------------------------------------------
*/

else if ($reason_lower != '') {

    /*
    |--------------------------------------------------------------------------
    | STOCK IN REASONS
    |--------------------------------------------------------------------------
    */

    if (
        strpos($reason_lower, 'new stock') !== false ||
        strpos($reason_lower, 'new supply') !== false ||
        strpos($reason_lower, 'restock') !== false ||
        strpos($reason_lower, 'restocking') !== false ||
        strpos($reason_lower, 'purchase') !== false ||
        strpos($reason_lower, 'purchased') !== false ||
        strpos($reason_lower, 'added') !== false ||
        strpos($reason_lower, 'add stock') !== false ||
        strpos($reason_lower, 'stock added') !== false ||
        strpos($reason_lower, 'receive') !== false ||
        strpos($reason_lower, 'received') !== false
    ) {

        $movement = 'Stock In';

    }


    /*
    |--------------------------------------------------------------------------
    | STOCK OUT REASONS
    |--------------------------------------------------------------------------
    */

    else if (
        strpos($reason_lower, 'reservation') !== false ||
        strpos($reason_lower, 'usage') !== false ||
        strpos($reason_lower, 'used') !== false ||
        strpos($reason_lower, 'damage') !== false ||
        strpos($reason_lower, 'damaged') !== false ||
        strpos($reason_lower, 'expired') !== false ||
        strpos($reason_lower, 'release') !== false ||
        strpos($reason_lower, 'released') !== false ||
        strpos($reason_lower, 'sold') !== false ||
        strpos($reason_lower, 'delivery') !== false
    ) {

        $movement = 'Stock Out';

    }

}


/*
|--------------------------------------------------------------------------
| 4. If still unknown, don't show a blank badge.
|--------------------------------------------------------------------------
*/

if ($movement == '') {

    $movement = 'Stock In';

}

                $quantity = isset($row[$egg_quantity]) ?
                    number_value($row[$egg_quantity]) :
                    0;

                $summary['card1'] += ($movement == 'Stock In') ? 1 : 0;
                $summary['card2'] += ($movement == 'Stock Out') ? 1 : 0;

                if ($movement == 'Stock In') {
                    $summary['card4'] += $quantity;
                }

                if ($movement == 'Stock Out') {
                    $summary['card5'] += $quantity;
                }

                $rows[] = array(
                    'date' => ($egg_date != '' && isset($row[$egg_date])) ?
                        $row[$egg_date] : '',
                    'category' => 'Eggs',
                    'item' => ($egg_type != '' && isset($row[$egg_type])) ?
                        $row[$egg_type] : 'Eggs',
                    'type' => $movement,
                    'quantity' => $quantity,
                    'unit' => ($egg_unit != '' && isset($row[$egg_unit])) ?
                        $row[$egg_unit] : 'Trays',
                    'reason' => ($egg_reason != '' && isset($row[$egg_reason])) ?
                        $row[$egg_reason] : '-',
                    'recorded' => 'Manager'
                );
            }

            $result->free();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | SUPPLY INVENTORY
    |--------------------------------------------------------------------------
    */

    if (count($supply_columns) > 0 &&
        $supply_date != '' &&
        $supply_quantity != '') {

        $sql = "SELECT * FROM `supply_inventory`
                WHERE DATE(`" . $supply_date . "`) >= '" . $conn->real_escape_string($date_from) . "'
                AND DATE(`" . $supply_date . "`) <= '" . $conn->real_escape_string($date_to) . "'";

        if ($search != '') {

            $search_safe = $conn->real_escape_string($search);

            $conditions = array();

            if ($supply_category != '') {
                $conditions[] = "`" . $supply_category . "` LIKE '%" . $search_safe . "%'";
            }

            if ($supply_item != '') {
                $conditions[] = "`" . $supply_item . "` LIKE '%" . $search_safe . "%'";
            }

            if ($supply_reason != '') {
                $conditions[] = "`" . $supply_reason . "` LIKE '%" . $search_safe . "%'";
            }

            if ($supply_type != '') {
                $conditions[] = "`" . $supply_type . "` LIKE '%" . $search_safe . "%'";
            }

            if (count($conditions) > 0) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }

        $sql .= " ORDER BY `" . $supply_date . "` DESC";

        $result = $conn->query($sql);

        if ($result) {

            while ($row = $result->fetch_assoc()) {

                $movement = '';

                if ($supply_type != '') {
                    $movement = isset($row[$supply_type]) ?
                        $row[$supply_type] : '';
                }
/* ---------------------------------------------------------------
   DETERMINE SUPPLY STOCK MOVEMENT
   --------------------------------------------------------------- */

$movement = trim($movement);

$reason_value = '';

if ($supply_reason != '' && isset($row[$supply_reason])) {
    $reason_value = trim($row[$supply_reason]);
}

$movement_lower = strtolower($movement);
$reason_lower = strtolower($reason_value);


/*
|--------------------------------------------------------------------------
| STOCK IN
|--------------------------------------------------------------------------
*/

if (
    $movement_lower == 'in' ||
    $movement_lower == 'stock in' ||
    $movement_lower == 'stock_in' ||
    $movement_lower == 'stock-in' ||
    $movement_lower == 'stockin' ||
    $movement_lower == 'add' ||
    $movement_lower == 'added' ||
    $movement_lower == 'increase' ||
    $movement_lower == 'increased'
) {

    $movement = 'Stock In';

}


/*
|--------------------------------------------------------------------------
| STOCK OUT
|--------------------------------------------------------------------------
*/

else if (
    $movement_lower == 'out' ||
    $movement_lower == 'stock out' ||
    $movement_lower == 'stock_out' ||
    $movement_lower == 'stock-out' ||
    $movement_lower == 'stockout' ||
    $movement_lower == 'remove' ||
    $movement_lower == 'removed' ||
    $movement_lower == 'decrease' ||
    $movement_lower == 'decreased'
) {

    $movement = 'Stock Out';

}


/*
|--------------------------------------------------------------------------
| USE REASON / NOTES AS FALLBACK
|--------------------------------------------------------------------------
*/

else if ($reason_lower != '') {

    /*
    |--------------------------------------------------------------------------
    | STOCK IN
    |--------------------------------------------------------------------------
    */

    if (
        strpos($reason_lower, 'new stock') !== false ||
        strpos($reason_lower, 'new supply') !== false ||
        strpos($reason_lower, 'restock') !== false ||
        strpos($reason_lower, 'restocking') !== false ||
        strpos($reason_lower, 'purchase') !== false ||
        strpos($reason_lower, 'purchased') !== false ||
        strpos($reason_lower, 'added') !== false ||
        strpos($reason_lower, 'add stock') !== false ||
        strpos($reason_lower, 'stock added') !== false ||
        strpos($reason_lower, 'receive') !== false ||
        strpos($reason_lower, 'received') !== false
    ) {

        $movement = 'Stock In';

    }


    /*
    |--------------------------------------------------------------------------
    | STOCK OUT
    |--------------------------------------------------------------------------
    */

    else if (
        strpos($reason_lower, 'reservation') !== false ||
        strpos($reason_lower, 'usage') !== false ||
        strpos($reason_lower, 'used') !== false ||
        strpos($reason_lower, 'damage') !== false ||
        strpos($reason_lower, 'damaged') !== false ||
        strpos($reason_lower, 'expired') !== false ||
        strpos($reason_lower, 'release') !== false ||
        strpos($reason_lower, 'released') !== false ||
        strpos($reason_lower, 'sold') !== false ||
        strpos($reason_lower, 'delivery') !== false
    ) {

        $movement = 'Stock Out';

    }

}


/*
|--------------------------------------------------------------------------
| FINAL FALLBACK
|--------------------------------------------------------------------------
*/

if ($movement == '') {

    $movement = 'Stock In';

}

                $quantity = isset($row[$supply_quantity]) ?
                    number_value($row[$supply_quantity]) :
                    0;

                $summary['card1'] += ($movement == 'Stock In') ? 1 : 0;
                $summary['card2'] += ($movement == 'Stock Out') ? 1 : 0;

                $rows[] = array(
                    'date' => ($supply_date != '' && isset($row[$supply_date])) ?
                        $row[$supply_date] : '',
                    'category' => ($supply_category != '' && isset($row[$supply_category])) ?
                        $row[$supply_category] : 'Supplies',
                    'item' => ($supply_item != '' && isset($row[$supply_item])) ?
                        $row[$supply_item] : 'Supply',
                    'type' => $movement,
                    'quantity' => $quantity,
                    'unit' => ($supply_unit != '' && isset($row[$supply_unit])) ?
                        $row[$supply_unit] : 'Pieces',
                    'reason' => ($supply_reason != '' && isset($row[$supply_reason])) ?
                        $row[$supply_reason] : '-',
                    'recorded' => 'Manager'
                );
            }

            $result->free();
        }
    }

    /*
    |--------------------------------------------------------------------------
    | ITEMS AFFECTED
    |--------------------------------------------------------------------------
    */

    $unique_items = array();

    $i = 0;

    while ($i < count($rows)) {

        $item_key = strtolower(
            trim(
                $rows[$i]['category'] . '|' . $rows[$i]['item']
            )
        );

        if ($item_key != '|') {
            $unique_items[$item_key] = true;
        }

        $i++;
    }

    $summary['card3'] = count($unique_items);

    /*
    |--------------------------------------------------------------------------
    | SORT
    |--------------------------------------------------------------------------
    */

    usort($rows, 'sort_inventory_rows');

}

/*
|--------------------------------------------------------------------------
| RESERVATION REPORT
|--------------------------------------------------------------------------
*/

if ($report_type == 'reservation') {

    $summary_labels['card1'] = 'Total Reservations';
    $summary_labels['card2'] = 'Confirmed';
    $summary_labels['card3'] = 'Pending';
    $summary_labels['card4'] = 'Cancelled / Rejected';
    $summary_labels['card5'] = 'Total Trays Reserved';

    $summary_icons['card1'] = 'calendar';
    $summary_icons['card2'] = 'check';
    $summary_icons['card3'] = 'clock';
    $summary_icons['card4'] = 'x';
    $summary_icons['card5'] = 'package';

    if (count($reservation_columns) > 0 && $res_date != '') {

        $sql = "SELECT * FROM `reservations`
                WHERE DATE(`" . $res_date . "`) >= '" . $conn->real_escape_string($date_from) . "'
                AND DATE(`" . $res_date . "`) <= '" . $conn->real_escape_string($date_to) . "'";

        if ($search != '') {

            $search_safe = $conn->real_escape_string($search);

            $conditions = array();

            if ($res_code != '') {
                $conditions[] = "`" . $res_code . "` LIKE '%" . $search_safe . "%'";
            }

            if ($res_customer != '') {
                $conditions[] = "`" . $res_customer . "` LIKE '%" . $search_safe . "%'";
            }

            if ($res_egg != '') {
                $conditions[] = "`" . $res_egg . "` LIKE '%" . $search_safe . "%'";
            }

            if ($res_status != '') {
                $conditions[] = "`" . $res_status . "` LIKE '%" . $search_safe . "%'";
            }

            if (count($conditions) > 0) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }

        $sql .= " ORDER BY `" . $res_date . "` DESC";

        $result = $conn->query($sql);

        if ($result) {

            $reservation_codes = array();

            while ($row = $result->fetch_assoc()) {

                $code = ($res_code != '' && isset($row[$res_code])) ?
                    $row[$res_code] : '';

                $customer = ($res_customer != '' && isset($row[$res_customer])) ?
                    $row[$res_customer] : 'Customer';

                $egg = ($res_egg != '' && isset($row[$res_egg])) ?
                    $row[$res_egg] : 'Eggs';

                $quantity = ($res_quantity != '' && isset($row[$res_quantity])) ?
                    number_value($row[$res_quantity]) :
                    0;

                $status = ($res_status != '' && isset($row[$res_status])) ?
                    $row[$res_status] : 'Pending';

                $status_clean = strtolower(trim($status));

                $rows[] = array(
                    'date' => isset($row[$res_date]) ? $row[$res_date] : '',
                    'code' => $code,
                    'customer' => $customer,
                    'egg' => $egg,
                    'quantity' => $quantity,
                    'status' => $status,
                    'delivery' => ($res_delivery != '' && isset($row[$res_delivery])) ?
                        $row[$res_delivery] : '-'
                );

                /*
                |------------------------------------------------------------------
                | Count by unique reservation code if available.
                |------------------------------------------------------------------
                */

                if ($code != '') {

                    if (!isset($reservation_codes[$code])) {
                        $reservation_codes[$code] = array(
                            'quantity' => 0,
                            'status' => $status
                        );
                    }

                    $reservation_codes[$code]['quantity'] += $quantity;
                    $reservation_codes[$code]['status'] = $status;
                } else {

                    $generated_key = md5(
                        ($customer . '|' .
                         $egg . '|' .
                         $quantity . '|' .
                         (isset($row[$res_date]) ? $row[$res_date] : ''))
                    );

                    if (!isset($reservation_codes[$generated_key])) {
                        $reservation_codes[$generated_key] = array(
                            'quantity' => $quantity,
                            'status' => $status
                        );
                    }
                }
            }

            $result->free();

            foreach ($reservation_codes as $reservation_data) {

                $summary['card1']++;

                $reservation_status =
                    strtolower(trim($reservation_data['status']));

                if ($reservation_status == 'confirmed' ||
                    $reservation_status == 'completed') {

                    $summary['card2']++;

                } else if ($reservation_status == 'pending') {

                    $summary['card3']++;

                } else if ($reservation_status == 'cancelled' ||
                           $reservation_status == 'canceled' ||
                           $reservation_status == 'rejected') {

                    $summary['card4']++;
                }

                $summary['card5'] += number_value(
                    $reservation_data['quantity']
                );
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| DELIVERY REPORT
|--------------------------------------------------------------------------
*/

if ($report_type == 'delivery') {

    $summary_labels['card1'] = 'Total Deliveries';
    $summary_labels['card2'] = 'Scheduled';
    $summary_labels['card3'] = 'Out for Delivery';
    $summary_labels['card4'] = 'Delivered';
    $summary_labels['card5'] = 'Failed / Cancelled';

    $summary_icons['card1'] = 'truck';
    $summary_icons['card2'] = 'calendar';
    $summary_icons['card3'] = 'truck';
    $summary_icons['card4'] = 'check';
    $summary_icons['card5'] = 'x';

    /*
    |--------------------------------------------------------------------------
    | Delivery records are based on reservations.
    |--------------------------------------------------------------------------
    */

    if (count($reservation_columns) > 0 && $res_date != '') {

        $sql = "SELECT * FROM `reservations`
                WHERE DATE(`" . $res_date . "`) >= '" . $conn->real_escape_string($date_from) . "'
                AND DATE(`" . $res_date . "`) <= '" . $conn->real_escape_string($date_to) . "'";

        /*
        |--------------------------------------------------------------------------
        | Only delivery reservations.
        |--------------------------------------------------------------------------
        */

        if ($res_delivery != '') {
            $sql .= " AND LOWER(`" . $res_delivery . "`) = 'delivery'";
        }

        if ($search != '') {

            $search_safe = $conn->real_escape_string($search);

            $conditions = array();

            if ($res_code != '') {
                $conditions[] = "`" . $res_code . "` LIKE '%" . $search_safe . "%'";
            }

            if ($res_customer != '') {
                $conditions[] = "`" . $res_customer . "` LIKE '%" . $search_safe . "%'";
            }

            if ($res_status != '') {
                $conditions[] = "`" . $res_status . "` LIKE '%" . $search_safe . "%'";
            }

            if (count($conditions) > 0) {
                $sql .= " AND (" . implode(" OR ", $conditions) . ")";
            }
        }

        $sql .= " ORDER BY `" . $res_date . "` DESC";

        $result = $conn->query($sql);

        if ($result) {

            while ($row = $result->fetch_assoc()) {

                $status = ($res_status != '' && isset($row[$res_status])) ?
                    $row[$res_status] : 'Scheduled';

                $status_lower = strtolower(trim($status));

                /*
                |--------------------------------------------------------------------------
                | Do not count cancelled/rejected as active deliveries.
                |--------------------------------------------------------------------------
                */

                if ($status_lower == 'cancelled' ||
                    $status_lower == 'canceled' ||
                    $status_lower == 'rejected') {

                    $summary['card5']++;
                } else if ($status_lower == 'delivered' ||
                           $status_lower == 'completed') {

                    $summary['card4']++;
                } else if ($status_lower == 'out for delivery' ||
                           $status_lower == 'out_for_delivery') {

                    $summary['card3']++;
                } else {

                    $summary['card2']++;
                }

                $summary['card1']++;

                $rows[] = array(
                    'date' => isset($row[$res_date]) ?
                        $row[$res_date] : '',
                    'code' => ($res_code != '' && isset($row[$res_code])) ?
                        $row[$res_code] : '-',
                    'customer' => ($res_customer != '' && isset($row[$res_customer])) ?
                        $row[$res_customer] : 'Customer',
                    'quantity' => ($res_quantity != '' && isset($row[$res_quantity])) ?
                        number_value($row[$res_quantity]) : 0,
                    'status' => $status,
                    'method' => ($res_delivery != '' && isset($row[$res_delivery])) ?
                        $row[$res_delivery] : 'Delivery'
                );
            }

            $result->free();
        }
    }
}

/*
|--------------------------------------------------------------------------
| SORTING FUNCTIONS
|--------------------------------------------------------------------------
*/

function sort_inventory_rows($a, $b)
{
    $ta = strtotime($a['date']);
    $tb = strtotime($b['date']);

    if ($ta == $tb) {
        return 0;
    }

    return ($ta > $tb) ? -1 : 1;
}

/*
|--------------------------------------------------------------------------
| PAGINATION
|--------------------------------------------------------------------------
*/

$total_rows = count($rows);

$total_pages = ceil($total_rows / $per_page);

if ($total_pages < 1) {
    $total_pages = 1;
}

if ($page > $total_pages) {
    $page = $total_pages;
}

$offset = ($page - 1) * $per_page;

$display_rows = array_slice($rows, $offset, $per_page);

/*
|--------------------------------------------------------------------------
| EXPORT CSV
|--------------------------------------------------------------------------
*/

if (isset($_GET['export']) && $_GET['export'] == 'csv') {

    $filename = 'manager_' . $report_type . '_report_' .
                date('Y-m-d_H-i-s') . '.csv';

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    if ($report_type == 'inventory') {

        fputcsv($output, array(
            'Date & Time',
            'Item Category',
            'Item Name / Description',
            'Type',
            'Quantity',
            'Unit',
            'Reason / Notes',
            'Recorded By'
        ));

        foreach ($rows as $row) {

            fputcsv($output, array(
                $row['date'],
                $row['category'],
                $row['item'],
                $row['type'],
                $row['quantity'],
                $row['unit'],
                $row['reason'],
                $row['recorded']
            ));
        }

    } else if ($report_type == 'reservation') {

        fputcsv($output, array(
            'Date',
            'Reservation ID',
            'Customer',
            'Egg Type',
            'Quantity',
            'Status',
            'Delivery Method'
        ));

        foreach ($rows as $row) {

            fputcsv($output, array(
                $row['date'],
                $row['code'],
                $row['customer'],
                $row['egg'],
                $row['quantity'],
                $row['status'],
                $row['delivery']
            ));
        }

    } else {

        fputcsv($output, array(
            'Delivery Date',
            'Reservation ID',
            'Customer',
            'Quantity',
            'Status',
            'Method'
        ));

        foreach ($rows as $row) {

            fputcsv($output, array(
                $row['date'],
                $row['code'],
                $row['customer'],
                $row['quantity'],
                $row['status'],
                $row['method']
            ));
        }
    }

    fclose($output);
    exit;
}

/*
|--------------------------------------------------------------------------
| URL HELPER
|--------------------------------------------------------------------------
*/

function report_url($type, $from, $to, $search, $page)
{
    return '?report_type=' . urlencode($type) .
           '&date_from=' . urlencode($from) .
           '&date_to=' . urlencode($to) .
           '&search=' . urlencode($search) .
           '&page=' . (int)$page;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>

<meta charset="UTF-8">

<meta name="viewport"
      content="width=device-width, initial-scale=1.0">

<title>Reports | Manager</title>

<style>

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;
    font-family: Arial, Helvetica, sans-serif;
    background: #f7f8fa;
    color: #263238;
    font-size: 13px;
}

body {
    min-height: 100vh;
}
.page-wrapper {
    width: 100%;
    background: #ffffff;
    padding: 22px 28px 28px;
    border: 1px solid #e3e8e5;
    border-top: 0;
    border-radius: 0 0 12px 12px;
    box-shadow: 0 2px 8px rgba(31, 45, 39, 0.05);
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
/*
|--------------------------------------------------------------------------
| REPORTS OUTER CARD
|--------------------------------------------------------------------------
*/
.reports-outer-card {
    width: 100%;
    background: transparent;
    border: 0;
    border-radius: 0;
    box-shadow: none;
    overflow: visible;
}
/* ============================================================
   REPORTS TOP HEADER
   SAME AS RESERVATION HEADER
   ============================================================ */

.reports-top-header {
    width: 100%;
    min-height: 86px;
    padding: 0 28px;

    display: flex;
    align-items: center;
    justify-content: space-between;

    background: #f7f6f2;

    border: 1px solid #dddcd6;
    border-radius: 28px 28px 0 0;

    box-sizing: border-box;

    position: relative;
    z-index: 30;
}


/* ============================================================
   TITLE
   ============================================================ */

.reports-heading {
    display: flex;
    align-items: center;

    margin: 0;
}

.reports-heading h1 {
    margin: 0;

   font-size: 1.35rem;
line-height: 1;
font-weight: 700;

    color: #294d5b;

    letter-spacing: -0.7px;
}


/* ============================================================
   RIGHT SIDE
   ============================================================ */

.reports-header-right {
    display: flex;

    align-items: center;

    height: 100%;

    gap: 18px;
}


/* ============================================================
   NOTIFICATION
   ============================================================ */

.notification-container {
    position: relative;

    display: flex;
    align-items: center;
}

.notification-button {
    position: relative;

    width: 40px;
    height: 40px;

    padding: 0;

    border: 0;

    background: transparent;

    color: #53716f;

    display: flex;
    align-items: center;
    justify-content: center;

    cursor: pointer;
}

.notification-icon {
    display: flex;

    align-items: center;
    justify-content: center;
}

.notification-icon svg {
    width: 25px;
    height: 25px;
}


/* ============================================================
   NOTIFICATION NUMBER
   ============================================================ */

.notification-count {
    position: absolute;

   top: 1px;
right: 0;

min-width: 17px;
height: 17px;

font-size: 0.58rem;
    padding: 0 4px;

    border-radius: 50%;

    background: #4f8565;

    color: #ffffff;

    border: 0;

    display: flex;
    align-items: center;
    justify-content: center;


    line-height: 17px;

    font-weight: 700;

    box-sizing: border-box;
}
/* =========================================================
   NOTIFICATION DROPDOWN
   SAME STYLE AS RESERVATION HEADER
   ========================================================= */

.notification-dropdown {
    display: none;

    position: absolute;

    top: calc(100% + 12px);

    right: -8px;

    width: 320px;

    background: #ffffff;

    border: 1px solid #dddcd6;

    border-radius: 14px;

    box-shadow:
        0 12px 30px rgba(39, 49, 42, 0.12);

    overflow: hidden;

    z-index: 1000;
}

.notification-dropdown.show {
    display: block;
}


/* =========================================================
   NOTIFICATION DROPDOWN HEADER
   ========================================================= */

.notification-dropdown-header {
    padding: 14px 16px;

    display: flex;

    align-items: center;

    justify-content: space-between;

    border-bottom: 1px solid #ecebe6;

    background: #f7f6f2;
}

.notification-dropdown-header strong {
    font-size: 13px;

    font-weight: 700;

    color: #294d5b;
}

.notification-dropdown-header span {
    font-size: 11px;

    color: #7c8586;
}


/* =========================================================
   NOTIFICATION LIST
   ========================================================= */

.notification-list {
    max-height: 320px;

    overflow-y: auto;
}


/* =========================================================
   NOTIFICATION ITEM
   ========================================================= */

.notification-item {
    display: flex;

    align-items: flex-start;

    gap: 11px;

    padding: 13px 15px;

    border-bottom: 1px solid #f0f1ee;

    background: #ffffff;
}

.notification-item:last-child {
    border-bottom: 0;
}

.notification-item:hover {
    background: #f8faf8;
}


/* =========================================================
   NOTIFICATION ITEM ICON
   ========================================================= */

.notification-item-icon {
    width: 30px;
    height: 30px;

    min-width: 30px;

    border-radius: 50%;

    background: #eef5ef;

    color: #4f8565;

    display: flex;

    align-items: center;
    justify-content: center;
}

.notification-item-icon svg {
    width: 15px;
    height: 15px;
}


/* =========================================================
   NOTIFICATION ITEM CONTENT
   ========================================================= */

.notification-item-content {
    min-width: 0;

    display: flex;

    flex-direction: column;
}

.notification-item-content strong {
    font-size: 11px;

    line-height: 1.3;

    font-weight: 700;

    color: #294d5b;
}

.notification-item-content span {
    margin-top: 3px;

    font-size: 11px;

    line-height: 1.3;

    color: #4f5955;
}

.notification-item-content small {
    margin-top: 3px;

    font-size: 9px;

    line-height: 1.3;

    color: #858d8a;
}


/* =========================================================
   EMPTY NOTIFICATION
   ========================================================= */

.notification-empty {
    min-height: 130px;

    padding: 25px 18px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #8c9491;
}

.notification-empty svg {
    margin-bottom: 8px;
}

.notification-empty strong {
    font-size: 11px;

    font-weight: 700;

    color: #68716e;
}

.notification-empty span {
    margin-top: 4px;

    font-size: 10px;

    color: #8c9491;
}

/* ============================================================
   VERTICAL DIVIDER
   ============================================================ */

.reports-header-divider {
    width: 1px;

    height: 42px;

    background: #dddcd6;

    flex-shrink: 0;
}


/* ============================================================
   MANAGER ACCOUNT
   ============================================================ */

.manager-account-container {
    position: relative;

    display: flex;

    align-items: center;

    gap: 11px;
    min-width: 230px;
}


/* ============================================================
   CIRCULAR USER ICON
   ============================================================ */

.manager-avatar {
   width: 54px;
height: 54px;
min-width: 54px;
    border-radius: 50%;

    background: #eef2ed;

    border: 1px solid #dce1dc;

    display: flex;

    align-items: center;
    justify-content: center;

    box-shadow:
        0 2px 5px rgba(40, 65, 48, 0.04);

    box-sizing: border-box;
}

.manager-avatar svg {
    width: 25px;
    height: 25px;
}


/* ============================================================
   MANAGER INFORMATION
   ============================================================ */

.manager-account-info {
    min-width: 120px;

    display: flex;

    flex-direction: column;

    justify-content: center;
}

.manager-account-info strong {
    margin: 0;

   font-size: 0.88rem;
line-height: 1.2;
font-weight: 700;
    color: #243f4d;
}

.manager-account-info span {
    display: block;

    line-height: 1.25;
}

.manager-account-info .manager-date {
    margin-top: 5px;

    font-size:0.63rem;

    color: #7c8586;
}

.manager-account-info .manager-time {
    margin-top: 2px;

   font-size: 0.63rem;
font-weight: 600;

    color: #2f6d4c;
}


/* ============================================================
   ACCOUNT ARROW
   ============================================================ */

.manager-account-arrow {
  width: 28px;

font-size: 0.7rem;
    padding: 0;

    margin-left: 2px;

    border: 0;

    background: transparent;

    color: #55716d;

    display: flex;

    align-items: center;
    justify-content: center;

    cursor: pointer;


    font-weight: 400;
}

.manager-account-arrow span {
    display: block;

    transform: translateY(-2px);
}


/* ============================================================
   MANAGER DROPDOWN
   ============================================================ */

.manager-account-dropdown {
    display: none;

    position: absolute;

    top: calc(100% + 12px);

    right: 0;

    width: 220px;

    padding: 10px 0;

    background: #ffffff;

    border: 1px solid #dddcd6;

    border-radius: 14px;

    box-shadow:
        0 12px 30px rgba(39, 49, 42, 0.12);

    z-index: 100;
}

.manager-account-dropdown.show {
    display: block;
}


/* ============================================================
   DROPDOWN HEADER
   ============================================================ */

.manager-dropdown-header {
    display: flex;

    align-items: center;

    gap: 10px;

    padding: 10px 14px;
}

.dropdown-avatar {
    width: 38px;
    height: 38px;

    min-width: 38px;

    border-radius: 50%;

    background: #eef2ed;

    border: 1px solid #dce1dc;

    display: flex;

    align-items: center;
    justify-content: center;
}

.manager-dropdown-header > div:last-child {
    display: flex;

    flex-direction: column;
}

.manager-dropdown-header strong {
    font-size: 11px;

    color: #294d5b;

    font-weight: 700;
}

.manager-dropdown-header span {
    margin-top: 2px;

    font-size: 9px;

    color: #858b88;
}


/* ============================================================
   DROPDOWN DIVIDER
   ============================================================ */

.manager-dropdown-divider {
    height: 1px;

    margin: 6px 0;

    background: #ecebe6;
}


/* ============================================================
   DROPDOWN LINKS
   ============================================================ */

.manager-account-dropdown a {
    display: block;

    padding: 10px 14px;

    color: #555c59;

    text-decoration: none;

    font-size: 11px;

    font-weight: 500;
}

.manager-account-dropdown a:hover {
    background: #f4f5f1;

    color: #294d5b;
}
.page-header {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    margin-bottom: 14px;
}

.page-title-area {
    display: flex;
    align-items: flex-start;
    gap: 10px;
}

.page-title-icon {
    width: 34px;
    height: 34px;
    display: flex;
    align-items: center;
    justify-content: center;
    color: #394b45;
}

.page-title-icon svg {
    width: 27px;
    height: 27px;
}

.page-title {
    margin: 0;
    font-size: 22px;
    font-weight: 600;
    color: #29322f;
    line-height: 1.2;
}

.page-subtitle {
    margin-top: 4px;
    color: #7b8582;
    font-size: 11px;
}

.breadcrumb {
    font-size: 10px;
    color: #8a9390;
    padding-top: 4px;
}

.breadcrumb .home {
    color: #2f6d4f;
}

.breadcrumb .separator {
    padding: 0 7px;
    color: #c2c7c5;
}

.main-card {
    background: #ffffff;
    border: 1px solid #e4e8e6;
    border-radius: 8px;
    box-shadow: 0 1px 4px rgba(31, 45, 39, 0.04);
}

/*
|--------------------------------------------------------------------------
| FILTER CARD
|--------------------------------------------------------------------------
*/

.filter-card {
    padding: 14px 13px;
    margin-bottom: 10px;
}

.filter-layout {
    display: grid;
    grid-template-columns: 270px 1fr 125px;
    gap: 18px;
    align-items: center;
}

.filter-section {
    min-height: 70px;
}

.filter-section.border-right {
    border-right: 1px solid #edf0ee;
    padding-right: 18px;
}

.filter-label {
    font-size: 11px;
    font-weight: 600;
    color: #555e5b;
    margin-bottom: 8px;
}

.report-types {
    display: flex;
    gap: 8px;
}

.report-type-button { 
    width: 105px; 
    height: 58px; 
    background: #ffffff; 
    border: 1px solid #e2e6e4; 
    border-radius: 6px; 
    color: #66706d; 
    cursor: pointer; 
    transition: all .15s ease; 
    text-align: center; 
    padding: 6px 5px; 
}

.report-type-button:hover {
    border-color: #75a48c;
}

.report-type-button.active {
    border-color: #6aa384;
    background: #f4faf6;
    color: #347450;
}

.report-type-button svg { 
    display: block; 
    margin: 0 auto 5px auto; 
    width: 18px; 
    height: 18px; 
}
.report-type-button span { 
    display: block; 
    font-size: 10px; 
    line-height: 1.2; 
}

.date-area {
    display: flex;
    align-items: flex-end;
    gap: 8px;
}

.date-field {
    flex: 1;
}

.date-field label {
    display: block;
    font-size: 10px;
    color: #727b78;
    margin-bottom: 5px;
}

.date-input {
    width: 100%;
    height: 34px;
    border: 1px solid #dfe4e2;
    border-radius: 4px;
    padding: 0 9px;
    font-size: 11px;
    color: #3f4845;
    background: #ffffff;
    outline: none;
}

.date-input:focus {
    border-color: #6ea184;
}

.date-separator {
    height: 30px;
    display: flex;
    align-items: center;
    color: #a4aaa8;
    font-size: 11px;
}

.quick-range {
    width: 100%;
    height: 34px;
    border: 1px solid #dfe4e2;
    border-radius: 4px;
    background: #fff;
    font-size: 11px;
    color: #505956;
    padding: 0 7px;
    outline: none;
}

.quick-range:focus {
    border-color: #6ea184;
}

.action-row {
    display: flex;
    justify-content: flex-end;
    gap: 8px;
    margin-top: 8px;
}

.btn {
    height: 34px;
    border-radius: 4px;
    border: 1px solid #d9dfdc;
    background: #ffffff;
    padding: 0 14px;
    font-size: 11px;
    cursor: pointer;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 6px;
}

.btn svg {
    width: 13px;
    height: 13px;
}

.btn-primary {
    background: #2f6d4c;
    border-color: #2f6d4c;
    color: #ffffff;
}

.btn-primary:hover {
    background: #285f42;
}

.btn-secondary {
    color: #59635f;
}

.btn-secondary:hover {
    background: #f7f9f8;
}

/*
|--------------------------------------------------------------------------
| SUMMARY CARDS
|--------------------------------------------------------------------------
*/

.summary-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 9px;
    margin-bottom: 10px;
}

.summary-card {
    background: #ffffff;
    border: 1px solid #e6eae8;
    border-radius: 7px;
    min-height: 64px;
    padding: 10px;
    display: flex;
    align-items: center;
    gap: 9px;
    box-shadow: 0 1px 3px rgba(31, 45, 39, 0.025);
}

.summary-icon {
    width: 31px;
    height: 31px;
    min-width: 31px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}

.summary-icon svg {
    width: 16px;
    height: 16px;
}

.summary-icon.green {
    background: #edf8f1;
    color: #4b9568;
}

.summary-icon.red {
    background: #fff0f0;
    color: #e85c5c;
}

.summary-icon.blue {
    background: #eef5fb;
    color: #3e76a8;
}

.summary-content {
    min-width: 0;
}

.summary-label {
    font-size: 10px;
    color: #69726f;
    line-height: 1.25;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.summary-value {
    margin-top: 3px;
    font-size: 18px;
    font-weight: 600;
    color: #35403b;
    line-height: 1.1;
}

.summary-unit {
    font-size: 10px;
    color: #8a9290;
    margin-left: 2px;
}

/*
|--------------------------------------------------------------------------
| REPORT CARD
|--------------------------------------------------------------------------
*/

.report-card {
    overflow: hidden;
}

.report-header {
    min-height: 53px;
    padding: 10px 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 1px solid #edf0ef;
}

.report-title-area {
    display: flex;
    align-items: center;
    gap: 9px;
}

.report-icon {
    width: 24px;
    height: 24px;
    color: #3c7857;
}

.report-icon svg {
    width: 22px;
    height: 22px;
}

.report-title {
    font-size: 14px;
    font-weight: 600;
    color: #3c4642;
}

.report-range {
    margin-top: 3px;
    font-size: 10px;
    color: #8a9290;
}

.report-tools {
    display: flex;
    align-items: center;
    gap: 8px;
}

.search-box {
    width: 170px;
    height: 28px;
    position: relative;
}

.search-box input {
    width: 100%;
    height: 100%;
    border: 1px solid #e0e5e3;
    border-radius: 4px;
    padding: 0 9px 0 28px;
    font-size: 11px;
    color: #4c5552;
    outline: none;
}

.search-box svg {
    position: absolute;
    left: 9px;
    top: 7px;
    width: 13px;
    height: 13px;
    color: #929b98;
}

.export-button {
    height: 32px;
    border: 1px solid #e0e5e3;
    background: #fff;
    border-radius: 4px;
    padding: 0 8px;
    font-size: 10px;
    color: #59635f;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 5px;
}

.export-button svg {
    width: 12px;
    height: 12px;
}

.export-button:hover {
    background: #f8faf9;
}

/*
|--------------------------------------------------------------------------
| TABLE
|--------------------------------------------------------------------------
*/

.table-wrapper {
    width: 100%;
    overflow-x: auto;
}

.report-table {
    width: 100%;
    border-collapse: collapse;
    table-layout: fixed;
}

.report-table th {
    height: 31px;
    background: #edf5ef;
    color: #52605a;
    font-size: 11px;
    font-weight: 600;
    text-align: left;
    padding: 0 8px;
    border-bottom: 1px solid #dce6df;
    white-space: nowrap;
}

.report-table td {
    height: 36px;
    padding: 0 10px;
    font-size: 11px;
    color: #59625f;
    border-bottom: 1px solid #edf0ef;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.report-table tr:hover td {
    background: #fafcfb;
}

/*
|--------------------------------------------------------------------------
| INVENTORY TABLE WIDTHS
|--------------------------------------------------------------------------
*/

.inventory-table th:nth-child(1),
.inventory-table td:nth-child(1) {
    width: 15%;
}

.inventory-table th:nth-child(2),
.inventory-table td:nth-child(2) {
    width: 10%;
}

.inventory-table th:nth-child(3),
.inventory-table td:nth-child(3) {
    width: 17%;
}

.inventory-table th:nth-child(4),
.inventory-table td:nth-child(4) {
    width: 10%;
}

.inventory-table th:nth-child(5),
.inventory-table td:nth-child(5) {
    width: 9%;
    text-align: center;
}

.inventory-table th:nth-child(6),
.inventory-table td:nth-child(6) {
    width: 9%;
}

.inventory-table th:nth-child(7),
.inventory-table td:nth-child(7) {
    width: 20%;
}

.inventory-table th:nth-child(8),
.inventory-table td:nth-child(8) {
    width: 10%;
}

/*
|--------------------------------------------------------------------------
| RESERVATION TABLE
|--------------------------------------------------------------------------
*/

.reservation-table th:nth-child(1),
.reservation-table td:nth-child(1) {
    width: 14%;
}

.reservation-table th:nth-child(2),
.reservation-table td:nth-child(2) {
    width: 18%;
}

.reservation-table th:nth-child(3),
.reservation-table td:nth-child(3) {
    width: 20%;
}

.reservation-table th:nth-child(4),
.reservation-table td:nth-child(4) {
    width: 16%;
}

.reservation-table th:nth-child(5),
.reservation-table td:nth-child(5) {
    width: 12%;
    text-align: center;
}

.reservation-table th:nth-child(6),
.reservation-table td:nth-child(6) {
    width: 20%;
}

/*
|--------------------------------------------------------------------------
| DELIVERY TABLE
|--------------------------------------------------------------------------
*/

.delivery-table th:nth-child(1),
.delivery-table td:nth-child(1) {
    width: 18%;
}

.delivery-table th:nth-child(2),
.delivery-table td:nth-child(2) {
    width: 19%;
}

.delivery-table th:nth-child(3),
.delivery-table td:nth-child(3) {
    width: 24%;
}

.delivery-table th:nth-child(4),
.delivery-table td:nth-child(4) {
    width: 12%;
    text-align: center;
}

.delivery-table th:nth-child(5),
.delivery-table td:nth-child(5) {
    width: 15%;
}

.delivery-table th:nth-child(6),
.delivery-table td:nth-child(6) {
    width: 12%;
}

/*
|--------------------------------------------------------------------------
| BADGES
|--------------------------------------------------------------------------
*/

.badge {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 60px;
    height: 22px;
    padding: 0 8px;
    border-radius: 4px;
    font-size: 10px;
    font-weight: 600;
}

.badge-in {
    background: #eaf7ef;
    color: #398054;
}

.badge-out {
    background: #fff0f0;
    color: #d64d4d;
}

.badge-confirmed {
    background: #eaf7ef;
    color: #397b51;
}

.badge-pending {
    background: #fff7e9;
    color: #b78331;
}

.badge-cancelled {
    background: #fff0f0;
    color: #c74f4f;
}

.badge-delivered {
    background: #eaf7ef;
    color: #397b51;
}

.badge-scheduled {
    background: #eef5fb;
    color: #4c7297;
}

.badge-outdelivery {
    background: #fff7e9;
    color: #aa7a2d;
}

.badge-failed {
    background: #fff0f0;
    color: #c74f4f;
}

.badge-default {
    background: #f1f3f2;
    color: #68716e;
}

/*
|--------------------------------------------------------------------------
| EMPTY STATE
|--------------------------------------------------------------------------
*/

.empty-state {
    padding: 38px 20px;
    text-align: center;
    color: #8c9491;
}

.empty-icon {
    margin-bottom: 8px;
    color: #b4bdb9;
}

.empty-icon svg {
    width: 30px;
    height: 30px;
}

.empty-title {
    font-size: 11px;
    font-weight: 600;
    color: #68716e;
}

.empty-text {
    margin-top: 5px;
    font-size: 10px;
}

/*
|--------------------------------------------------------------------------
| FOOTER / PAGINATION
|--------------------------------------------------------------------------
*/

.report-footer {
    min-height: 43px;
    padding: 8px 13px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-top: 1px solid #edf0ef;
}

.showing-text {
    font-size: 10px;
    color: #7d8683;
}

.pagination {
    display: flex;
    align-items: center;
    gap: 5px;
}

.page-btn {
    min-width: 34px;
    height: 30px;
    border: 1px solid #e2e6e4;
    border-radius: 4px;
    background: #ffffff;
    color: #68716e;
    font-size: 10px;
    cursor: pointer;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    justify-content: center;
}

.page-btn:hover {
    background: #f7faf8;
}

.page-btn.disabled {
    color: #c0c6c3;
    background: #fbfcfc;
    pointer-events: none;
}

.page-number.active {
    background: #2f6d4c;
    border-color: #2f6d4c;
    color: #ffffff;
}

.page-number {
    width: 25px;
    min-width: 25px;
}

/*
|--------------------------------------------------------------------------
| RESPONSIVE
|--------------------------------------------------------------------------
*/

@media (max-width: 1000px) {

    .filter-layout {
        grid-template-columns: 1fr;
        gap: 12px;
    }

    .filter-section.border-right {
        border-right: 0;
        border-bottom: 1px solid #edf0ee;
        padding-right: 0;
        padding-bottom: 12px;
    }

    .summary-grid {
        grid-template-columns: repeat(2, 1fr);
    }
}

@media (max-width: 650px) {

    .page-wrapper {
        padding: 12px 8px;
    }

    .page-header {
        display: block;
    }

    .breadcrumb {
        margin-top: 8px;
    }

    .summary-grid {
        grid-template-columns: 1fr;
    }

    .date-area {
        display: block;
    }

    .date-separator {
        display: none;
    }

    .date-field {
        margin-bottom: 8px;
    }

    .report-header {
        display: block;
    }

    .report-tools {
        margin-top: 9px;
    }

    .search-box {
        width: 100%;
    }


    .reports-top-header {
        min-height: auto;

        padding: 16px 18px;

        align-items: flex-start;
    }

    .reports-heading h1 {
        font-size: 20px;
    }

    .reports-heading p {
        font-size: 10px;
    }

    .reports-header-right {
        margin-top: 12px;

        justify-content: flex-end;

        gap: 12px;
    }

    .reports-header-divider {
        height: 34px;
    }

    .notification-dropdown {
        right: -45px;

        width: 290px;
    }

    .manager-account-info strong {
        font-size: 11px;
    }

    .manager-account-info span,
    .manager-account-info .manager-date,
    .manager-account-info .manager-time {
        font-size: 9px;
    }

    .manager-avatar {
        width: 34px;
        height: 34px;

        min-width: 34px;
    }

    .notification-button {
        width: 34px;
        height: 34px;
    }

    .manager-account-arrow {
        width: 22px;
    }

}

</style>
</head>

<body class="bg-slate-100 font-sans text-gray-700 antialiased min-h-screen">

<?php include('manager_panel.php'); ?>

<div class="main-content">

    <div class="min-h-screen">

 <div class="reports-outer-card">

    <div class="reports-top-header">

        <!-- =====================================================
             REPORTS TITLE
             ===================================================== -->

        <div class="reports-heading">

            <h1>
                Reports Management
            </h1>

        </div>


        <!-- =====================================================
             HEADER RIGHT
             ===================================================== -->



        <!-- =================================================
             HEADER RIGHT
             ================================================= -->

        <div class="reports-header-right">


            <!-- =============================================
                 NOTIFICATION
                 ============================================= -->

            <div class="notification-container">

                <button
                    type="button"
                    class="notification-button"
                    id="notificationButton"
                    aria-label="Notifications"
                >

                    <span class="notification-icon">

                        <?php
                        echo icon_svg(
                            'bell',
                            25
                        );
                        ?>

                    </span>


                    <?php if ($notification_count > 0) { ?>

                        <span class="notification-count">

                            <?php
                            echo $notification_count;
                            ?>

                        </span>

                    <?php } ?>

                </button>


                <!-- =========================================
                     NOTIFICATION DROPDOWN
                     ========================================= -->

                <div
                    class="notification-dropdown"
                    id="notificationDropdown"
                >

                    <div class="notification-dropdown-header">

                        <strong>
                            Notifications
                        </strong>

                        <?php if ($notification_count > 0) { ?>

                            <span>
                                <?php echo $notification_count; ?>
                                pending
                            </span>

                        <?php } ?>

                    </div>


                    <div class="notification-list">

                        <?php if (count($notifications) > 0) { ?>

                            <?php foreach ($notifications as $notification) { ?>

                                <div class="notification-item">

                                    <div class="notification-item-icon">

                                        <?php
                                        echo icon_svg(
                                            'calendar',
                                            15
                                        );
                                        ?>

                                    </div>


                                    <div class="notification-item-content">

                                        <strong>
                                            New Reservation
                                        </strong>

                                        <span>
                                            <?php
                                            echo h(
                                                $notification['customer']
                                            );
                                            ?>
                                        </span>

                                        <small>
                                            <?php
                                            echo h(
                                                $notification['code']
                                            );
                                            ?>
                                        </small>

                                        <small>
                                            <?php
                                            echo format_date_time(
                                                $notification['date']
                                            );
                                            ?>
                                        </small>

                                    </div>

                                </div>

                            <?php } ?>

                        <?php } else { ?>

                            <div class="notification-empty">

                                <?php
                                echo icon_svg(
                                    'check',
                                    24
                                );
                                ?>

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


            <!-- =============================================
                 VERTICAL DIVIDER
                 ============================================= -->

            <div class="reports-header-divider"></div>


            <!-- =============================================
                 MANAGER ACCOUNT
                 ============================================= -->

            <div class="manager-account-container">


                <!-- MANAGER AVATAR -->

                <div class="manager-avatar">

                    <svg
                        width="23"
                        height="23"
                        viewBox="0 0 24 24"
                        fill="none"
                        xmlns="http://www.w3.org/2000/svg"
                    >

                        <circle
                            cx="12"
                            cy="8"
                            r="4"
                            fill="#4f8565"
                        />

                        <path
                            d="M5 20C5 16.6863 8.13401 14 12 14C15.866 14 19 16.6863 19 20"
                            fill="#4f8565"
                        />

                    </svg>

                </div>


                <!-- MANAGER DETAILS -->

                <div class="manager-account-info">

                    <strong>
                        Manager
                    </strong>

                    <span class="manager-date">

                        <?php
                        echo date(
                            'F d, Y'
                        );
                        ?>

                    </span>

                    <span
                        class="manager-time"
                        id="liveClock"
                    >

                        <?php
                        echo date(
                            'h:i:s A'
                        );
                        ?>

                    </span>

                </div>


                <!-- DROPDOWN ARROW -->

                <button
                    type="button"
                    class="manager-account-arrow"
                    id="managerAccountButton"
                    aria-label="Manager account menu"
                >

                    <span>
                        &#8964;
                    </span>

                </button>


                <!-- =========================================
                     MANAGER DROPDOWN
                     ========================================= -->

                <div
                    class="manager-account-dropdown"
                    id="managerAccountDropdown"
                >

                    <div class="manager-dropdown-header">

                        <div class="dropdown-avatar">

                            <svg
                                width="20"
                                height="20"
                                viewBox="0 0 24 24"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >

                                <circle
                                    cx="12"
                                    cy="8"
                                    r="4"
                                    fill="#4f8565"
                                />

                                <path
                                    d="M5 20C5 16.6863 8.13401 14 12 14C15.866 14 19 16.6863 19 20"
                                    fill="#4f8565"
                                />

                            </svg>

                        </div>


                        <div>

                            <strong>
                                Manager
                            </strong>

                            <span>
                                Manager
                            </span>

                        </div>

                    </div>


                    <div class="manager-dropdown-divider"></div>


                    <a href="manager_profile.php">
                        My Profile
                    </a>


                    <a href="logout.php">
                        Logout
                    </a>

                </div>

            </div>

        </div>

    </div>


            <div class="page-wrapper">



    <!-- ==============================================================
         FILTER CARD
         ============================================================== -->

    <div class="main-card filter-card">

        <form method="get"
              action=""
              id="reportForm">

            <input type="hidden"
                   name="report_type"
                   id="report_type"
                   value="<?php echo h($report_type); ?>">

            <div class="filter-layout">

                <!-- REPORT TYPE -->

                <div class="filter-section border-right">

                    <div class="filter-label">
                        Select Report Type
                    </div>

                    <div class="report-types">

                        <button type="button"
                                class="report-type-button <?php echo ($report_type == 'inventory') ? 'active' : ''; ?>"
                                data-type="inventory">

                            <?php echo icon_svg('box', 17); ?>

                            <span>
                                Inventory Report
                            </span>

                        </button>


                        <button type="button"
                                class="report-type-button <?php echo ($report_type == 'reservation') ? 'active' : ''; ?>"
                                data-type="reservation">

                            <?php echo icon_svg('calendar', 17); ?>

                            <span>
                                Reservation Report
                            </span>

                        </button>


                        <button type="button"
                                class="report-type-button <?php echo ($report_type == 'delivery') ? 'active' : ''; ?>"
                                data-type="delivery">

                            <?php echo icon_svg('truck', 17); ?>

                            <span>
                                Delivery Report
                            </span>

                        </button>

                    </div>

                </div>


                <!-- DATE RANGE -->

                <div class="filter-section">

                    <div class="filter-label">
                        Date Range
                    </div>

                    <div class="date-area">

                        <div class="date-field">

                            <label for="date_from">
                                From
                            </label>

                            <input type="date"
                                   class="date-input"
                                   id="date_from"
                                   name="date_from"
                                   value="<?php echo h($date_from); ?>">

                        </div>

                        <div class="date-separator">
                            –
                        </div>

                        <div class="date-field">

                            <label for="date_to">
                                To
                            </label>

                            <input type="date"
                                   class="date-input"
                                   id="date_to"
                                   name="date_to"
                                   value="<?php echo h($date_to); ?>">

                        </div>

                    </div>

                </div>


                <!-- QUICK RANGE -->

                <div class="filter-section">

                    <div class="filter-label">
                        Quick Range
                    </div>

                    <select class="quick-range"
                            id="quick_range">

                        <option value="">
                            Select
                        </option>

                        <option value="today">
                            Today
                        </option>

                        <option value="week">
                            This Week
                        </option>

                        <option value="month">
                            This Month
                        </option>

                        <option value="last_month">
                            Last Month
                        </option>

                        <option value="year">
                            This Year
                        </option>

                    </select>

                </div>

            </div>


            <div class="action-row">

                <button type="submit"
                        class="btn btn-primary">

                    <?php echo icon_svg('search', 13); ?>

                    Generate Report

                </button>

                <button type="button"
                        class="btn btn-secondary"
                        id="resetButton">

                    <?php echo icon_svg('refresh', 13); ?>

                    Reset

                </button>

            </div>

        </form>

    </div>


    <!-- ==============================================================
         SUMMARY CARDS
         ============================================================== -->

    <div class="summary-grid">

        <?php
        $card_keys = array(
            'card1',
            'card2',
            'card3',
            'card4',
            'card5'
        );

        foreach ($card_keys as $key) {
        ?>

            <div class="summary-card">

                <div class="summary-icon <?php echo h($summary_colors[$key]); ?>">

                    <?php echo icon_svg($summary_icons[$key], 16); ?>

                </div>

                <div class="summary-content">

                    <div class="summary-label"
                         title="<?php echo h($summary_labels[$key]); ?>">

                        <?php echo h($summary_labels[$key]); ?>

                    </div>

                    <div class="summary-value">

                        <?php
                        echo number_display($summary[$key]);

                        if ($report_type == 'reservation' &&
                            $key == 'card5') {
                        ?>

                            <span class="summary-unit">
                                trays
                            </span>

                        <?php
                        } else if ($report_type == 'inventory' &&
                                   ($key == 'card4' || $key == 'card5')) {
                        ?>

                            <span class="summary-unit">
                                quantity
                            </span>

                        <?php
                        }
                        ?>

                    </div>

                </div>

            </div>

        <?php
        }
        ?>

    </div>


    <!-- ==============================================================
         REPORT TABLE
         ============================================================== -->

    <div class="main-card report-card">

        <div class="report-header">

            <div class="report-title-area">

                <div class="report-icon">

                    <?php echo icon_svg('file', 22); ?>

                </div>

                <div>

                    <div class="report-title">

                        <?php
                        if ($report_type == 'inventory') {
                            echo 'Inventory Report';
                        } else if ($report_type == 'reservation') {
                            echo 'Reservation Report';
                        } else {
                            echo 'Delivery Report';
                        }
                        ?>

                    </div>

                    <div class="report-range">

                        From
                        <?php echo date('F d, Y', strtotime($date_from)); ?>

                        to

                        <?php echo date('F d, Y', strtotime($date_to)); ?>

                    </div>

                </div>

            </div>


            <div class="report-tools">

                <form method="get"
                      action=""
                      style="margin:0;">

                    <input type="hidden"
                           name="report_type"
                           value="<?php echo h($report_type); ?>">

                    <input type="hidden"
                           name="date_from"
                           value="<?php echo h($date_from); ?>">

                    <input type="hidden"
                           name="date_to"
                           value="<?php echo h($date_to); ?>">

                    <div class="search-box">

                        <?php echo icon_svg('search', 13); ?>

                        <input type="text"
                               name="search"
                               value="<?php echo h($search); ?>"
                               placeholder="Search report..."
                               autocomplete="off">

                    </div>

                </form>


                <a class="export-button"
                   href="<?php echo h(report_url($report_type, $date_from, $date_to, $search, 1)); ?>&export=csv">

                    <?php echo icon_svg('download', 12); ?>

                    Export

                </a>

            </div>

        </div>


        <div class="table-wrapper">

            <?php if ($report_type == 'inventory') { ?>

                <table class="report-table inventory-table">

                    <thead>

                        <tr>

                            <th>Date &amp; Time</th>

                            <th>Item Category</th>

                            <th>Item Name / Description</th>

                            <th>Type</th>

                            <th>Quantity</th>

                            <th>Unit</th>

                            <th>Reason / Notes</th>

                            <th>Recorded By</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($display_rows) > 0) { ?>

                        <?php foreach ($display_rows as $row) { ?>

                            <tr>

                                <td>
                                    <?php echo format_date_time($row['date']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['category']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['item']); ?>
                                </td>

                                <td>

                                    <?php if ($row['type'] == 'Stock In') { ?>

                                        <span class="badge badge-in">
                                            Stock In
                                        </span>

                                    <?php } else if ($row['type'] == 'Stock Out') { ?>

                                        <span class="badge badge-out">
                                            Stock Out
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge badge-default">
                                            <?php echo h($row['type']); ?>
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>
                                    <?php echo number_display($row['quantity']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['unit']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['reason']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['recorded']); ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="8">

                                <div class="empty-state">

                                    <div class="empty-icon">
                                        <?php echo icon_svg('file', 30); ?>
                                    </div>

                                    <div class="empty-title">
                                        No inventory records found
                                    </div>

                                    <div class="empty-text">
                                        Try another date range or search keyword.
                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>


            <?php } else if ($report_type == 'reservation') { ?>


                <table class="report-table reservation-table">

                    <thead>

                        <tr>

                            <th>Date</th>

                            <th>Reservation ID</th>

                            <th>Customer</th>

                            <th>Egg Type</th>

                            <th>Quantity</th>

                            <th>Status</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($display_rows) > 0) { ?>

                        <?php foreach ($display_rows as $row) { ?>

                            <tr>

                                <td>
                                    <?php echo format_date_only($row['date']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['code']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['customer']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['egg']); ?>
                                </td>

                                <td>
                                    <?php echo number_display($row['quantity']); ?>
                                    trays
                                </td>

                                <td>

                                    <?php
                                    $status_lower =
                                        strtolower(trim($row['status']));

                                    if ($status_lower == 'confirmed' ||
                                        $status_lower == 'completed') {
                                    ?>

                                        <span class="badge badge-confirmed">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php
                                    } else if ($status_lower == 'pending') {
                                    ?>

                                        <span class="badge badge-pending">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php
                                    } else if ($status_lower == 'cancelled' ||
                                               $status_lower == 'canceled' ||
                                               $status_lower == 'rejected') {
                                    ?>

                                        <span class="badge badge-cancelled">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge badge-default">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php } ?>

                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="6">

                                <div class="empty-state">

                                    <div class="empty-icon">
                                        <?php echo icon_svg('calendar', 30); ?>
                                    </div>

                                    <div class="empty-title">
                                        No reservation records found
                                    </div>

                                    <div class="empty-text">
                                        Try another date range or search keyword.
                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>


            <?php } else { ?>


                <table class="report-table delivery-table">

                    <thead>

                        <tr>

                            <th>Delivery Date</th>

                            <th>Reservation ID</th>

                            <th>Customer</th>

                            <th>Quantity</th>

                            <th>Status</th>

                            <th>Method</th>

                        </tr>

                    </thead>

                    <tbody>

                    <?php if (count($display_rows) > 0) { ?>

                        <?php foreach ($display_rows as $row) { ?>

                            <tr>

                                <td>
                                    <?php echo format_date_only($row['date']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['code']); ?>
                                </td>

                                <td>
                                    <?php echo h($row['customer']); ?>
                                </td>

                                <td>
                                    <?php echo number_display($row['quantity']); ?>
                                    trays
                                </td>

                                <td>

                                    <?php
                                    $status_lower =
                                        strtolower(trim($row['status']));

                                    if ($status_lower == 'delivered' ||
                                        $status_lower == 'completed') {
                                    ?>

                                        <span class="badge badge-delivered">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php
                                    } else if ($status_lower == 'out for delivery' ||
                                               $status_lower == 'out_for_delivery') {
                                    ?>

                                        <span class="badge badge-outdelivery">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php
                                    } else if ($status_lower == 'cancelled' ||
                                               $status_lower == 'canceled' ||
                                               $status_lower == 'failed' ||
                                               $status_lower == 'rejected') {
                                    ?>

                                        <span class="badge badge-failed">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php } else { ?>

                                        <span class="badge badge-scheduled">
                                            <?php echo h($row['status']); ?>
                                        </span>

                                    <?php } ?>

                                </td>

                                <td>
                                    <?php echo h($row['method']); ?>
                                </td>

                            </tr>

                        <?php } ?>

                    <?php } else { ?>

                        <tr>

                            <td colspan="6">

                                <div class="empty-state">

                                    <div class="empty-icon">
                                        <?php echo icon_svg('truck', 30); ?>
                                    </div>

                                    <div class="empty-title">
                                        No delivery records found
                                    </div>

                                    <div class="empty-text">
                                        Try another date range or search keyword.
                                    </div>

                                </div>

                            </td>

                        </tr>

                    <?php } ?>

                    </tbody>

                </table>


            <?php } ?>

        </div>


        <!-- ==========================================================
             REPORT FOOTER
             ========================================================== -->

        <div class="report-footer">

            <div class="showing-text">

                <?php

                if ($total_rows == 0) {

                    echo 'Showing 0 entries';

                } else {

                    $show_start = $offset + 1;

                    $show_end = $offset + count($display_rows);

                    echo 'Showing ' .
                         $show_start .
                         ' to ' .
                         $show_end .
                         ' of ' .
                         $total_rows .
                         ' entries';
                }

                ?>

            </div>


            <div class="pagination">

                <?php
                $prev_page = $page - 1;

                if ($prev_page < 1) {
                    $prev_page = 1;
                }

                ?>

                <a class="page-btn <?php echo ($page <= 1) ? 'disabled' : ''; ?>"
                   href="<?php echo h(report_url($report_type, $date_from, $date_to, $search, $prev_page)); ?>">

                    Previous

                </a>


                <?php

                /*
                |--------------------------------------------------------------------------
                | SHOW PAGE NUMBERS
                |--------------------------------------------------------------------------
                */

                $start_page = $page - 2;

                if ($start_page < 1) {
                    $start_page = 1;
                }

                $end_page = $start_page + 4;

                if ($end_page > $total_pages) {
                    $end_page = $total_pages;
                    $start_page = $end_page - 4;

                    if ($start_page < 1) {
                        $start_page = 1;
                    }
                }

                $p = $start_page;

                while ($p <= $end_page) {
                ?>

                    <a class="page-btn page-number <?php echo ($p == $page) ? 'active' : ''; ?>"
                       href="<?php echo h(report_url($report_type, $date_from, $date_to, $search, $p)); ?>">

                        <?php echo $p; ?>

                    </a>

                <?php
                    $p++;
                }

                ?>


                <?php

                $next_page = $page + 1;

                if ($next_page > $total_pages) {
                    $next_page = $total_pages;
                }

                ?>

                <a class="page-btn <?php echo ($page >= $total_pages) ? 'disabled' : ''; ?>"
                   href="<?php echo h(report_url($report_type, $date_from, $date_to, $search, $next_page)); ?>">

                    Next

                </a>

            </div>

        </div>

    </div>

</div>
 </div>

</div>


<script>

/*
|--------------------------------------------------------------------------
| REPORT TYPE
|--------------------------------------------------------------------------
*/

var reportButtons =
    document.querySelectorAll('.report-type-button');

var reportTypeInput =
    document.getElementById('report_type');

var reportForm =
    document.getElementById('reportForm');


var i = 0;

for (i = 0; i < reportButtons.length; i++) {

    reportButtons[i].addEventListener('click', function () {

        reportTypeInput.value =
            this.getAttribute('data-type');

        /*
        |--------------------------------------------------------------------------
        | Submit immediately.
        |--------------------------------------------------------------------------
        */

        reportForm.submit();

    });

}


/*
|--------------------------------------------------------------------------
| DATE VALIDATION
|--------------------------------------------------------------------------
*/

var dateFrom =
    document.getElementById('date_from');

var dateTo =
    document.getElementById('date_to');


dateFrom.addEventListener('change', function () {

    if (this.value != '') {

        dateTo.min = this.value;

        if (dateTo.value != '' &&
            dateTo.value < this.value) {

            dateTo.value = this.value;
        }
    }

});


dateTo.addEventListener('change', function () {

    if (dateFrom.value != '' &&
        this.value < dateFrom.value) {

        alert('The To date cannot be earlier than the From date.');

        this.value = dateFrom.value;
    }

});


/*
|--------------------------------------------------------------------------
| QUICK RANGE
|--------------------------------------------------------------------------
*/

var quickRange =
    document.getElementById('quick_range');


quickRange.addEventListener('change', function () {

    var selected =
        this.value;

    if (selected == '') {
        return;
    }

    var now =
        new Date();

    var year =
        now.getFullYear();

    var month =
        now.getMonth();

    var day =
        now.getDate();


    var from;
    var to;


    /*
    |--------------------------------------------------------------------------
    | FORMAT DATE
    |--------------------------------------------------------------------------
    */

    function formatDate(date) {

        var y =
            date.getFullYear();

        var m =
            date.getMonth() + 1;

        var d =
            date.getDate();

        if (m < 10) {
            m = '0' + m;
        }

        if (d < 10) {
            d = '0' + d;
        }

        return y + '-' + m + '-' + d;
    }


    /*
    |--------------------------------------------------------------------------
    | TODAY
    |--------------------------------------------------------------------------
    */

    if (selected == 'today') {

        from =
            new Date(year, month, day);

        to =
            new Date(year, month, day);
    }


    /*
    |--------------------------------------------------------------------------
    | THIS WEEK
    |--------------------------------------------------------------------------
    */

    if (selected == 'week') {

        var currentDay =
            now.getDay();

        var mondayOffset =
            currentDay == 0 ? -6 : 1 - currentDay;

        from =
            new Date(year, month, day + mondayOffset);

        to =
            new Date(
                from.getFullYear(),
                from.getMonth(),
                from.getDate() + 6
            );
    }


    /*
    |--------------------------------------------------------------------------
    | THIS MONTH
    |--------------------------------------------------------------------------
    */

    if (selected == 'month') {

        from =
            new Date(year, month, 1);

        to =
            new Date(year, month + 1, 0);
    }


    /*
    |--------------------------------------------------------------------------
    | LAST MONTH
    |--------------------------------------------------------------------------
    */

    if (selected == 'last_month') {

        from =
            new Date(year, month - 1, 1);

        to =
            new Date(year, month, 0);
    }


    /*
    |--------------------------------------------------------------------------
    | THIS YEAR
    |--------------------------------------------------------------------------
    */

    if (selected == 'year') {

        from =
            new Date(year, 0, 1);

        to =
            new Date(year, 11, 31);
    }


    if (from && to) {

        dateFrom.value =
            formatDate(from);

        dateTo.value =
            formatDate(to);
    }

});


/*
|--------------------------------------------------------------------------
| RESET
|--------------------------------------------------------------------------
*/

document.getElementById('resetButton')
    .addEventListener('click', function () {

        window.location.href =
            '<?php echo basename($_SERVER['PHP_SELF']); ?>';

    });


/*
|--------------------------------------------------------------------------
| SEARCH
|--------------------------------------------------------------------------
| Search is submitted when Enter is pressed.
|--------------------------------------------------------------------------
*/

var searchInputs =
    document.querySelectorAll('.search-box input');

for (i = 0; i < searchInputs.length; i++) {

    searchInputs[i].addEventListener('keypress', function (event) {

        event =
            event || window.event;

        if (event.keyCode == 13) {

            event.preventDefault();

            this.form.submit();

            return false;
        }

    });

}
/*
|--------------------------------------------------------------------------
| LIVE CLOCK
|--------------------------------------------------------------------------
*/

function updateManagerClock() {

    var clock =
        document.getElementById('liveClock');

    if (!clock) {
        return;
    }

    var now =
        new Date();

    var hours =
        now.getHours();

    var minutes =
        now.getMinutes();

    var seconds =
        now.getSeconds();

    var ampm =
        hours >= 12 ? 'PM' : 'AM';

    hours =
        hours % 12;

    if (hours == 0) {
        hours = 12;
    }

    if (hours < 10) {
        hours = '0' + hours;
    }

    if (minutes < 10) {
        minutes = '0' + minutes;
    }

    if (seconds < 10) {
        seconds = '0' + seconds;
    }

    clock.innerHTML =
        hours + ':' +
        minutes + ':' +
        seconds + ' ' +
        ampm;
}


updateManagerClock();

setInterval(
    updateManagerClock,
    1000
);


/*
|--------------------------------------------------------------------------
| NOTIFICATION DROPDOWN
|--------------------------------------------------------------------------
*/

var notificationButton =
    document.getElementById('notificationButton');

var notificationDropdown =
    document.getElementById('notificationDropdown');


if (notificationButton &&
    notificationDropdown) {

    notificationButton.addEventListener(
        'click',
        function (event) {

            event =
                event || window.event;

            if (event.stopPropagation) {
                event.stopPropagation();
            }

            if (notificationDropdown.className.indexOf('show') == -1) {

                notificationDropdown.className +=
                    ' show';

            } else {

                notificationDropdown.className =
                    notificationDropdown.className.replace(
                        ' show',
                        ''
                    );
            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| MANAGER ACCOUNT DROPDOWN
|--------------------------------------------------------------------------
*/

var managerAccountButton =
    document.getElementById('managerAccountButton');

var managerAccountDropdown =
    document.getElementById('managerAccountDropdown');


if (managerAccountButton &&
    managerAccountDropdown) {

    managerAccountButton.addEventListener(
        'click',
        function (event) {

            event =
                event || window.event;

            if (event.stopPropagation) {
                event.stopPropagation();
            }

            if (managerAccountDropdown.className.indexOf('show') == -1) {

                managerAccountDropdown.className +=
                    ' show';

            } else {

                managerAccountDropdown.className =
                    managerAccountDropdown.className.replace(
                        ' show',
                        ''
                    );
            }

        }
    );
}


/*
|--------------------------------------------------------------------------
| CLOSE DROPDOWNS WHEN CLICKING OUTSIDE
|--------------------------------------------------------------------------
*/

document.addEventListener(
    'click',
    function () {

        if (notificationDropdown) {

            notificationDropdown.className =
                notificationDropdown.className.replace(
                    ' show',
                    ''
                );
        }


        if (managerAccountDropdown) {

            managerAccountDropdown.className =
                managerAccountDropdown.className.replace(
                    ' show',
                    ''
                );
        }

    }
);
</script>

</body>
</html>