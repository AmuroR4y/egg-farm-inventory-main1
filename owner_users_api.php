<?php
require_once 'session_config.php';
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Owner access required.'));
    exit();
}

function owner_users_response($message, $user = null) {
    echo json_encode(array('status' => 'success', 'message' => $message, 'user' => $user));
    exit();
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$userId = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$name = trim(isset($_POST['name']) ? $_POST['name'] : '');
$email = trim(isset($_POST['email']) ? $_POST['email'] : '');
$role = strtolower(trim(isset($_POST['role']) ? $_POST['role'] : ''));
$status = strtolower(trim(isset($_POST['status']) ? $_POST['status'] : ''));
$password = isset($_POST['password']) ? $_POST['password'] : '';

if ($action === 'create') {
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, array('manager', 'customer')) || !in_array($status, array('active', 'inactive')) || strlen($password) < 8) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Valid name, email, role, status, and an 8-character password are required.'));
        exit();
    }
    $username = strtolower(preg_replace('/[^a-z0-9]+/i', '.', $name));
    $username = trim($username, '.');
    $username .= '.' . substr(md5(uniqid('', true)), 0, 6);
    $stmt = mysqli_prepare($conn, "INSERT INTO users (fullname, username, email, password, role, status) VALUES (?, ?, ?, ?, ?, ?)");
    $hash = md5($password);
    mysqli_stmt_bind_param($stmt, 'ssssss', $name, $username, $email, $hash, $role, $status);
    if (!mysqli_stmt_execute($stmt)) {
        http_response_code(409);
        echo json_encode(array('status' => 'error', 'message' => 'Unable to create user. Username or email may already exist.'));
        mysqli_stmt_close($stmt);
        exit();
    }
    mysqli_stmt_close($stmt);
    owner_users_response('User created successfully.');
}

if ($userId <= 0) {
    http_response_code(400);
    echo json_encode(array('status' => 'error', 'message' => 'A valid user ID is required.'));
    exit();
}

if ($action === 'status') {
    if (!in_array($status, array('active', 'inactive'))) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Invalid status.'));
        exit();
    }
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ? AND role IN ('manager', 'customer')");
    mysqli_stmt_bind_param($stmt, 'si', $status, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    owner_users_response($success ? 'User status updated.' : 'Unable to update user status.');
}

if ($action === 'update') {
    if ($name === '' || $email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL) || !in_array($role, array('manager', 'customer')) || !in_array($status, array('active', 'inactive'))) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Valid user fields are required.'));
        exit();
    }
    $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ?, role = ?, status = ? WHERE id = ? AND role IN ('manager', 'customer')");
    mysqli_stmt_bind_param($stmt, 'ssssi', $name, $email, $role, $status, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    owner_users_response($success ? 'User updated successfully.' : 'Unable to update user.');
}

if ($action === 'delete') {
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ? AND role IN ('manager', 'customer')");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    owner_users_response($success ? 'User deleted successfully.' : 'Unable to delete user.');
}

http_response_code(400);
echo json_encode(array('status' => 'error', 'message' => 'Unsupported user action.'));
