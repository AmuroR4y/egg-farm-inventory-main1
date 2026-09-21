<?php

session_start();
session_destroy();

header("Location: /egg-farm-inventory-main1/login.php");
exit();

?>