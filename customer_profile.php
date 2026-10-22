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

    <link rel="stylesheet" href="customer_styles.css">

<style>
* {
    box-sizing: border-box;
}

/* =========================================================
   CUSTOMER NAVIGATION — USE SHARED STYLES FROM customer_styles.css
========================================================= */

/* Remove duplicate navbar styles - using shared stylesheet */



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
    .profile-layout {
        grid-template-columns: 220px minmax(0, 1fr);
        padding: 10px;
        gap: 10px;
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


</head>

<body>

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





</script>

<?php require 'customer_modal.php'; ?>

<?php require 'customer_footer.php'; ?>

</body>
</html>
