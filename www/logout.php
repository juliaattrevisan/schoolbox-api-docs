<?php
//small script to logout users

include("include/include.php");

session_start();
session_destroy();

localRedir("/login.php");

?>

