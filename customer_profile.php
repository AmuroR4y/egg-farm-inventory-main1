<?php

if (!isset($_SESSION)) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| SECURITY CHECK
|--------------------------------------------------------------------------
*/

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

include 'db.php';

$user_id = $_SESSION['user_id'];

$user_role = isset($_SESSION['role'])
    ? $_SESSION['role']
    : 'Customer';


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
<style>

    body {
        background: #f5f6f7;
    }

    .main-content {
        min-height: 100vh;
        padding: 25px;
    }

    .profile-page {
        max-width: 1450px;
        margin: 0 auto;
    }


    /*
    |--------------------------------------------------------------------------
    | OUTER PROFILE CARD
    |--------------------------------------------------------------------------
    */

    .profile-outer-card {
        width: 100%;
        background: #ffffff;
        border: 1px solid #dfe5e1;
        border-radius: 14px;
        padding: 25px;
        box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
    }


    /*
    |--------------------------------------------------------------------------
    | OUTER CARD HEADER
    |--------------------------------------------------------------------------
    */

    .page-header {
        display: flex;
        align-items: center;
        gap: 15px;
    }

    .page-header-icon {
        width: 46px;
        height: 46px;
        min-width: 46px;
        border-radius: 50%;

        display: flex;
        align-items: center;
        justify-content: center;

        background: #e8f1eb;
        color: #356b4b;
        font-size: 20px;
    }

    .page-title {
        font-size: 22px;
        font-weight: 700;
        color: #35433b;
        margin: 0;
    }

    .page-subtitle {
        font-size: 12px;
        color: #6b7280;
        margin: 3px 0 0;
    }


    /*
    |--------------------------------------------------------------------------
    | HEADER DIVIDER
    |--------------------------------------------------------------------------
    */

    .profile-header-divider {
        width: 100%;
        height: 1px;
        background: #e5e9e6;
        margin: 22px 0;
    }


    /*
    |--------------------------------------------------------------------------
    | PROFILE LAYOUT
    |--------------------------------------------------------------------------
    */

    .profile-layout {
        display: grid;
        grid-template-columns: 270px minmax(0, 1fr);
        gap: 18px;
        align-items: stretch;
    }


    /*
    |--------------------------------------------------------------------------
    | LEFT PROFILE CARD
    |--------------------------------------------------------------------------
    */

    .profile-sidebar-card {
        width: 100%;
        height: 100%;

        background: #ffffff;
        border: 1px solid #dfe5e1;
        border-radius: 10px;
        overflow: hidden;

        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }

    .profile-user-section {
        padding: 30px 22px;
        text-align: center;
        border-bottom: 1px solid #edf0ee;
    }

    .profile-avatar-wrapper {
        position: relative;
        width: 100px;
        height: 100px;
        margin: 0 auto 15px;
    }

    .profile-avatar {
        width: 100%;
        height: 100%;
        object-fit: cover;
        border-radius: 50%;
        background: #e8f1eb;
        border: 5px solid #edf4ef;
    }

    .edit-photo-button {
        position: absolute;
        right: 0;
        bottom: 0;

        width: 30px;
        height: 30px;

        border-radius: 50%;
        background: #356b4b;
        color: white;

        display: flex;
        align-items: center;
        justify-content: center;

        cursor: pointer;
        font-size: 11px;
        border: 3px solid white;
    }

    .profile-name {
        font-size: 18px;
        font-weight: 700;
        color: #415049;
        margin-bottom: 7px;
    }

    .role-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;

        background: #e8f1eb;
        color: #4d745b;

        font-size: 11px;
        font-weight: 600;
    }

    .contact-section {
        padding: 20px;
        border-bottom: 1px solid #edf0ee;
    }

    .contact-item {
        display: flex;
        align-items: center;
        gap: 10px;

        font-size: 11px;
        color: #5d6a64;

        margin-bottom: 14px;
        word-break: break-word;
    }

    .contact-item:last-child {
        margin-bottom: 0;
    }

    .contact-icon {
        width: 15px;
        color: #527d59;
    }

    .status-box {
        margin: 14px;
        padding: 17px;

        border-radius: 7px;

        background: linear-gradient(
            90deg,
            #f3faf5,
            #eaf2ed
        );
    }

    .status-title {
        font-size: 11px;
        font-weight: 600;
        color: #52615a;
        margin-bottom: 12px;
    }

    .active-status {
        display: flex;
        align-items: center;
        gap: 7px;

        color: #3f7a53;
        font-size: 11px;
        font-weight: 600;
    }

    .status-dot {
        width: 7px;
        height: 7px;

        border-radius: 50%;
        background: #4caf70;
    }


    /*
    |--------------------------------------------------------------------------
    | RIGHT PROFILE MAIN CARD
    |--------------------------------------------------------------------------
    */

    .profile-main-card {
        width: 100%;
        height: 100%;

        background: #ffffff;
        border: 1px solid #dfe5e1;
        border-radius: 10px;

        padding: 20px;

        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
    }


    /*
    |--------------------------------------------------------------------------
    | TABS
    |--------------------------------------------------------------------------
    */

    .profile-tabs {
        display: flex;
        align-items: center;
        gap: 25px;

        border-bottom: 1px solid #dce5df;
        margin-bottom: 20px;
    }

    .profile-tab {
        padding: 0 8px 12px;

        font-size: 12px;
        font-weight: 600;

        color: #6d7772;
        border: none;
        border-bottom: 3px solid transparent;

        cursor: pointer;
        background: transparent;
    }

    .profile-tab.active {
        color: #356b4b;
        border-bottom: 3px solid #356b4b;
    }


    /*
    |--------------------------------------------------------------------------
    | FORM
    |--------------------------------------------------------------------------
    */

    .section-title {
        font-size: 16px;
        font-weight: 700;
        color: #435149;
        margin-bottom: 4px;
    }

    .section-subtitle {
        font-size: 11px;
        color: #6c7771;
        margin-bottom: 22px;
    }

    .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 18px;
    }

    .form-group.full-width {
        grid-column: 1 / -1;
    }

    .form-label {
        display: block;

        font-size: 11px;
        font-weight: 600;

        color: #526059;
        margin-bottom: 7px;
    }

    .required {
        color: #d9534f;
    }

    .form-input {
        width: 100%;
        height: 43px;

        border: 1px solid #d6ded9;
        border-radius: 6px;

        padding: 0 13px;

        font-size: 12px;
        color: #48554e;

        outline: none;
        background: #ffffff;

        transition: 0.2s;
    }

    .form-input:focus {
        border-color: #527d59;

        box-shadow:
            0 0 0 3px
            rgba(82, 125, 89, 0.10);
    }

    .readonly-input {
        background: #f7f9f8;
        color: #7a8580;
    }


    /*
    |--------------------------------------------------------------------------
    | SAVE BUTTON
    |--------------------------------------------------------------------------
    */

    .button-row {
        display: flex;
        justify-content: flex-end;
        margin-top: 28px;
    }

    .save-button {
        background: #356b4b;
        color: white;

        border: none;
        border-radius: 6px;

        padding: 12px 21px;

        font-size: 11px;
        font-weight: 600;

        cursor: pointer;
        transition: 0.2s;

        display: flex;
        align-items: center;
        gap: 8px;
    }

    .save-button:hover {
        background: #2d5b40;
    }


    /*
    |--------------------------------------------------------------------------
    | INFORMATION BOX
    |--------------------------------------------------------------------------
    */

    .security-note {
        margin-top: 25px;
        padding: 14px;

        background: linear-gradient(
            90deg,
            #f2faf5,
            #eaf2ed
        );

        border-radius: 7px;

        color: #587064;
        font-size: 10px;

        display: flex;
        align-items: center;
        gap: 10px;
    }


    /*
    |--------------------------------------------------------------------------
    | PASSWORD
    |--------------------------------------------------------------------------
    */

    .password-wrapper {
        position: relative;
    }

    .toggle-password {
        position: absolute;
        right: 13px;
        top: 50%;

        transform: translateY(-50%);

        border: none;
        background: transparent;

        color: #839089;
        cursor: pointer;
    }

    .password-input {
        padding-right: 40px;
    }


    /*
    |--------------------------------------------------------------------------
    | TAB CONTENT
    |--------------------------------------------------------------------------
    */

    .tab-content {
        display: none;
    }

    .tab-content.active {
        display: block;
    }


    /*
    |--------------------------------------------------------------------------
    | ALERTS
    |--------------------------------------------------------------------------
    */

    .alert {
        padding: 14px 17px;
        margin-bottom: 18px;

        border-radius: 7px;

        font-size: 12px;

        display: flex;
        align-items: center;
        gap: 10px;
    }

    .alert-success {
        background: #eaf7ee;
        border: 1px solid #b9dfc3;
        color: #2f7045;
    }

    .alert-error {
        background: #fff0f0;
        border: 1px solid #efc4c4;
        color: #b84444;
    }


    /*
    |--------------------------------------------------------------------------
    | RESPONSIVE DESIGN
    |--------------------------------------------------------------------------
    */

    @media (max-width: 900px) {

        .profile-layout {
            grid-template-columns: 1fr;
        }

    }

    @media (max-width: 650px) {

        .main-content {
            padding: 15px;
        }

        .profile-outer-card {
            padding: 18px;
        }

        .form-grid {
            grid-template-columns: 1fr;
        }

        .profile-tabs {
            gap: 10px;
            overflow-x: auto;
        }

    }

</style>

</head>

<body>

<?php include('customer_panel.php'); ?>

<div class="main-content">

    <div class="profile-page">

        <!-- LARGE OUTER CARD -->
        <div class="profile-outer-card">

            <!-- PROFILE MANAGEMENT HEADER -->
            <div class="page-header">

                <div class="page-header-icon">

                    <i class="fa-solid fa-user"></i>

                </div>

                <div>

                    <h1 class="page-title">
                        Profile Management
                    </h1>

                    <p class="page-subtitle">
                        Manage your account information and security settings.
                    </p>

                </div>

            </div>


            <!-- HORIZONTAL DIVIDER -->
            <div class="profile-header-divider"></div>


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
                                <div class="form-group">

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
                                <div class="form-group">

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
                                <div class="form-group">

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


                                <!-- ROLE -->
                                <div class="form-group">

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


                                <!-- ADDRESS -->
                                <div class="form-group full-width">

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
                                <div class="form-group full-width">

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
                                            required
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
                            <div class="security-note">

                                <i
                                    class="fa-solid fa-shield-halved"
                                ></i>

                                Use a strong password with at least
                                8 characters to help protect your account.

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


<script>


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
| PASSWORD MATCHING
|--------------------------------------------------------------------------
*/

var newPassword =
    document.getElementById(
        "newPassword"
    );


var confirmPassword =
    document.getElementById(
        "confirmPassword"
    );


var passwordMessage =
    document.getElementById(
        "passwordMessage"
    );


function checkPasswords() {


    if (
        !confirmPassword.value
    ) {

        passwordMessage.innerHTML =
            "";


        confirmPassword.setCustomValidity(
            ""
        );


        return;

    }


    if (
        newPassword.value ==
        confirmPassword.value
    ) {

        passwordMessage.innerHTML =
            "Passwords match.";


        passwordMessage.style.color =
            "#2f7a4b";


        confirmPassword.setCustomValidity(
            ""
        );

    } else {

        passwordMessage.innerHTML =
            "Passwords do not match.";


        passwordMessage.style.color =
            "#c94f4f";


        confirmPassword.setCustomValidity(
            "Passwords must match."
        );

    }

}


if (newPassword) {

    newPassword.addEventListener(
        "input",
        checkPasswords
    );

}


if (confirmPassword) {

    confirmPassword.addEventListener(
        "input",
        checkPasswords
    );

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


</script>


</body>

</html>