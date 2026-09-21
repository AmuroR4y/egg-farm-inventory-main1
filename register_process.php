<?php

include 'db.php';


header(
    'Content-Type: application/json'
);


/* =========================================
   CHECK REQUEST METHOD
========================================= */

if ($_SERVER['REQUEST_METHOD'] != 'POST') {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Invalid request."
        )
    );


    exit();

}


/* =========================================
   CHECK REQUIRED FIELDS
========================================= */

if (

    !isset($_POST['fullname']) ||

    !isset($_POST['username']) ||

    !isset($_POST['email']) ||

    !isset($_POST['password']) ||

    !isset($_POST['confirm_password'])

) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Please complete all required fields."
        )
    );


    exit();

}


/* =========================================
   GET FORM DATA
========================================= */

$fullname = trim(
    $_POST['fullname']
);


$username = trim(
    $_POST['username']
);


$email = trim(
    $_POST['email']
);


$password_input =
    $_POST['password'];


$confirm_password =
    $_POST['confirm_password'];


/* =========================================
   CHECK EMPTY FIELDS
========================================= */

if (

    $fullname == "" ||

    $username == "" ||

    $email == "" ||

    $password_input == "" ||

    $confirm_password == ""

) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Please complete all required fields."
        )
    );


    exit();

}


/* =========================================
   VALIDATE EMAIL
========================================= */

if (
    !filter_var(
        $email,
        FILTER_VALIDATE_EMAIL
    )
) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Please enter a valid email address."
        )
    );


    exit();

}


/* =========================================
   CHECK PASSWORD MATCH
========================================= */

if (
    $password_input != $confirm_password
) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Passwords do not match."
        )
    );


    exit();

}


/* =========================================
   ENCRYPT PASSWORD

   MD5 is retained because your existing
   login_process.php currently uses MD5.
========================================= */

$password = md5(
    $password_input
);


/* =========================================
   CHECK IF USERNAME EXISTS
========================================= */

$check_username = mysqli_prepare(

    $conn,

    "SELECT id
     FROM users
     WHERE username = ?"

);


if (!$check_username) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Database error. Please try again."
        )
    );


    exit();

}


mysqli_stmt_bind_param(

    $check_username,

    "s",

    $username

);


mysqli_stmt_execute(
    $check_username
);


mysqli_stmt_store_result(
    $check_username
);


if (
    mysqli_stmt_num_rows(
        $check_username
    ) > 0
) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Username is already taken."
        )
    );


    mysqli_stmt_close(
        $check_username
    );


    exit();

}


mysqli_stmt_close(
    $check_username
);


/* =========================================
   CHECK IF EMAIL EXISTS
========================================= */

$check_email = mysqli_prepare(

    $conn,

    "SELECT id
     FROM users
     WHERE email = ?"

);


if (!$check_email) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Database error. Please try again."
        )
    );


    exit();

}


mysqli_stmt_bind_param(

    $check_email,

    "s",

    $email

);


mysqli_stmt_execute(
    $check_email
);


mysqli_stmt_store_result(
    $check_email
);


if (
    mysqli_stmt_num_rows(
        $check_email
    ) > 0
) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Email address is already registered."
        )
    );


    mysqli_stmt_close(
        $check_email
    );


    exit();

}


mysqli_stmt_close(
    $check_email
);


/* =========================================
   INSERT NEW CUSTOMER
========================================= */

$sql = "

    INSERT INTO users

    (

        fullname,

        username,

        email,

        password,

        role,

        status

    )

    VALUES

    (

        ?,

        ?,

        ?,

        ?,

        'customer',

        'active'

    )

";


$stmt = mysqli_prepare(
    $conn,
    $sql
);


if (!$stmt) {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Registration failed. Please try again."
        )
    );


    exit();

}


mysqli_stmt_bind_param(

    $stmt,

    "ssss",

    $fullname,

    $username,

    $email,

    $password

);


/* =========================================
   SUCCESSFUL REGISTRATION
========================================= */

if (
    mysqli_stmt_execute(
        $stmt
    )
) {


    echo json_encode(
        array(
            "status" => "success",
            "message" => "Registration Successful! Redirecting to login..."
        )
    );

}


else {


    echo json_encode(
        array(
            "status" => "error",
            "message" => "Registration failed. Please try again."
        )
    );

}


mysqli_stmt_close(
    $stmt
);

?>