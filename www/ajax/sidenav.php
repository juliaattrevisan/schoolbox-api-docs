<?php

/**
 * Save side menu dock / undock
 */

use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}

if (\in_array($_POST['state'], ['show', 'hide'], true)) {
    try {
        $userMapper = UserMapper::getInstance();
        $user = $userMapper->findById($_SESSION['user']->getId());

        if ($_POST['state'] == 'show') {
            $user->showSidenav();
            $_SESSION['user']->showSidenav();
        } else {
            $user->hideSidenav();
            $_SESSION['user']->hideSidenav();
        }

        // Save setting
        $userMapper->save($user);
    } catch (Exception $e) {
    }
}

exit();
