<?php
require_once 'session_config.php';
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || $_SESSION['role'] !== 'owner') {
    http_response_code(403);
    echo json_encode(array('status' => 'error', 'message' => 'Owner access required.'));
    exit();
}

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validate CSRF token if provided
if (isset($_POST['csrf_token']) && !empty($_POST['csrf_token'])) {
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        http_response_code(403);
        echo json_encode(array('status' => 'error', 'message' => 'Invalid CSRF token.'));
        exit();
    }
}

$action = isset($_POST['action']) ? $_POST['action'] : '';
$userId = (int)$_SESSION['user_id'];

if ($action === 'profile') {
    $fullname = trim(isset($_POST['fullname']) ? $_POST['fullname'] : '');
    $email = trim(isset($_POST['email']) ? $_POST['email'] : '');
    $phone = trim(isset($_POST['phone']) ? $_POST['phone'] : '');
    
    // Validation
    if ($fullname === '') {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Full name is required.'));
        exit();
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Please enter a valid email address.'));
        exit();
    }
    
    if (strlen($fullname) > 100) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Full name must be less than 100 characters.'));
        exit();
    }
    
    if (strlen($email) > 100) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Email must be less than 100 characters.'));
        exit();
    }
    
    if ($phone !== '' && strlen($phone) > 20) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Phone number must be less than 20 characters.'));
        exit();
    }
    
    // Check for duplicate email (excluding current user)
    $checkEmail = mysqli_prepare($conn, "SELECT id FROM users WHERE email = ? AND id != ? AND role = 'owner'");
    mysqli_stmt_bind_param($checkEmail, 'si', $email, $userId);
    mysqli_stmt_execute($checkEmail);
    $emailResult = mysqli_stmt_get_result($checkEmail);
    if (mysqli_num_rows($emailResult) > 0) {
        mysqli_stmt_close($checkEmail);
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'This email address is already in use.'));
        exit();
    }
    mysqli_stmt_close($checkEmail);
    
    // Update profile
    $stmt = mysqli_prepare($conn, "UPDATE users SET fullname = ?, email = ?, phone = ? WHERE id = ? AND role = 'owner'");
    mysqli_stmt_bind_param($stmt, 'sssi', $fullname, $email, $phone, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // Update session
        $_SESSION['fullname'] = $fullname;
        $_SESSION['name'] = $fullname;
    }
    
    echo json_encode(array('status' => $success ? 'success' : 'error', 'message' => $success ? 'Profile updated successfully.' : 'Unable to update profile.'));
    exit();
}

if ($action === 'password') {
    $current = isset($_POST['current_password']) ? $_POST['current_password'] : '';
    $newPassword = isset($_POST['new_password']) ? $_POST['new_password'] : '';
    $confirmPassword = isset($_POST['confirm_password']) ? $_POST['confirm_password'] : '';
    
    // Validation
    if ($current === '') {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Current password is required.'));
        exit();
    }
    
    if ($newPassword === '') {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'New password is required.'));
        exit();
    }
    
    if ($confirmPassword === '') {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Please confirm your new password.'));
        exit();
    }
    
    if ($newPassword !== $confirmPassword) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'New passwords do not match.'));
        exit();
    }
    
    if (strlen($newPassword) < 8) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Password must be at least 8 characters long.'));
        exit();
    }
    
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $newPassword)) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Password must contain at least one special character.'));
        exit();
    }
    
    // Get current password hash
    $stmt = mysqli_prepare($conn, "SELECT password FROM users WHERE id = ? AND role = 'owner' LIMIT 1");
    mysqli_stmt_bind_param($stmt, 'i', $userId);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $row = mysqli_fetch_assoc($result);
    mysqli_stmt_close($stmt);
    
    if (!$row) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'User not found.'));
        exit();
    }
    
    // Verify current password (support both MD5 legacy and modern hashes)
    $currentHash = $row['password'];
    $passwordValid = false;
    
    // Check if it's a modern hash (starts with $2y$ for bcrypt)
    if (strpos($currentHash, '$2y$') === 0 || strpos($currentHash, '$2a$') === 0) {
        $passwordValid = password_verify($current, $currentHash);
    } else {
        // Legacy MD5 verification
        $passwordValid = hash_equals(strtolower($currentHash), md5($current));
    }
    
    if (!$passwordValid) {
        http_response_code(400);
        echo json_encode(array('status' => 'error', 'message' => 'Current password is incorrect.'));
        exit();
    }
    
    // Hash new password with modern algorithm
    $newHash = password_hash($newPassword, PASSWORD_DEFAULT);
    
    // Update password
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ? AND role = 'owner'");
    mysqli_stmt_bind_param($stmt, 'si', $newHash, $userId);
    $success = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    
    if ($success) {
        // Regenerate session ID for security
        session_regenerate_id(true);
    }
    
    echo json_encode(array('status' => $success ? 'success' : 'error', 'message' => $success ? 'Password updated successfully.' : 'Unable to update password.'));
    exit();
}

http_response_code(400);
echo json_encode(array('status' => 'error', 'message' => 'Unsupported profile action.'));
