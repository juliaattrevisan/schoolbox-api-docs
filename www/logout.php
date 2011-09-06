<?php
//small script to logout users

require_once('../include/Bootstrap.php');

session_start();
session_destroy();

SitePage::localRedir("/");


