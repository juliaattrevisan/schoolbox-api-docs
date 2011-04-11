<?php
//small script to logout users

include('include/stdfuncs.php');

session_start();
session_destroy();

localRedir("/");

?>

