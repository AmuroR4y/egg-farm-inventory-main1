<?php
/**
 * Inventory API — serves egg and supply inventory data to the owner-dashboard
 * analytics engine (app_analytics.js).
 *
 * Supported query parameters:
 *   resource=eggs      — egg_inventory ledger rows within ?from date
 *   resource=supplies  — supply_inventory ledger rows within ?from date
 *   resource=snapshot  — latest current_stock per egg_size / per supply item
 *
 * This endpoint mirrors the subset of owner_api.php that the owner dashboard
 * JS needs for inventory KPIs, charts, and stock calculations.
 */

require_once 'session_config.php';
require_once 'db.php';
require_once 'config_age_eligibility.php';

header('Content-Type: application/json; charset=utf-8');

// Allow both manager and owner roles to access inventory data.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required.']);
    exit();
}
if (!in_array($_SESSION['role'], ['owner', 'manager'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Insufficient role.']);
    exit();
}

$resource = isset($_GET['resource']) ? trim($_GET['resource']) : '';
$from     = isset($_GET['from'])     ? trim($_GET['from'])     : '';
$limit    = isset($_GET['limit'])    ? (int)$_GET['limit']     : 500;
$limit    = min(max($limit, 1), 2000); // clamp 1–2000

// Validate ?from date; fall back to 30 days ago if absent or invalid.
$from_date = '';
if ($from && preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) {
    $from_date = $from;
} else {
    $from_date = date('Y-m-d', strtotime('-30 days'));
}

try {

    /* ============================================================
       resource=eggs — egg_inventory ledger rows
    ============================================================ */
    if ($resource === 'eggs') {
        $safe_from = mysqli_real_escape_string($conn, $from_date);
        $result = mysqli_query($conn,
            "SELECT id, batch_id, harvest_date, harvest_time, egg_size, quantity,
                    current_stock, movement_type, reason, date_logged
             FROM egg_inventory
             WHERE date_logged >= '$safe_from 00:00:00'
             ORDER BY id DESC
             LIMIT $limit"
        );
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        echo json_encode(['status' => 'success', 'rows' => $rows]);
        exit();
    }

    /* ============================================================
       resource=supplies — supply_inventory ledger rows
    ============================================================ */
    if ($resource === 'supplies') {
        $safe_from = mysqli_real_escape_string($conn, $from_date);
        $result = mysqli_query($conn,
            "SELECT id, batch_id, item_category, item_name, quantity,
                    current_stock, action_type, reason, date_logged
             FROM supply_inventory
             WHERE date_logged >= '$safe_from 00:00:00'
             ORDER BY id DESC
             LIMIT $limit"
        );
        if (!$result) {
            throw new Exception(mysqli_error($conn));
        }
        $rows = [];
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
        echo json_encode(['status' => 'success', 'rows' => $rows]);
        exit();
    }

    /* ============================================================
       resource=snapshot — latest current_stock per egg_size /
       per supply item (what the dashboard KPI cards show)

       Uses the same eligibility window as owner_api.php and
       manager_dashboard.php:  harvest_date >= (today - excluded_days).

       For stock-out rows the harvest_date is the stock-out date,
       so recent stock-outs are captured correctly.
    ============================================================ */
    if ($resource === 'snapshot') {
        $eligible_min_date = get_eligible_min_date();
        $safe_min = mysqli_real_escape_string($conn, $eligible_min_date);

        // Latest egg row (by id) per egg_size, within the eligibility window.
        $egg_result = mysqli_query($conn,
            "SELECT e.id, e.batch_id, e.harvest_date, e.harvest_time,
                    e.egg_size, e.quantity, e.current_stock,
                    e.movement_type, e.reason, e.date_logged
             FROM egg_inventory e
             INNER JOIN (
                 SELECT egg_size, MAX(id) AS id
                 FROM egg_inventory
                 WHERE harvest_date >= '$safe_min'
                 GROUP BY egg_size
             ) latest ON latest.id = e.id"
        );
        if (!$egg_result) {
            throw new Exception(mysqli_error($conn));
        }
        $eggs = [];
        while ($row = mysqli_fetch_assoc($egg_result)) {
            $eggs[] = $row;
        }

        // Latest supply row (by id) per (item_category, item_name).
        $supply_result = mysqli_query($conn,
            "SELECT s.id, s.batch_id, s.item_category, s.item_name,
                    s.quantity, s.current_stock, s.action_type,
                    s.reason, s.date_logged
             FROM supply_inventory s
             INNER JOIN (
                 SELECT item_category, item_name, MAX(id) AS id
                 FROM supply_inventory
                 GROUP BY item_category, item_name
             ) latest ON latest.id = s.id"
        );
        if (!$supply_result) {
            throw new Exception(mysqli_error($conn));
        }
        $supplies = [];
        while ($row = mysqli_fetch_assoc($supply_result)) {
            $supplies[] = $row;
        }

        echo json_encode([
            'status'   => 'success',
            'eggs'     => $eggs,
            'supplies' => $supplies,
        ]);
        exit();
    }

    // Unknown resource
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => "Unknown resource: '$resource'. Use eggs, supplies, or snapshot."]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Inventory API error: ' . $e->getMessage()]);
}
