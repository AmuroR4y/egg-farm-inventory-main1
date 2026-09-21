<?php
/**
 * Login Process Handler
 */

// Include standardized session configuration FIRST
require_once 'session_config.php';

include 'db.php';


/* =========================================
   CHECK IF FORM WAS SUBMITTED
========================================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST') {


    /* =========================================
       GET USER INPUT
    ========================================= */

    $raw_username = $_POST['username'];
    $raw_password = $_POST['password'];
    
    $username = mysqli_real_escape_string(
        $conn,
        $raw_username
    );

    $password = md5($raw_password);
    
    // DEBUG: Log login attempt details
    error_log("=== LOGIN ATTEMPT ===");
    error_log("Username input: '" . $raw_username . "'");
    error_log("Password MD5: " . $password);


    /* =========================================
       CHECK USER ACCOUNT
    ========================================= */

    $sql = "

        SELECT * FROM users

        WHERE username = '$username'

        AND password = '$password'

        AND status = 'active'

    ";

    error_log("SQL Query: " . $sql);

    $result = mysqli_query(
        $conn,
        $sql
    );
    
    if (!$result) {
        error_log("SQL ERROR: " . mysqli_error($conn));
    } else {
        error_log("Rows found: " . mysqli_num_rows($result));
    }
    
    // CRITICAL FIX: If login failed, check if it's a case sensitivity issue
    // or special character issue with the username
    if (!$result || mysqli_num_rows($result) == 0) {
        // Try a more flexible lookup to diagnose the issue
        $check_sql = "SELECT username, password, role, status FROM users WHERE LOWER(username) = LOWER('$username')";
        $check_result = mysqli_query($conn, $check_sql);
        
        if ($check_result && mysqli_num_rows($check_result) > 0) {
            $user_data = mysqli_fetch_assoc($check_result);
            error_log("Found user (case-insensitive match): " . $user_data['username']);
            error_log("Stored password hash: " . $user_data['password']);
            error_log("Submitted password hash: " . $password);
            
            // Check if this is a case sensitivity issue with the username
            if ($user_data['username'] !== $username) {
                error_log("USERNAME CASE MISMATCH: Database has '" . $user_data['username'] . "' but user entered '" . $username . "'");
            }
            
            // Check password match
            if ($user_data['password'] !== $password) {
                error_log("PASSWORD MISMATCH");
            } else {
                error_log("PASSWORD MATCHES!");
            }
        } else {
            error_log("User not found (even with case-insensitive search): " . $username);
        }
    }


    /* =========================================
       SUCCESSFUL LOGIN
    ========================================= */

    if (mysqli_num_rows($result) == 1) {

        $row = mysqli_fetch_assoc($result);

        error_log("=== LOGIN SUCCESS ===");
        error_log("User ID: " . $row['id']);
        error_log("Username: " . $row['username']);
        error_log("Role: " . $row['role']);

        /* =========================================
           CREATE SESSION
        ========================================= */

        $_SESSION['user_id'] = $row['id'];
        $_SESSION['fullname'] = $row['fullname'];
        $_SESSION['username'] = $row['username'];
        $_SESSION['role'] = $row['role'];
        
        error_log("Session created. Session ID: " . session_id());
        error_log("Session role: " . $_SESSION['role']);

        /* =========================================
           ROLE-BASED REDIRECTION
        ========================================= */

        if ($row['role'] == "owner") {
            error_log("Redirecting to owner_dashboard.php");
            header("Location: owner_dashboard.php");
            exit();
        }
        elseif ($row['role'] == "manager") {
            header("Location: manager_dashboard.php");
            exit();
        }
        else {
            header("Location: customer_dashboard.php");
            exit();
        }

    }


    /* =========================================
       INVALID LOGIN
    ========================================= */

    else {
        // Debug: Check if user exists with different password
        $user_check_sql = "SELECT username, password, role, status FROM users WHERE username = '$username'";
        $user_check_result = mysqli_query($conn, $user_check_sql);
        
        if ($user_check_result && mysqli_num_rows($user_check_result) == 1) {
            $user_row = mysqli_fetch_assoc($user_check_result);
            error_log("User found: " . $user_row['username']);
            error_log("Stored hash: " . $user_row['password']);
            error_log("Submitted hash: " . $password);
            error_log("Role: " . $user_row['role']);
            error_log("Status: " . $user_row['status']);
            
            // Check what password would match the stored hash
            $test_passwords = ['owner', 'password', '123456', 'admin', '123', 'owner123', 'Owner', 'OWNER'];
            foreach ($test_passwords as $test) {
                if (md5($test) === $user_row['password']) {
                    error_log("CORRECT PASSWORD FOUND: '$test'");
                    break;
                }
            }
        } else {
            error_log("User not found: $username");
        }

        echo "

        <script>

            alert('Invalid username or password.');

            window.location.href = 'login.php';

        </script>

        ";

        exit();

    }


}


else {


    header(
        "Location: login.php"
    );

    exit();

}

?>