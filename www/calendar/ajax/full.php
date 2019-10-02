<?php
/**
 * ${NAME}
 *
 * @author	  Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link		https://schoolbox.com.au/
 */

use Calendar\Api\ModuleChecker;
use Schoolbox\Config\ConfigMapper;

/** @var SitePage $this */
$user_mapper = new \Schoolbox\UserMapper();

$viewer = $_SESSION['user'];
$userId = $_GET['userId'] ?? null;
$user   = $userId ? $user_mapper->findById($_GET['userId']) : $viewer;

try {
    Calendar_Layout_Full_Ajax_JSON::permissionCheck($user->getId(), $viewer, new Group());
}
catch (Exception $e) {
    error($e->getCode());
}

/** @var Calendar_Api_Factory $factory */
$factory  = $this->getDi()->get('calendarApiFactory');
$calendar = new Calendar_Layout_Full_Ajax_JSON(
    $this,
    $user,
    $this->arg[0],
    $viewer,
    $factory,
    new ModuleChecker(ConfigMapper::getInstance())
);
$calendar->render();
