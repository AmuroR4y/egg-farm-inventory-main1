<?php
// Include standardized session configuration FIRST
require_once 'session_config.php';

date_default_timezone_set('Asia/Manila');

include 'db.php';

/*
|--------------------------------------------------------------------------
| SECURITY CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_role = isset($_SESSION['role']) ? $_SESSION['role'] : 'customer';

$customer_name = isset($_SESSION['fullname'])
    ? $_SESSION['fullname']
    : (isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer'));
$active_page = 'profile';

$unread_notifications = 0;
$notification_count_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$user_id . " AND is_read = 0");
if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
}

if ($user_role !== 'customer') {
    header("Location: login.php");
    exit;
}
/* =========================================================
   LOGOUT HANDLER
========================================================= */

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['customer_logout'])) {
    $submitted_token = isset($_POST['logout_token']) ? $_POST['logout_token'] : '';
    $session_token = isset($_SESSION['customer_logout_token']) ? $_SESSION['customer_logout_token'] : '';

    if (
        $submitted_token !== '' &&
        $session_token !== '' &&
        is_string($submitted_token) &&
        is_string($session_token) &&
        strlen($submitted_token) === strlen($session_token) &&
        hash_equals($session_token, $submitted_token)
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

    header("Location: customer_profile.php");
    exit();
}

/* =========================================================
   CUSTOMER LOGOUT TOKEN
   PHP 5.5 COMPATIBLE
========================================================= */

if (empty($_SESSION['customer_logout_token'])) {
    if (function_exists('random_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(random_bytes(32));
    } elseif (function_exists('openssl_random_pseudo_bytes')) {
        $_SESSION['customer_logout_token'] = bin2hex(openssl_random_pseudo_bytes(32));
    } else {
        $_SESSION['customer_logout_token'] = hash(
            'sha256',
            uniqid(mt_rand(), true) . session_id()
        );
    }
}

/*
|--------------------------------------------------------------------------
| DEFAULT VALUES
|--------------------------------------------------------------------------
*/

$success_msg = "";
$error_msg = "";

$db_fullname = "";
$db_username = "";
$db_email = "";
$db_phone = "";
$db_address = "";
$db_profile_photo = "";


/*
|--------------------------------------------------------------------------
| CHECK OPTIONAL DATABASE COLUMNS
|--------------------------------------------------------------------------
*/

$has_phone = false;
$has_contact_number = false;
$has_address = false;
$has_profile_photo = false;

$column_query = mysqli_query($conn, "SHOW COLUMNS FROM users");

if ($column_query) {

    while ($column = mysqli_fetch_assoc($column_query)) {

        if ($column['Field'] == 'phone') {
            $has_phone = true;
        }

        if ($column['Field'] == 'contact_number') {
            $has_contact_number = true;
        }

        if ($column['Field'] == 'address') {
            $has_address = true;
        }

        if ($column['Field'] == 'profile_photo') {
            $has_profile_photo = true;
        }
    }
}


/*
|--------------------------------------------------------------------------
| NOTIFICATION COUNT
|--------------------------------------------------------------------------
*/

$unread_notifications = 0;
$notification_count_result = $conn->query("SELECT COUNT(*) AS total FROM notifications WHERE user_id = " . (int)$user_id . " AND is_read = 0");
if ($notification_count_result && ($notification_count_row = $notification_count_result->fetch_assoc())) {
    $unread_notifications = (int)$notification_count_row['total'];
}

/*
|--------------------------------------------------------------------------
| FETCH USER INFORMATION
|--------------------------------------------------------------------------
*/

$select_fields = "id, fullname, username, email";

if ($has_phone) {
    $select_fields .= ", phone";
}

if ($has_contact_number) {
    $select_fields .= ", contact_number";
}

if ($has_address) {
    $select_fields .= ", address";
}

if ($has_profile_photo) {
    $select_fields .= ", profile_photo";
}


$sql = "
    SELECT $select_fields
    FROM users
    WHERE id = ?
    LIMIT 1
";


if ($stmt = mysqli_prepare($conn, $sql)) {

    mysqli_stmt_bind_param(
        $stmt,
        "i",
        $user_id
    );

    mysqli_stmt_execute($stmt);

    $result = mysqli_stmt_get_result($stmt);

    if ($user = mysqli_fetch_assoc($result)) {

        $db_fullname = isset($user['fullname'])
            ? $user['fullname']
            : '';

        // Use session customer_name for display
        $customer_name = isset($_SESSION['fullname'])
            ? $_SESSION['fullname']
            : (isset($_SESSION['customer_name']) ? $_SESSION['customer_name'] : (isset($_SESSION['name']) ? $_SESSION['name'] : 'Customer'));

        $db_username = isset($user['username'])
            ? $user['username']
            : '';

        $db_email = isset($user['email'])
            ? $user['email']
            : '';


        if (
            $has_phone &&
            isset($user['phone'])
        ) {

            $db_phone = $user['phone'];

        } elseif (
            $has_contact_number &&
            isset($user['contact_number'])
        ) {

            $db_phone = $user['contact_number'];
        }


        if (
            $has_address &&
            isset($user['address'])
        ) {

            $db_address = $user['address'];
        }


        if (
            $has_profile_photo &&
            isset($user['profile_photo'])
        ) {

            $db_profile_photo =
                $user['profile_photo'];
        }
    }

    mysqli_stmt_close($stmt);
}


/*
|--------------------------------------------------------------------------
| DEFAULT AVATAR
|--------------------------------------------------------------------------
*/

$avatar_src = "";

if (
    !empty($db_profile_photo) &&
    file_exists($db_profile_photo)
) {

    $avatar_src = $db_profile_photo;

} else {

    $avatar_src =
        "https://ui-avatars.com/api/?name=" .
        urlencode($db_fullname) .
        "&background=e4f0e7" .
        "&color=2f6b45" .
        "&size=200";
}


/*
|--------------------------------------------------------------------------
| PROFILE UPDATE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST['save_profile_changes'])
) {

    $fullname_input =
        isset($_POST['fullname'])
        ? trim($_POST['fullname'])
        : '';

    $email_input =
        isset($_POST['email'])
        ? trim($_POST['email'])
        : '';

    $phone_input =
        isset($_POST['phone'])
        ? trim($_POST['phone'])
        : '';

    $address_input =
        isset($_POST['address'])
        ? trim($_POST['address'])
        : '';


    /*
    |--------------------------------------------------------------------------
    | VALIDATION
    |--------------------------------------------------------------------------
    */

    if (empty($fullname_input)) {

        $error_msg =
            "Full name is required.";

    } elseif (empty($email_input)) {

        $error_msg =
            "Email address is required.";

    } elseif (
        !filter_var(
            $email_input,
            FILTER_VALIDATE_EMAIL
        )
    ) {

        $error_msg =
            "Please enter a valid email address.";
    }


    /*
    |--------------------------------------------------------------------------
    | CHECK IF EMAIL EXISTS
    |--------------------------------------------------------------------------
    */

    if (empty($error_msg)) {

        $check_email_sql = "
            SELECT id
            FROM users
            WHERE email = ?
            AND id != ?
            LIMIT 1
        ";


        if (
            $check_stmt =
            mysqli_prepare(
                $conn,
                $check_email_sql
            )
        ) {

            mysqli_stmt_bind_param(
                $check_stmt,
                "si",
                $email_input,
                $user_id
            );

            mysqli_stmt_execute($check_stmt);

            $check_result =
                mysqli_stmt_get_result(
                    $check_stmt
                );

            if (
                mysqli_num_rows(
                    $check_result
                ) > 0
            ) {

                $error_msg =
                    "This email address is already being used.";
            }

            mysqli_stmt_close(
                $check_stmt
            );
        }
    }


    /*
    |--------------------------------------------------------------------------
    | PROFILE PHOTO UPLOAD
    |--------------------------------------------------------------------------
    */

    $new_photo_path =
        $db_profile_photo;


    if (
        empty($error_msg) &&
        $has_profile_photo &&
        isset($_FILES['profile_photo']) &&
        $_FILES['profile_photo']['error'] ==
        UPLOAD_ERR_OK
    ) {

        $file_name =
            $_FILES['profile_photo']['name'];

        $file_tmp =
            $_FILES['profile_photo']['tmp_name'];

        $file_size =
            $_FILES['profile_photo']['size'];

        $file_extension =
            strtolower(
                pathinfo(
                    $file_name,
                    PATHINFO_EXTENSION
                )
            );


        $allowed_extensions = array(
            'jpg',
            'jpeg',
            'png',
            'gif'
        );


        if (
            !in_array(
                $file_extension,
                $allowed_extensions
            )
        ) {

            $error_msg =
                "Invalid image format. Please upload JPG, JPEG, PNG, or GIF.";

        } elseif ($file_size > 5000000) {

            $error_msg =
                "Profile image must not exceed 5MB.";

        } else {

            $upload_directory =
                "uploads/profile_photos/";


            if (
                !is_dir(
                    $upload_directory
                )
            ) {

                mkdir(
                    $upload_directory,
                    0755,
                    true
                );
            }


            $new_file_name =
                "customer_" .
                $user_id .
                "_" .
                time() .
                "." .
                $file_extension;


            $destination =
                $upload_directory .
                $new_file_name;


            if (
                move_uploaded_file(
                    $file_tmp,
                    $destination
                )
            ) {

                $new_photo_path =
                    $destination;

            } else {

                $error_msg =
                    "Unable to upload your profile image.";
            }
        }
    }


    /*
    |--------------------------------------------------------------------------
    | UPDATE USER PROFILE
    |--------------------------------------------------------------------------
    */

    if (empty($error_msg)) {

        $update_fields = array();

        $update_fields[] =
            "fullname = ?";

        $update_fields[] =
            "email = ?";


        if ($has_phone) {

            $update_fields[] =
                "phone = ?";
        }


        if ($has_contact_number) {

            $update_fields[] =
                "contact_number = ?";
        }


        if ($has_address) {

            $update_fields[] =
                "address = ?";
        }


        if ($has_profile_photo) {

            $update_fields[] =
                "profile_photo = ?";
        }


        $update_sql =
            "UPDATE users SET " .
            implode(
                ", ",
                $update_fields
            ) .
            " WHERE id = ?";


        if (
            $update_stmt =
            mysqli_prepare(
                $conn,
                $update_sql
            )
        ) {

            if (
                $has_phone &&
                $has_contact_number &&
                $has_address &&
                $has_profile_photo
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $phone_input,
                    $address_input,
                    $new_photo_path,
                    $user_id
                );

            } elseif (
                $has_phone &&
                $has_address &&
                $has_profile_photo
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $address_input,
                    $new_photo_path,
                    $user_id
                );

            } elseif (
                $has_contact_number &&
                $has_address &&
                $has_profile_photo
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $address_input,
                    $new_photo_path,
                    $user_id
                );

            } elseif (
                $has_phone &&
                $has_address
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $address_input,
                    $user_id
                );

            } elseif (
                $has_contact_number &&
                $has_address
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $address_input,
                    $user_id
                );

            } elseif (
                $has_phone &&
                $has_profile_photo
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $new_photo_path,
                    $user_id
                );

            } elseif (
                $has_contact_number &&
                $has_profile_photo
            ) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $new_photo_path,
                    $user_id
                );

            } elseif ($has_address) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $fullname_input,
                    $email_input,
                    $address_input,
                    $user_id
                );

            } elseif ($has_phone) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $user_id
                );

            } elseif ($has_contact_number) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $fullname_input,
                    $email_input,
                    $phone_input,
                    $user_id
                );

            } elseif ($has_profile_photo) {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "sssi",
                    $fullname_input,
                    $email_input,
                    $new_photo_path,
                    $user_id
                );

            } else {

                mysqli_stmt_bind_param(
                    $update_stmt,
                    "ssi",
                    $fullname_input,
                    $email_input,
                    $user_id
                );
            }


            if (
                mysqli_stmt_execute(
                    $update_stmt
                )
            ) {

                $success_msg =
                    "Profile changes saved successfully!";


                $db_fullname =
                    $fullname_input;

                $db_email =
                    $email_input;

                $db_phone =
                    $phone_input;

                $db_address =
                    $address_input;

                $db_profile_photo =
                    $new_photo_path;


                $_SESSION['fullname'] =
                    $fullname_input;

            } else {

                $error_msg =
                    "Unable to update your profile.";
            }


            mysqli_stmt_close(
                $update_stmt
            );

        } else {

            $error_msg =
                "Database error while updating your profile.";
        }
    }
}


/*
|--------------------------------------------------------------------------
| PASSWORD CHANGE
|--------------------------------------------------------------------------
*/

if (
    $_SERVER["REQUEST_METHOD"] == "POST" &&
    isset($_POST['change_password'])
) {

    $current_password =
        isset($_POST['current_password'])
        ? $_POST['current_password']
        : '';

    $new_password =
        isset($_POST['new_password'])
        ? $_POST['new_password']
        : '';

    $confirm_password =
        isset($_POST['confirm_password'])
        ? $_POST['confirm_password']
        : '';


    if (
        empty($current_password) ||
        empty($new_password) ||
        empty($confirm_password)
    ) {

        $error_msg =
            "Please complete all password fields.";

    } elseif (
        strlen($new_password) < 8
    ) {

        $error_msg =
            "New password must be at least 8 characters.";

    } elseif (
        $new_password != $confirm_password
    ) {

        $error_msg =
            "New passwords do not match.";

    } else {

        $password_sql =
            "SELECT password
             FROM users
             WHERE id = ?
             LIMIT 1";


        if (
            $password_stmt =
            mysqli_prepare(
                $conn,
                $password_sql
            )
        ) {

            mysqli_stmt_bind_param(
                $password_stmt,
                "i",
                $user_id
            );

            mysqli_stmt_execute(
                $password_stmt
            );

            $password_result =
                mysqli_stmt_get_result(
                    $password_stmt
                );


            if (
                $password_row =
                mysqli_fetch_assoc(
                    $password_result
                )
            ) {

                $stored_password =
                    $password_row['password'];

                $current_md5 =
                    md5($current_password);


                if (
                    $current_md5 ==
                    $stored_password
                ) {

                    $new_hashed_password =
                        md5($new_password);


                    $change_password_sql =
                        "UPDATE users
                         SET password = ?
                         WHERE id = ?";


                    if (
                        $change_stmt =
                        mysqli_prepare(
                            $conn,
                            $change_password_sql
                        )
                    ) {

                        mysqli_stmt_bind_param(
                            $change_stmt,
                            "si",
                            $new_hashed_password,
                            $user_id
                        );


                        if (
                            mysqli_stmt_execute(
                                $change_stmt
                            )
                        ) {

                            $success_msg =
                                "Password changed successfully!";

                        } else {

                            $error_msg =
                                "Unable to change your password.";
                        }


                        mysqli_stmt_close(
                            $change_stmt
                        );
                    }

                } else {

                    $error_msg =
                        "Your current password is incorrect.";
                }
            }

            mysqli_stmt_close(
                $password_stmt
            );
        }
    }
}


/*
|--------------------------------------------------------------------------
| REFRESH AVATAR
|--------------------------------------------------------------------------
*/

if (
    !empty($db_profile_photo) &&
    file_exists($db_profile_photo)
) {

    $avatar_src =
        $db_profile_photo;

} else {

    $avatar_src =
        "https://ui-avatars.com/api/?name=" .
        urlencode($db_fullname) .
        "&background=e4f0e7" .
        "&color=2f6b45" .
        "&size=200";
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
        VDVC Egg Farm - Profile Management
    </title>


    <script
        src="https://cdn.tailwindcss.com"
    ></script>


    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <link rel="stylesheet" href="shared_notifications.css?v=20261022">

<style>
* {
    box-sizing: border-box;
}

html, body {
    margin: 0;
    min-height: 100%;
}

body {
    font-family: Arial, Helvetica, sans-serif;
    background: #f3f1eb;
    color: #35463b;
    font-size: 12px;
}

button, input {
    font-family: inherit;
}

a {
    -webkit-tap-highlight-color: transparent;
}


/* =========================================================
   CUSTOMER NAVIGATION — MATCH CUSTOMER DASHBOARD
========================================================= */

.customer-navbar {
    width: 100%;
    height: 84px;
    padding: 0 48px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
    background: #0d4828;
    border-top: 1px solid #e7e8e2;
    border-bottom: 1px solid #dedfd8;
    /* Keep navbar visible while scrolling */
    position: sticky;
    top: 0;
    left: 0;

    z-index: 1000;
}

.customer-navbar-logo {
    width: 190px;
    flex-shrink: 0;
    display: flex;
    align-items: center;
}

.customer-navbar-logo a {
    display: flex;
    align-items: center;
}

.customer-navbar-logo img {
    display: block;
    width: 145px;
    height: auto;
    object-fit: contain;
}

.customer-navbar-center {
    height: 84px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 38px;
    margin-left: 80px;
}

.customer-nav-link {
    height: 100%;
    padding: 0 2px;
    display: flex;
    align-items: center;
    justify-content: center;
    position: relative;
    text-decoration: none;
    white-space: nowrap;
    color: #e8f1e9;
    font-size: 14px;
    font-weight: 500;
    transition: color .2s ease;
}

.customer-nav-link:hover,
.customer-nav-link.active {
    color: #b9e4c3;
}

.customer-nav-link.active {
    font-weight: 700;
    color:#ffffff;
}

.customer-nav-link.active::after {
    content: "";
    height: 2px;
    position: absolute;
    bottom: 20px;
    left: 0;
    right: 0;
    background: #14552d;
    border-radius: 2px;
}

.customer-navbar-right {
    min-width: 240px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 16px;
}

.customer-notification-wrapper {
    position: relative;
    flex-shrink: 0;
}

.customer-top-notification {
    width: 36px;
    height: 36px;
    padding: 0;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #eaf2e8;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 16px;
    cursor: pointer;
    position: relative;
}

.customer-top-notification:hover {

    color: #bfe3c7;
}

.customer-notification-badge {
    position: absolute;
    top: 0;
    right: 0;
    min-width: 16px;
    height: 16px;
    padding: 0 4px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 20px;
     background-color: #eaf2e8;
    color: #0d4828;
    border: 2px solid #0d4828;
    font-size: 9px;
    font-weight: 700;
}

.customer-notification-dropdown {
    position: absolute;
    top: 46px;
    right: -8px;
    width: 350px;
    max-width: calc(100vw - 24px);
    max-height: 440px;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e0e5df;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(30, 50, 35, .15);
    z-index: 1000;
}

.customer-notification-dropdown.hidden {
    display: none;
}

.customer-notification-dropdown-header {
    min-height: 50px;
    padding: 12px 15px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 10px;
    background: #f8faf7;
    border-bottom: 1px solid #e9ede7;
}

.customer-panel-title-group {
    display: flex;
    align-items: center;
    gap: 9px;
}

.customer-panel-title-icon {
    width: 30px;
    height: 30px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #eaf3e9;
    color: #286440;
    font-size: 13px;
}

.customer-dashboard-panel-title {
    color: #304b39;
    font-size: 13px;
    font-weight: 700;
}

.customer-mark-all-link {
    color: #36704b;
    font-size: 11px;
    font-weight: 700;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
}

.customer-mark-all-link:hover {
    text-decoration: underline;
}

.customer-notification-list {
    max-height: 385px;
    overflow-y: auto;
}

.customer-notification-item {
    display: flex;
    align-items: flex-start;
    gap: 11px;
    padding: 13px 15px;
    border-bottom: 1px solid #eff1ed;
    cursor: pointer;
}

.customer-notification-item:hover {
    background: #f8fbf7;
}

.customer-notification-item.unread {
    background: #eff6ef;
}

.customer-notification-icon {
    width: 32px;
    height: 32px;
    min-width: 32px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    font-size: 13px;
}

.customer-notification-icon.success {
    background: #e2f3e4;
    color: #28713c;
}

.customer-notification-icon.alert {
    background: #fae8e6;
    color: #b84e45;
}

.customer-notification-icon.info {
    background: #e5eff7;
    color: #3b6d8a;
}

.customer-notification-icon.warning {
    background: #fff2d9;
    color: #a7741e;
}

.customer-notification-content {
    flex: 1;
    min-width: 0;
}

.customer-notification-title {
    margin-bottom: 4px;
    color: #334b3a;
    font-size: 12px;
    font-weight: 700;
}

.customer-notification-description {
    color: #6d7b71;
    font-size: 11px;
    line-height: 1.5;
}

.customer-notification-time {
    margin-top: 5px;
    color: #929c94;
    font-size: 10px;
}

.customer-notification-empty {
    padding: 30px 16px;
    text-align: center;
    color: #89958c;
}

.customer-notification-empty i {
    display: block;
    margin-bottom: 8px;
    font-size: 25px;
}

.customer-notification-empty p {
    margin: 0;
    font-size: 12px;
}

/* CUSTOMER ACCOUNT */

.customer-account-link {
    display: flex;
    align-items: center;

    gap: 9px;

    text-decoration: none;

    color: #263d32;
}

.customer-avatar {
    width: 42px;
    height: 42px;
    min-width: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 1px solid #e1e9df;
    border-radius: 50%;
   background-color: #dcebdd;
color: #0d4828;
    font-size: 16px;
}

.customer-account-details {
    display: flex;
    flex-direction: column;

    justify-content: center;

    min-width: 100px;
}

.customer-top-name {
    display: block;

    font-size: 13px;
    font-weight: 700;

    line-height: 1.2;

    color: #ffffff;
}

.customer-date-time {
    display: flex;
    flex-direction: column;

    gap: 1px;

    margin-top: 3px;
}
 
.customer-date{
    font-size: 9px;
    color: #d5e5d8;
}

.customer-time {
    font-size: 9px;

    color: #d5e5d8;

    font-weight: 600;
    
}

.customer-dropdown-icon {
     color: #718079;
    font-size: 9px;

    margin-left: 3px;
}

/* Keep if used by existing notification JavaScript */
.customer-mark-read-btn {
    cursor: pointer;
}


/* ==================== PROFILE BANNER ==================== */

.profile-banner {
    position: relative;
    min-height: 143px;
    width: 100%;
    padding: 24px 5%;
    display: flex;
    align-items: center;
      justify-content: space-between;
    gap: 20px;

    overflow: hidden;
    border: 0;
    border-radius: 0;
  background-image: url("profile.jpg");
       background-position: center, center 52%;
    background-size: cover;
    background-repeat: no-repeat;

    border-bottom: 1px solid #e4e8dc;
}
  
.profile-banner-content {
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
}

.profile-banner h1 {
      margin: 0 0 7px;
    color: #17472d;
    font-family: Georgia, "Times New Roman", serif;
    font-size: clamp(27px, 3.1vw, 28px);
    font-weight: 700;
    line-height: 1.12;
    letter-spacing: -0.5px;
}

.profile-banner p {
     margin: 0;
    color: #303a32;
    font-size: 15px;
    line-height: 1.45;
}

/* ==================== PAGE LAYOUT ==================== */

.main-content {
    min-height: calc(100vh - 160px);
    padding: 20px 4% 25px;
}

.profile-page {
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
}

.profile-outer-card {
    width: 100%;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e0e7de;
    border-radius: 9px;
    box-shadow: 0 3px 12px rgba(35, 67, 41, .05);
}

.profile-layout {
    display: grid;
    grid-template-columns: 260px minmax(0, 1fr);
    align-items: stretch;
    gap: 14px;
    padding: 14px;
}

/* ==================== PROFILE SIDEBAR ==================== */

.profile-sidebar-card {
    width: 100%;
    min-width: 0;
    overflow: hidden;
    background: #fff;
    border: 1px solid #e7ebe5;
    border-radius: 7px;
    margin-top: 35px;
}
.profile-address-item {
    align-items: flex-start;
    min-width: 0;
}

.profile-address-item .contact-icon {
    flex-shrink: 0;
}

.profile-address-item span {
    min-width: 0;
    overflow-wrap: anywhere;
}
.profile-user-section {
    padding: 15px 12px 14px;
    text-align: center;
    border-bottom: 1px solid #edf0eb;
}

.profile-avatar-wrapper {
    position: relative;
    width: 68px;
    height: 68px;
    margin: 0 auto 10px;
}

.profile-avatar {
    width: 100%;
    height: 100%;
    display: block;
    object-fit: cover;
    border: 3px solid #edf4e9;
    border-radius: 50%;
    background: #edf4e9;
}

.edit-photo-button {
    position: absolute;
    right: -1px;
    bottom: 0;
    width: 23px;
    height: 23px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 2px solid #fff;
    border-radius: 50%;
    background: #28623b;
    color: #fff;
    font-size: 9px;
    cursor: pointer;
}

.profile-name {
    margin-bottom: 5px;
    color: #35483a;
    font-size: 15px;
    font-weight: 700;
    overflow-wrap: anywhere;
}

.role-badge {
    display: inline-block;
    padding: 4px 10px;
    border-radius: 12px;
    background: #eaf3e8;
    color: #3e7048;
    font-size: 12px;
    font-weight: 600;
}

.contact-section {
    padding: 12px 13px;
    border-bottom: 1px solid #edf0eb;
}

.contact-item {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin-bottom: 10px;
    color: #647166;
    font-size: 13px;
    line-height: 1.5;
    overflow-wrap: anywhere;
}

.contact-item:last-child {
    margin-bottom: 0;
}

.contact-icon {
    width: 13px;
    min-width: 13px;
    margin-top: 2px;
    color: #397348;
}

.status-box {
    margin: 12px;
    padding: 12px;
    border-radius: 6px;
    background: #f1f7ef;
}

.status-title {
    margin-bottom: 8px;
    color: #536456;
    font-size: 13px;
    font-weight: 700;
}

.active-status {
    display: flex;
    align-items: center;
    gap: 6px;
    color: #327345;
    font-size: 13px;
    font-weight: 600;
}

.status-dot {
    width: 6px;
    height: 6px;
    border-radius: 50%;
    background: #49a765;
}

/* ==================== MAIN PROFILE CARD ==================== */

.profile-main-card {
    min-width: 0;
    padding: 17px;
    background: #fff;
    border: 1px solid #e7ebe5;
    border-radius: 7px;
}

.profile-tabs {
    display: flex;
    align-items: center;
    gap: 22px;
    margin-bottom: 17px;
    border-bottom: 1px solid #e4e9e1;
}

.profile-tab {
    position: relative;
    padding: 0 2px 12px;
    display: flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: 0;
    border-bottom: 2px solid transparent;
    color: #69766b;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}

.profile-tab.active {
    border-bottom-color: #28623b;
    color: #285e38;
}

.section-title {
    margin: 0 0 5px;
    color: #35483a;
    font-size: 17px;
    font-weight: 700;
}

.section-subtitle {
    margin: 0 0 16px;
    color: #7b877d;
    font-size: 13px;
    line-height: 1.5;
}

/* ==================== PROFILE FORM ==================== */

.form-grid {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 16px 18px;
}

.form-group {
    min-width: 0;
}

.form-group.third-width {
    grid-column: span 2;
}

.form-group.half-width {
    grid-column: span 3;
}

.form-label {
    display: block;
    margin-bottom: 5px;
    color: #506054;
    font-size: 13px;
    font-weight: 700;
}

.required {
    color: #cf5149;
}

.form-input {
    display: block;
    width: 100%;
      min-width: 0;
    box-sizing: border-box;
    height: 37px;
    padding: 0 10px;
    border: 1px solid #dfe5dd;
    border-radius: 4px;
    outline: none;
    background: #fff;
    color: #455448;
    font-size: 13px;
    transition: border-color .2s, box-shadow .2s;
}

.form-input:focus {
    border-color: #6c9871;
    box-shadow: 0 0 0 2px rgba(70, 125, 79, .09);
}

.readonly-input {
    background: #f1f3f0;
    color: #7c867e;
    cursor: not-allowed;
}

.button-row {
    display: flex;
    justify-content: flex-end;
    margin-top: 15px;
}

.save-button {
    min-height: 37px;
    padding: 0 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 7px;
    border: 0;
    border-radius: 4px;
    background: #245b35;
    color: #fff;
    font-size: 13px;
    font-weight: 700;
    cursor: pointer;
    transition: background .2s;
}

.save-button:hover {
    background: #194729;
}

/* SECURITY NOTE */
.security-note {
    margin-top: 15px;
    padding: 16px 18px;
    display: block;
    width: 100%;
    box-sizing: border-box;
    border: 1px solid #dce9d7;
    border-radius: 10px;
    background: #f4f8f1;
    color: #476354;
    font-size: 13px;
    line-height: 1.6;
}

/* INSTRUCTION */
.security-note-heading {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #476354;
    font-size: 13px;
    line-height: 1.6;
}

.security-note-heading > i {
    flex-shrink: 0;
    color: #247047;
    font-size: 16px;
}

/* PASSWORD CHECKLIST */
.security-note .password-requirements {
    display: grid;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    align-items: stretch;
    width: 100%;
    box-sizing: border-box;
    margin-top: 22px;
    padding: 0;
    row-gap: 22px;
}

/* EACH REQUIREMENT */
.security-note .password-requirement {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    min-width: 0;
    width: 100%;
    box-sizing: border-box;
    padding: 4px 18px;
    font-size: 14px;
    line-height: 1.5;
    white-space: nowrap;
}

/* LARGER STATUS ICONS */
.security-note .password-requirement i {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
    margin: 0;
    font-size: 20px;
}

/* FIRST ROW: THREE EVENLY SPACED ITEMS */
.security-note .password-requirement:nth-child(1) {
    grid-column: 1 / 3;
    border-right: 2px solid #dce9d7;
}

.security-note .password-requirement:nth-child(2) {
    grid-column: 3 / 5;
    border-right: 2px solid #dce9d7;
}

.security-note .password-requirement:nth-child(3) {
    grid-column: 5 / 7;
}

/* SECOND ROW: TWO ITEMS CENTERED */
.security-note .password-requirement:nth-child(4) {
    grid-column: 2 / 4;
    border-right: 2px solid #dce9d7;
}

.security-note .password-requirement:nth-child(5) {
    grid-column: 4 / 6;
}

/* INCOMPLETE REQUIREMENTS */
.security-note .password-requirement.invalid {
    color: #c94f4f;
}

.security-note .password-requirement.invalid i {
    color: #c94f4f;
}

/* COMPLETED REQUIREMENTS */
.security-note .password-requirement.valid {
    color: #247047;
}

.security-note .password-requirement.valid i {
    color: #247047;
}

/* MOBILE LAYOUT */
@media (max-width: 700px) {
    .security-note .password-requirements {
        grid-template-columns: repeat(2, minmax(0, 1fr));
        row-gap: 12px;
        margin-top: 16px;
    }

    .security-note .password-requirement {
        grid-column: auto !important;
        justify-content: flex-start;
        padding: 4px 6px;
        font-size: 12px;
        white-space: normal;
        overflow-wrap: anywhere;
        border-right: none !important;
    }

    .security-note .password-requirement:nth-child(5) {
        grid-column: 1 / -1 !important;
    }
}



/* ==================== PASSWORD CONTROLS ==================== */

.password-wrapper {
    position: relative;
}

.password-input {
    padding-right: 36px;
}

.toggle-password {
    position: absolute;
    top: 50%;
    right: 10px;
    transform: translateY(-50%);
    padding: 4px;
    border: 0;
    background: transparent;
    color: #778579;
    cursor: pointer;
}

/* ==================== TABS AND ALERTS ==================== */

.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
}

.alert {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    margin: 12px 14px 0;
    padding: 10px 12px;
    border-radius: 5px;
    font-size: 10px;
    line-height: 1.5;
}

.alert-success {
    border: 1px solid #c9e5ce;
    background: #edf8ef;
    color: #2e7140;
}

.alert-error {
    border: 1px solid #edceca;
    background: #fff1ef;
    color: #a53f38;
}

/* =========================================================
   FOOTER
========================================================= */

.customer-footer {
    width: 100%;

    min-height: 68px;

    background: #0d4828;

    color: #ffffff;

    display: flex;
    align-items: center;

    padding: 0 48px;
}

.customer-footer-inner {
    width: 100%;

    max-width: 1740px;

    margin: 0 auto;

    display: flex;
    align-items: center;
    justify-content: space-between;
}

.customer-footer-left {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-logo {
    width: 110px;

    padding-right: 15px;

    border-right: 1px solid rgba(255,255,255,0.22);
}

.customer-footer-logo img {
    width: 100px;
    height: auto;

    display: block;
}

.customer-footer-tagline {
    font-size: 11px;

    color: rgba(255,255,255,0.9);
}

.customer-footer-right {
    display: flex;
    align-items: center;

    gap: 18px;
}

.customer-footer-social {
    display: flex;
    align-items: center;

    gap: 17px;
}

.customer-footer-social a {
    color: #ffffff;

    font-size: 13px;

    text-decoration: none;

    opacity: 0.95;

    transition: opacity 0.2s ease;
}

.customer-footer-social a:hover {
    opacity: 0.7;
}

.customer-footer-copy {
    font-size: 11px;

    color: rgba(255,255,255,0.82);
}
/* ==================== RESPONSIVE ==================== */

@media (min-width: 1500px) {
    .main-content {
        padding-top: 24px;
    }

    .profile-layout {
        grid-template-columns: 280px minmax(0, 1fr);
        gap: 18px;
        padding: 18px;
    }

    .profile-main-card {
        padding: 22px;
    }
}

@media (max-width: 900px) {
    .customer-navbar {
        padding: 0 20px;
    }

    .customer-navbar-logo {
        width: auto;
    }

    .customer-navbar-center {
        gap: 18px;
    }

    .customer-navbar-right {
        min-width: auto;
    }

    .profile-layout {
        grid-template-columns: 220px minmax(0, 1fr);
        padding: 10px;
        gap: 10px;
    }

    .profile-main-card {
        padding: 14px;
    }

    .customer-footer-inner {
        flex-wrap: wrap;
    }
}

@media (max-width: 680px) {
    .customer-navbar {
        height: auto;
        min-height: 60px;
        padding: 10px 14px;
        flex-wrap: wrap;
        gap: 8px;
    }

    .customer-navbar-logo img {
        width: 85px;
    }

    .customer-navbar-center {
        order: 3;
        width: 100%;
        height: 35px;
        justify-content: space-around;
        gap: 12px;
    }

    .customer-nav-link {
        font-size: 10px;
    }

    .customer-nav-link.active::after {
        bottom: 1px;
    }

    .customer-account-details,
    .customer-dropdown-icon {
        display: none;
    }

    .customer-navbar-right {
        gap: 8px;
    }

    .customer-notification-dropdown {
        position: fixed;
        top: 65px;
        left: 12px;
        right: 12px;
        width: auto;
        max-width: none;
    }

    .profile-banner {
        min-height: 80px;
        padding: 16px 18px;
    }

    .profile-banner h1 {
        font-size: 18px;
    }

    .main-content {
        padding: 12px;
    }

    .profile-layout {
        grid-template-columns: minmax(0, 1fr);
        padding: 10px;
    }

    .profile-sidebar-card {
        display: grid;
        grid-template-columns: 1fr 1fr;
    }

    .profile-user-section {
        grid-row: span 2;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }

    .contact-section {
        border-bottom: 1px solid #edf0eb;
    }

    .status-box {
        align-self: start;
    }

    .profile-main-card {
        padding: 13px;
    }

    .profile-tabs {
        gap: 14px;
        overflow-x: auto;
    }

    .profile-tab {
        font-size: 9px;
    }

    .form-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 12px;
    }

    .form-group.full-width {
        grid-column: auto;
    }

    .customer-footer {
        padding: 14px;
    }

    .customer-footer-inner,
    .customer-footer-left,
    .customer-footer-right {
        flex-direction: column;
        align-items: flex-start;
    }

    .customer-footer-left,
    .customer-footer-right {
        gap: 10px;
    }

    .customer-footer-logo {
        border-right: 0;
        padding-right: 0;
    }
}

@media (max-width: 380px) {
    .profile-sidebar-card {
        grid-template-columns: 1fr;
    }

    .profile-user-section {
        grid-row: auto;
    }

    .profile-tabs {
        gap: 10px;
    }

    .profile-tab {
        white-space: normal;
        text-align: left;
    }
}

/* =========================================
   PROFILE PAGE — LARGE OUTER CARD
========================================= */

.main-content {
    width: 100%;
    min-height: 0;
    padding: 24px 25px 26px;
    box-sizing: border-box;
    background: #ffffff;
}

.profile-page {
    width: calc(100% - 60px);
    max-width: 1740px;
    margin: 14px auto 18px;
}

.profile-outer-card {
    width: 100%;
    overflow: hidden;
    background: #ffffff;
    border: 1px solid #dfe5e1;
    border-radius: 26px;
    box-shadow: 0 8px 24px rgba(35, 55, 38, 0.08);
}

/* BANNER BUILT INTO THE OUTER CARD */
.profile-banner {
    width: 100%;
    min-height: 100px;
    padding: 20px 5%;
    display: flex;
    align-items: center;
    background: url("profile.jpg") center 48% / cover no-repeat;
    border: 0;
    border-bottom: 1px solid #e0e8da;
    border-radius: 0;
}

.profile-banner-content {
    width: 100%;
    max-width: 1450px;
    margin: 0 auto;
}

/* EXISTING PROFILE LAYOUT */
.profile-layout {
    display: grid;
    grid-template-columns: 300px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
    padding: 25px;
}

/* TABLET */
@media (max-width: 900px) {
    .profile-page {
        width: calc(100% - 36px);
        margin: 12px auto 16px;
    }

    .main-content {
        padding: 18px;
    }

    .profile-layout {
        grid-template-columns: 1fr;
    }
}

/* MOBILE */
@media (max-width: 680px) {
    .profile-page {
        width: calc(100% - 20px);
        margin: 10px auto 14px;
    }

    .profile-outer-card {
        border-radius: 18px;
    }

    .profile-banner {
        min-height: 80px;
        padding: 16px 18px;
    }

    .main-content {
        padding: 12px;
    }

    .profile-layout {
        padding: 10px;
    }
}


.profile-sidebar-card,
.profile-main-card {
    min-width: 0;
    box-sizing: border-box;
}

.profile-sidebar-card {
    height: auto;
}

.profile-main-card {
    height: auto;
    padding: 25px;
}

.form-input {
    box-sizing: border-box;
    max-width: 100%;
}

@media (max-width: 900px) {
    .main-content {
        padding: 18px;
    }

    .profile-layout {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 650px) {
    .main-content {
        padding: 12px;
    }

    .profile-layout {
        padding: 15px;
    }

    .profile-main-card {
        padding: 16px;
    }

    .customer-navbar {
        flex-wrap: wrap;
    }
}


/* =========================================================
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

.customer-account-wrapper {
    position: relative;
    display: flex;
    align-items: center;
    gap: 8px;
}

.customer-account-toggle {
    width: 28px;
    height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    border: 0;
    border-radius: 50%;
    background: transparent;
    color: #eaf2e8;
    font-size: 11px;
    cursor: pointer;
    transition: background 0.2s ease, color 0.2s ease;
}

.customer-account-toggle:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.customer-account-dropdown {
    position: absolute;
    top: calc(100% + 12px);
    right: 0;
    width: 180px;
    padding: 7px;
    background: #fff;
    border: 1px solid #e0e5df;
    border-radius: 12px;
    box-shadow: 0 12px 30px rgba(30, 50, 35, 0.16);
    z-index: 1200;
}

.customer-account-dropdown.hidden {
    display: none;
}

.customer-logout-option {
    width: 100%;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 11px 12px;
    border: 0;
    border-radius: 8px;
    background: #fff;
    color: #a33b35;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    font-weight: 700;
    text-align: left;
    cursor: pointer;
    transition: background 0.2s ease;
}

.customer-logout-option:hover {
    background: #fff0ee;
}

.customer-logout-option i {
    width: 16px;
    font-size: 13px;
}

/* =========================================================
   CUSTOMER LOGOUT CONFIRMATION MODAL
========================================================= */

.customer-logout-overlay {
    position: fixed;
    inset: 0;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    background: rgba(15, 32, 20, 0.48);
    z-index: 3000;
}

.customer-logout-overlay.hidden {
    display: none;
}

.customer-logout-modal {
    width: 100%;
    max-width: 390px;
    padding: 30px 28px 24px;
    background: #fff;
    border: 1px solid #e5e9e3;
    border-radius: 18px;
    box-shadow: 0 24px 70px rgba(0, 0, 0, 0.22);
    text-align: center;
    animation: logoutModalAppear 0.22s ease;
}

.customer-logout-icon {
    width: 58px;
    height: 58px;
    margin: 0 auto 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    background: #fff0ee;
    color: #b6423b;
    font-size: 23px;
}

.customer-logout-modal h2 {
    margin: 0 0 9px;
    color: #2f4937;
    font-size: 20px;
    font-weight: 700;
}

.customer-logout-modal p {
    margin: 0;
    color: #7b877d;
    font-size: 13px;
    line-height: 1.6;
}

.customer-logout-actions {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    margin-top: 25px;
}

.customer-logout-actions form {
    margin: 0;
}

.customer-logout-cancel,
.customer-logout-confirm {
    min-width: 115px;
    height: 40px;
    padding: 0 16px;
    border-radius: 9px;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 12px;
    font-weight: 700;
    cursor: pointer;
    transition: background 0.2s ease, transform 0.2s ease;
}

.customer-logout-cancel {
    border: 1px solid #dce3dc;
    background: #fff;
    color: #58665b;
}

.customer-logout-cancel:hover {
    background: #f4f6f3;
}

.customer-logout-confirm {
    border: 1px solid #a83b35;
    background: #b6423b;
    color: #fff;
}

.customer-logout-confirm:hover {
    background: #9e342f;
    transform: translateY(-1px);
}

@keyframes logoutModalAppear {
    from {
        opacity: 0;
        transform: translateY(8px) scale(.98);
    }

    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}
/* =========================================================
   SECURITY SETTINGS — THREE EQUAL PASSWORD FIELDS
========================================================= */

#security .form-grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    align-items: start;
}

#security .form-group,
#security .password-wrapper {
    width: 100%;
    min-width: 0;
    box-sizing: border-box;
}

#security .password-wrapper {
    display: flex;
    align-items: center;
    position: relative;
}

#security .password-input {
    width: 100%;
    min-width: 0;
    height: 46px;
    box-sizing: border-box;
    padding-right: 42px;
}

#security .toggle-password {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
}

</style>


    <link rel="stylesheet" href="customer_navbar.css?v=20261022_viewall">
</head>

<body>


<!-- CUSTOMER NAVIGATION -->
<!-- =========================================================
     CUSTOMER NAVBAR (STANDARDIZED)
========================================================= -->
<?php require 'customer_header.php'; ?>

<!-- PROFILE PAGE OUTER CARD -->
<div class="profile-page">

    <div class="profile-outer-card">

        <!-- PROFILE BANNER BUILT INTO OUTER CARD -->
        <section class="profile-banner">
            <div class="profile-banner-content">
                <h1>
                    <i class="fa-solid fa-user-gear"></i>
                    Manage Your Account
                </h1>
                <p>Update your personal information and keep your profile secure.</p>
            </div>
        </section>

        <!-- MAIN PROFILE CONTENT -->
        <div class="main-content">

  


        


            <!-- SUCCESS MESSAGE -->
            <?php if (!empty($success_msg)) { ?>

                <div class="alert alert-success">

                    <i class="fa-solid fa-circle-check"></i>

                    <?php
                    echo htmlspecialchars($success_msg);
                    ?>

                </div>

            <?php } ?>


            <!-- ERROR MESSAGE -->
            <?php if (!empty($error_msg)) { ?>

                <div class="alert alert-error">

                    <i class="fa-solid fa-circle-xmark"></i>

                    <?php
                    echo htmlspecialchars($error_msg);
                    ?>

                </div>

            <?php } ?>


            <!-- PROFILE LAYOUT -->
            <div class="profile-layout">


                <!-- CUSTOMER PROFILE CARD -->
                <div class="profile-sidebar-card">

                    <div class="profile-user-section">

                        <div class="profile-avatar-wrapper">

                            <img
                                src="<?php echo htmlspecialchars($avatar_src); ?>"
                                id="avatarPreview"
                                class="profile-avatar"
                                alt="Profile Picture"
                            >

                            <label
                                for="profilePhotoInput"
                                class="edit-photo-button"
                            >

                                <i class="fa-solid fa-camera"></i>

                            </label>

                        </div>


                        <div class="profile-name">

                            <?php
                            echo htmlspecialchars($db_fullname);
                            ?>

                        </div>


                        <span class="role-badge">
                            Customer
                        </span>

                    </div>


                    <!-- CONTACT INFORMATION -->
                    <div class="contact-section">

                        <div class="contact-item">

                            <i
                                class="fa-solid fa-envelope contact-icon"
                            ></i>

                            <span>

                                <?php
                                echo htmlspecialchars($db_email);
                                ?>

                            </span>

                        </div>


                        <div class="contact-item">

                            <i
                                class="fa-solid fa-phone contact-icon"
                            ></i>

                            <span>

                                <?php

                                if (!empty($db_phone)) {

                                    echo htmlspecialchars($db_phone);

                                } else {

                                    echo "No phone number";

                                }

                                ?>

                            </span>

                        </div>
                        <!-- CUSTOMER ADDRESS -->
                        <div class="contact-item profile-address-item">

                            <i class="fa-solid fa-location-dot contact-icon"></i>

                            <span>
                                <?php
                                if (!empty($db_address)) {
                                    echo htmlspecialchars(
                                        $db_address,
                                        ENT_QUOTES,
                                        'UTF-8'
                                    );
                                } else {
                                    echo "No address provided";
                                }
                                ?>
                            </span>

                        </div>
                    </div>


                    <!-- ACCOUNT STATUS -->
                    <div class="status-box">

                        <div class="status-title">
                            Account Status
                        </div>

                        <div class="active-status">

                            <span class="status-dot"></span>

                            Active

                        </div>

                    </div>

                </div>


                <!-- PROFILE INFORMATION CARD -->
                <div class="profile-main-card">


                    <!-- TABS -->
                    <div class="profile-tabs">

                        <button
                            type="button"
                            class="profile-tab active"
                            data-tab="personal"
                        >

                            <i class="fa-solid fa-user"></i>

                            Personal Information

                        </button>


                        <button
                            type="button"
                            class="profile-tab"
                            data-tab="security"
                        >

                            <i class="fa-solid fa-shield-halved"></i>

                            Security Settings

                        </button>

                    </div>


                    <!-- PERSONAL INFORMATION TAB -->
                    <div
                        class="tab-content active"
                        id="personal"
                    >

                        <form
                            method="POST"
                            enctype="multipart/form-data"
                        >

                            <!-- HIDDEN PROFILE PHOTO INPUT -->
                            <input
                                type="file"
                                name="profile_photo"
                                id="profilePhotoInput"
                                accept="image/*"
                                style="display:none;"
                            >


                            <h2 class="section-title">
                                Personal Information
                            </h2>


                            <p class="section-subtitle">
                                Update your personal and contact information.
                            </p>


                            <div class="form-grid">


                                <!-- FULL NAME -->
                              <div class="form-group third-width">

                                    <label class="form-label">

                                        Full Name

                                        <span class="required">
                                            *
                                        </span>

                                    </label>


                                    <input
                                        type="text"
                                        name="fullname"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($db_fullname); ?>"
                                        required
                                    >

                                </div>


                                <!-- EMAIL -->
                               <div class="form-group third-width">

                                    <label class="form-label">

                                        Email Address

                                        <span class="required">
                                            *
                                        </span>

                                    </label>


                                    <input
                                        type="email"
                                        name="email"
                                        class="form-input"
                                        value="<?php echo htmlspecialchars($db_email); ?>"
                                        required
                                    >

                                </div>


                                <!-- PHONE -->
                               <div class="form-group third-width">
                                    <label class="form-label">
                                        Phone Number
                                    </label>


                                    <input
                                        type="text"
                                        name="phone"
                                        class="form-input"
                                        placeholder="Enter phone number"
                                        value="<?php echo htmlspecialchars($db_phone); ?>"
                                    >

                                </div>

                                <!-- ADDRESS -->
                              <div class="form-group half-width">

                                    <label class="form-label">
                                        Address
                                    </label>


                                    <input
                                        type="text"
                                        name="address"
                                        class="form-input"
                                        placeholder="Enter your address"
                                        value="<?php echo htmlspecialchars($db_address); ?>"
                                    >

                                </div>

                                <!-- ROLE -->
                               <div class="form-group half-width">
                                    <label class="form-label">
                                        Role
                                    </label>


                                    <input
                                        type="text"
                                        class="form-input readonly-input"
                                        value="Customer"
                                        readonly
                                    >

                                </div>



                            </div>


                            <!-- SAVE BUTTON -->
                            <div class="button-row">

                                <button
                                    type="submit"
                                    name="save_profile_changes"
                                    class="save-button"
                                >

                                    <i class="fa-solid fa-floppy-disk"></i>

                                    Save Changes

                                </button>

                            </div>


                            <!-- SECURITY NOTE -->
                            <div class="security-note">

                                <i
                                    class="fa-solid fa-circle-info"
                                ></i>

                                Your profile information is securely managed
                                and only authorized personnel can access it.

                            </div>

                        </form>

                    </div>


                    <!-- SECURITY SETTINGS TAB -->
                    <div
                        class="tab-content"
                        id="security"
                    >

                        <form method="POST">


                            <h2 class="section-title">
                                Security Settings
                            </h2>


                            <p class="section-subtitle">
                                Update your account password to keep your account secure.
                            </p>


                            <div class="form-grid">


                                <!-- CURRENT PASSWORD -->
                             <div class="form-group">

                                    <label class="form-label">

                                        Current Password

                                        <span class="required">
                                            *
                                        </span>

                                    </label>


                                    <div class="password-wrapper">

                                        <input
                                            type="password"
                                            name="current_password"
                                            class="form-input password-input"
                                            required
                                        >


                                        <button
                                            type="button"
                                            class="toggle-password"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                        </button>

                                    </div>

                                </div>


                                <!-- NEW PASSWORD -->
                                <div class="form-group">

                                    <label class="form-label">

                                        New Password

                                        <span class="required">
                                            *
                                        </span>

                                    </label>


                                    <div class="password-wrapper">

                                        <input
                                            type="password"
                                            id="newPassword"
                                            name="new_password"
                                            class="form-input password-input"
                                            minlength="8"
                                            required
                                        >


                                        <button
                                            type="button"
                                            class="toggle-password"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                        </button>

                                    </div>

                                </div>


                                <!-- CONFIRM PASSWORD -->
                                <div class="form-group">

                                    <label class="form-label">

                                        Confirm New Password

                                        <span class="required">
                                            *
                                        </span>

                                    </label>


                                    <div class="password-wrapper">

                                      
<input
    type="password"
    id="confirmPassword"
    name="confirm_password"
    class="form-input password-input"
    minlength="8"
    required
    disabled
>



                                        <button
                                            type="button"
                                            class="toggle-password"
                                        >

                                            <i class="fa-solid fa-eye"></i>

                                        </button>

                                    </div>


                                    <small
                                        id="passwordMessage"
                                        style="
                                            display:block;
                                            margin-top:7px;
                                            font-size:11px;
                                        "
                                    ></small>

                                </div>

                            </div>


                            <!-- SECURITY INFORMATION -->
                          
<!-- SECURITY INFORMATION -->
<div class="security-note">

    <div class="security-note-heading">
        <i class="fa-solid fa-shield-halved"></i>

        <span>
            Use a strong password with at least
            8 characters to help protect your account.
        </span>
    </div>

    <!-- PASSWORD REQUIREMENTS -->
    <div class="password-requirements" id="passwordRequirements">

        <div class="password-requirement" id="reqLength">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>At least 8 characters long.</span>
        </div>

        <div class="password-requirement" id="reqUppercase">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Includes one uppercase letter (A–Z).</span>
        </div>

        <div class="password-requirement" id="reqLowercase">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Includes one lowercase letter (a–z).</span>
        </div>

        <div class="password-requirement" id="reqNumber">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Includes one number (0–9).</span>
        </div>

        <div class="password-requirement" id="reqSpecial">
            <i class="fa-solid fa-circle-xmark"></i>
            <span>Includes one special character (@, #, !).</span>
        </div>

    </div>

</div>


                            <!-- UPDATE PASSWORD BUTTON -->
                            <div class="button-row">

                                <button
                                    type="submit"
                                    name="change_password"
                                    class="save-button"
                                >

                                    <i class="fa-solid fa-lock"></i>

                                    Update Password

                                </button>

                            </div>

                        </form>

                    </div>


                </div>

            </div>

        </div>
        <!-- END OUTER CARD -->

    </div>

</div>

<!-- =========================================================
     FOOTER
========================================================= -->

<footer class="customer-footer">


    <div class="customer-footer-inner">


        <!-- LEFT -->

        <div class="customer-footer-left">


            <div class="customer-footer-logo">

                <img
                    src="vdvclogoo.png"
                    alt="VDVC Egg Farm"
                >

            </div>


            <div class="customer-footer-tagline">

                Fresh Eggs. Trusted Farm. Better for You.

            </div>


        </div>



        <!-- RIGHT -->

        <div class="customer-footer-right">


            <div class="customer-footer-social">

                <a
                    href="#"
                    aria-label="Facebook"
                >
                    <i class="fa-brands fa-facebook-f"></i>
                </a>


                <a
                    href="#"
                    aria-label="Instagram"
                >
                    <i class="fa-brands fa-instagram"></i>
                </a>


                <a
                    href="#"
                    aria-label="YouTube"
                >
                    <i class="fa-brands fa-youtube"></i>
                </a>

            </div>


            <div class="customer-footer-copy">

                © 2026 VDVC Egg Farm.
                All rights reserved.

            </div>


        </div>


    </div>

</footer>



<script>


function updateCustomerDateTime() {

    var now = new Date();

    var options = {
        month: 'long',
        day: 'numeric',
        year: 'numeric'
    };

    var dateText = now.toLocaleDateString(
        'en-US',
        options
    );

    var timeText = now.toLocaleTimeString(
        'en-US',
        {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: true
        }
    );

    var dateElement = document.getElementById(
        'customer-live-date'
    );

    var timeElement = document.getElementById(
        'customer-live-time'
    );

    if (dateElement) {
        dateElement.textContent = dateText;
    }

    if (timeElement) {
        timeElement.textContent = timeText;
    }
}

updateCustomerDateTime();

setInterval(
    updateCustomerDateTime,
    1000
);
/*
|--------------------------------------------------------------------------
| TAB SWITCHING
|--------------------------------------------------------------------------
*/

var tabs =
    document.querySelectorAll(
        ".profile-tab"
    );


var tabContents =
    document.querySelectorAll(
        ".tab-content"
    );


for (
    var i = 0;
    i < tabs.length;
    i++
) {

    tabs[i].addEventListener(
        "click",
        function () {

            var target =
                this.getAttribute(
                    "data-tab"
                );


            for (
                var x = 0;
                x < tabs.length;
                x++
            ) {

                tabs[x].classList.remove(
                    "active"
                );

            }


            for (
                var y = 0;
                y < tabContents.length;
                y++
            ) {

                tabContents[y].classList.remove(
                    "active"
                );

            }


            this.classList.add(
                "active"
            );


            document
                .getElementById(target)
                .classList.add(
                    "active"
                );

        }
    );

}


/*
|--------------------------------------------------------------------------
| PASSWORD VISIBILITY
|--------------------------------------------------------------------------
*/

var toggleButtons =
    document.querySelectorAll(
        ".toggle-password"
    );


for (
    var i = 0;
    i < toggleButtons.length;
    i++
) {

    toggleButtons[i].addEventListener(
        "click",
        function () {

            var input =
                this.parentNode.querySelector(
                    "input"
                );


            var icon =
                this.querySelector(
                    "i"
                );


            if (
                input.type ==
                "password"
            ) {

                input.type =
                    "text";


                icon.classList.remove(
                    "fa-eye"
                );


                icon.classList.add(
                    "fa-eye-slash"
                );

            } else {

                input.type =
                    "password";


                icon.classList.remove(
                    "fa-eye-slash"
                );


                icon.classList.add(
                    "fa-eye"
                );

            }

        }
    );

}


/*
|--------------------------------------------------------------------------
| PASSWORD STRENGTH AND MATCHING
|--------------------------------------------------------------------------
*/

var newPassword = document.getElementById("newPassword");
var confirmPassword = document.getElementById("confirmPassword");
var passwordMessage = document.getElementById("passwordMessage");

var passwordRequirements = {
    length: document.getElementById("reqLength"),
    uppercase: document.getElementById("reqUppercase"),
    lowercase: document.getElementById("reqLowercase"),
    number: document.getElementById("reqNumber"),
    special: document.getElementById("reqSpecial")
};

function updatePasswordRequirement(element, isValid) {
    if (!element) {
        return;
    }

    var icon = element.querySelector("i");

    if (isValid) {
        element.classList.add("valid");
        element.classList.remove("invalid");

        if (icon) {
            icon.classList.remove("fa-circle-xmark");
            icon.classList.add("fa-circle-check");
        }
    } else {
        element.classList.remove("valid");
        element.classList.add("invalid");

        if (icon) {
            icon.classList.remove("fa-circle-check");
            icon.classList.add("fa-circle-xmark");
        }
    }
}

function checkPasswords() {
    if (!newPassword || !confirmPassword) {
        return;
    }

    if (!passwordMessage) {
        return;
    }

    if (confirmPassword.disabled) {
        passwordMessage.innerHTML =
            "Complete all password requirements first.";
        passwordMessage.style.color = "#777";
        confirmPassword.setCustomValidity("");
        return;
    }

    if (confirmPassword.value === "") {
        passwordMessage.innerHTML = "";
        confirmPassword.setCustomValidity("");
        return;
    }

    if (newPassword.value === confirmPassword.value) {
        passwordMessage.innerHTML = "Passwords match.";
        passwordMessage.style.color = "#2f7a4b";
        confirmPassword.setCustomValidity("");
    } else {
        passwordMessage.innerHTML = "Passwords do not match.";
        passwordMessage.style.color = "#c94f4f";
        confirmPassword.setCustomValidity("Passwords must match.");
    }
}

function checkPasswordStrength() {
    if (!newPassword || !confirmPassword) {
        return;
    }

    var password = newPassword.value;

    var hasLength = password.length >= 8;
    var hasUppercase = /[A-Z]/.test(password);
    var hasLowercase = /[a-z]/.test(password);
    var hasNumber = /[0-9]/.test(password);
    var hasSpecial = /[^A-Za-z0-9\s]/.test(password);

    updatePasswordRequirement(
        passwordRequirements.length, hasLength
    );

    updatePasswordRequirement(
        passwordRequirements.uppercase, hasUppercase
    );

    updatePasswordRequirement(
        passwordRequirements.lowercase, hasLowercase
    );

    updatePasswordRequirement(
        passwordRequirements.number, hasNumber
    );

    updatePasswordRequirement(
        passwordRequirements.special, hasSpecial
    );

    var isStrong =
        hasLength &&
        hasUppercase &&
        hasLowercase &&
        hasNumber &&
        hasSpecial;

    if (isStrong) {
        confirmPassword.disabled = false;
    } else {
        confirmPassword.disabled = true;
        confirmPassword.value = "";
        confirmPassword.setCustomValidity("");

        if (passwordMessage) {
            passwordMessage.innerHTML =
                "Complete all password requirements first.";
            passwordMessage.style.color = "#777";
        }
    }

    checkPasswords();
}

if (newPassword && confirmPassword) {
    confirmPassword.disabled = true;

    newPassword.addEventListener(
        "input",
        checkPasswordStrength
    );

    confirmPassword.addEventListener(
        "input",
        checkPasswords
    );

    checkPasswordStrength();
}


/*
|--------------------------------------------------------------------------
| PROFILE IMAGE PREVIEW
|--------------------------------------------------------------------------
*/

var profilePhotoInput =
    document.getElementById(
        "profilePhotoInput"
    );


var avatarPreview =
    document.getElementById(
        "avatarPreview"
    );


if (profilePhotoInput) {

    profilePhotoInput.addEventListener(
        "change",
        function () {

            var file =
                this.files[0];


            if (file) {

                var reader =
                    new FileReader();


                reader.onload =
                    function (event) {

                        avatarPreview.src =
                            event.target.result;

                    };


                reader.readAsDataURL(
                    file
                );

            }

        }
    );

}

/* =========================================================
   CUSTOMER NOTIFICATION DROPDOWN
========================================================= */

var customerNotificationDropdown = null;
var isCustomerDropdownOpen = false;

function toggleCustomerNotificationDropdown()
{
    if (!customerNotificationDropdown) {
        customerNotificationDropdown = document.getElementById('customerNotificationDropdown');
    }

    if (customerNotificationDropdown) {
        isCustomerDropdownOpen = !isCustomerDropdownOpen;

        if (isCustomerDropdownOpen) {
            customerNotificationDropdown.classList.remove('hidden');
        } else {
            customerNotificationDropdown.classList.add('hidden');
        }
    }
}

function closeCustomerNotificationDropdown()
{
    if (customerNotificationDropdown && isCustomerDropdownOpen) {
        customerNotificationDropdown.classList.add('hidden');
        isCustomerDropdownOpen = false;
    }
}

// Close dropdown when clicking outside
document.addEventListener('click', function(event) {
    if (customerNotificationDropdown && isCustomerDropdownOpen) {
        var notificationButton = document.querySelector('.customer-top-notification');
        var notificationContainer = customerNotificationDropdown.parentElement;
        if (!notificationContainer.contains(event.target)) {
            closeCustomerNotificationDropdown();
        }
    }
});

// Mark All as Read function for dropdown header
function markAllCustomerAsRead(event)
{
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var formData = new FormData();
    formData.append('mark_all_read', '1');
    formData.append('ajax', '1');

    fetch('customer_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            // Reload page to update all notification states
            window.location.reload();
        }
    })
    .catch(function(error) {
        console.error('Failed to mark all notifications as read:', error);
    });
}

/* =========================================================
   CUSTOMER ACCOUNT DROPDOWN
========================================================= */

function toggleCustomerAccountDropdown(event) {
    if (event) {
        event.preventDefault();
        event.stopPropagation();
    }

    var dropdown = document.getElementById(
        'customerAccountDropdown'
    );

    var toggle = document.getElementById(
        'customerAccountToggle'
    );

    if (!dropdown || !toggle) {
        return;
    }

    var isOpen = !dropdown.classList.contains('hidden');

    if (isOpen) {
        dropdown.classList.add('hidden');
        toggle.setAttribute('aria-expanded', 'false');
    } else {
        dropdown.classList.remove('hidden');
        toggle.setAttribute('aria-expanded', 'true');
    }
}


/* =========================================================
   CUSTOMER LOGOUT CONFIRMATION
========================================================= */

function openCustomerLogoutModal() {
    var dropdown = document.getElementById(
        'customerAccountDropdown'
    );

    var toggle = document.getElementById(
        'customerAccountToggle'
    );

    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (dropdown) {
        dropdown.classList.add('hidden');
    }

    if (toggle) {
        toggle.setAttribute('aria-expanded', 'false');
    }

    if (modal) {
        modal.classList.remove('hidden');
    }
}


function closeCustomerLogoutModal() {
    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (modal) {
        modal.classList.add('hidden');
    }
}


/* =========================================================
   CLOSE ACCOUNT DROPDOWN WHEN CLICKING OUTSIDE
========================================================= */

document.addEventListener('click', function(event) {
    var wrapper = document.querySelector(
        '.customer-account-wrapper'
    );

    var dropdown = document.getElementById(
        'customerAccountDropdown'
    );

    var toggle = document.getElementById(
        'customerAccountToggle'
    );

    if (
        wrapper &&
        dropdown &&
        !wrapper.contains(event.target)
    ) {
        dropdown.classList.add('hidden');

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }
    }
});


/* =========================================================
   CLOSE LOGOUT MODAL WHEN CLICKING OUTSIDE
========================================================= */

document.addEventListener('click', function(event) {
    var modal = document.getElementById(
        'customerLogoutModal'
    );

    if (
        modal &&
        !modal.classList.contains('hidden') &&
        event.target === modal
    ) {
        closeCustomerLogoutModal();
    }
});


/* =========================================================
   CLOSE MENUS WITH ESCAPE KEY
========================================================= */

document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        var dropdown = document.getElementById(
            'customerAccountDropdown'
        );

        var toggle = document.getElementById(
            'customerAccountToggle'
        );

        if (dropdown) {
            dropdown.classList.add('hidden');
        }

        if (toggle) {
            toggle.setAttribute('aria-expanded', 'false');
        }

        closeCustomerLogoutModal();
    }
});


</script>

<script src="shared_notifications.js?v=20261022"></script>
<script src="customer_scripts.js?v=20261022_2"></script>

<script>
// Load notifications using shared system
document.addEventListener('DOMContentLoaded', function() {
    var dropdown = document.getElementById('customerNotificationDropdown');
    var list = document.getElementById('customerNotificationList');
    
    if (dropdown && list) {
        fetch('customer_notifications.php?action=json')
            .then(function(response) { return response.json(); })
            .then(function(data) {
                var notifications = data.notifications || [];
                if (notifications.length === 0) {
                    list.innerHTML = '<div class="customer-notification-empty"><i class="fa-regular fa-bell-slash"></i><p>No notifications</p></div>';
                } else {
                    var html = '';
                    notifications.forEach(function(n) {
                        var iconClass = n.type || 'info';
                        var iconName = 'fa-bell';
                        var isUnread = !n.is_read;
                        var time = n.created_at;
                        
                        // Map notification types to icons
                        if (n.type === 'success') {
                            iconClass = 'success';
                            iconName = 'fa-check';
                        } else if (n.type === 'alert') {
                            iconClass = 'alert';
                            iconName = 'fa-exclamation';
                        } else if (n.type === 'warning') {
                            iconClass = 'warning';
                            iconName = 'fa-circle-exclamation';
                        }
                        
                        html += '<div class="customer-notification-item' + (isUnread ? ' unread' : '') + '" data-id="' + n.id + '">';
                        html += '<div class="customer-notification-icon ' + iconClass + '"><i class="fa-solid ' + iconName + '"></i></div>';
                        html += '<div class="customer-notification-content">';
                        html += '<div class="customer-notification-title">' + n.title + '</div>';
                        html += '<div class="customer-notification-description">' + n.description + '</div>';
                        html += '<div class="customer-notification-time">' + time + '</div>';
                        html += '</div>';
                        html += '</div>';
                    });
                    list.innerHTML = html;
                    
                    // Add click handlers for marking as read
                    var items = list.querySelectorAll('.customer-notification-item.unread');
                    items.forEach(function(item) {
                        item.addEventListener('click', function() {
                            var notifId = item.getAttribute('data-id');
                            markSingleNotificationAsRead(notifId, item);
                        });
                    });
                }
            })
            .catch(function(error) {
                list.innerHTML = '<div class="customer-notification-empty"><i class="fa-solid fa-exclamation-circle"></i><p>Failed to load notifications</p></div>';
            });
    }
});

// Mark single notification as read
function markSingleNotificationAsRead(notificationId, itemElement) {
    var formData = new FormData();
    formData.append('mark_read', '1');
    formData.append('notification_id', notificationId);

    fetch('customer_notifications.php', {
        method: 'POST',
        body: formData
    })
    .then(function(response) {
        return response.json();
    })
    .then(function(data) {
        if (data.success) {
            itemElement.classList.remove('unread');
            itemElement.classList.add('read');
            
            // Update badge count
            var badge = document.querySelector('.customer-notification-badge');
            if (badge) {
                var currentCount = parseInt(badge.textContent);
                if (!isNaN(currentCount) && currentCount > 1) {
                    badge.textContent = currentCount - 1;
                } else {
                    badge.style.display = 'none';
                }
            }
        }
    })
    .catch(function(error) {
        console.error('Failed to mark notification as read:', error);
    });
}
</script>

<?php require 'shared_notifications_modal.php'; ?>

<!-- CUSTOMER LOGOUT CONFIRMATION MODAL -->
<div
    class="customer-logout-overlay hidden"
    id="customerLogoutModal"
    role="dialog"
    aria-modal="true"
    aria-labelledby="customerLogoutTitle"
    aria-describedby="customerLogoutMessage"
>
    <div class="customer-logout-modal">

        <div class="customer-logout-icon">
            <i class="fa-solid fa-right-from-bracket"></i>
        </div>

        <h2 id="customerLogoutTitle">Confirm Logout</h2>

        <p id="customerLogoutMessage">
            Are you sure you want to logout?
        </p>

        <div class="customer-logout-actions">

            <button
                type="button"
                class="customer-logout-cancel"
                onclick="closeCustomerLogoutModal()"
            >
                Cancel
            </button>

            <form method="POST" action="customer_profile.php">

                <input
                    type="hidden"
                    name="customer_logout"
                    value="1"
                >

                <input
                    type="hidden"
                    name="logout_token"
                    value="<?php echo htmlspecialchars($_SESSION['customer_logout_token'], ENT_QUOTES, 'UTF-8'); ?>"
                >

                <button
                    type="submit"
                    class="customer-logout-confirm"
                >
                    Yes, Logout
                </button>

            </form>

        </div>

    </div>
</div>
</body>
</html>
