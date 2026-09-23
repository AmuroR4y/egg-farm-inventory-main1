<?php
require_once 'session_config.php';
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Owner access required.'));
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$userId = (int)$_SESSION['user_id'];

if ($action === 'profile') {
    $fullname = trim(isset($_POST['fullname']) ? $_POST['fullname'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    if ($fullname === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'A valid name and email are required.'));
        exit();
    }
    $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ? WHERE id = ? AND role = 'owner'");
    mysqli_stmt_bind_param($stmt, 'ssi', $fullname, $email, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(array('status' => $success ? 'success' : 'error', 'message' => $success ? 'Profile updated successfully.' : 'Unable to update profile.'));
    exit();
}

if ($action === 'password') {
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    if ($current === '' || strlen($newPassword) < 8 || !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Password requirements were not met.'));
        exit();
    }
    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? AND role = 'owner' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    if (!$row || !hash_equals(strtolower($row['password']), md5($current))) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Current password is incorrect.'));
        exit();
    }
    $newHash = md5($newPassword);
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'owner'");
    mysqli_stmt_bind_param($stmt, 'si', $newHash, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    echo json_encode(array('status' => $success ? 'success' : 'error', 'message' => $success ? 'Password updated successfully.' : 'Unable to update password.'));
    exit();
}

http_response_code(400);
echo json_encode(array('status' => 'error', 'message' => 'Unsupported profile action.'));
