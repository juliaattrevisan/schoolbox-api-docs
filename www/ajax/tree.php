<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;

if (is_numeric($_POST['resourcesID'])) {
    $_SESSION['resourcesID'] = $_POST['resourcesID'];
}