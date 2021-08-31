<?php

/**
 * ${NAME}
 *
 * @author    Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Config\Loader as ConfigLoader;

/** @var SitePage $this */
$user_mapper = new \Schoolbox\UserMapper();
$user = $user_mapper->findByIdWithFallback($_GET['userId']);
$viewer = $_SESSION['user'];

try {
    Calendar_Layout_Full_Ajax_JSON::permissionCheck($_GET['userId'], $viewer, new Group());
} catch (Exception $e) {
    error($e->getCode());
}

// Calendar generation could take a while
SitePage::extendExecutionTime();

/** @var Calendar_Api_Factory $factory */
$factory  = $this->getDi()->get('calendarApiFactory');
$calendar = new Calendar_Layout_Full_Ajax_JSON(
    $this->getDi()->get('authorizer'),
    $this->getDi()->get('router'),
    $user,
    $viewer,
    $factory,
    new ModuleChecker(ConfigLoader::getInstance()),
    isset($_GET['folderId']) ? (int)$_GET['folderId'] : null
);

$calendar->render();
