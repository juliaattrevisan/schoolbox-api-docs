<?php

require_once '../../include/Bootstrap.php';

$userId = $_GET['id'];

$sitePage = new SitePage;

$serviceController = new User_ServiceController($sitePage);

if ( is_numeric($_GET['width']) && is_numeric($_GET['height']) ) {
    echo $serviceController->getPortrait($userId, $_GET['width'], $_GET['height']);
}
else {
    echo $serviceController->getPortrait($userId);
}