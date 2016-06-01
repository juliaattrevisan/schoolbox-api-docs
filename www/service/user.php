<?php

require_once '../../library/Bootstrap.php';

$method = $_GET['method'];

$userId = $_GET['userId'];

if ( $method == 'portrait' ) {
    $sitePage = new SitePage;

    $serviceController = new User_ServiceController($sitePage);

    if ( is_numeric($_GET['width']) && is_numeric($_GET['height']) ) {
        echo $serviceController->getPortrait($userId, $_GET['width'], $_GET['height']);
    }
    else {
        echo $serviceController->getPortrait($userId);
    }
}
