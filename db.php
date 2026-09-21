<?php
date_default_timezone_set('Asia/Manila');
$conn = mysqli_connect(
    "localhost",
    "root",
    "",
    "egg_farm_system"
);

if(!$conn){
    die("Connection Failed");
}

?>