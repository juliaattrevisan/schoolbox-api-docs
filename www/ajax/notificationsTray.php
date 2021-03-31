<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\UserMapper;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}

if (in_array($_POST['state'], ['show', 'hide'])) {
    try {
        $userMapper = new UserMapper();
        $user = $userMapper->findById($_SESSION['user']->getId());


        if ($_POST['state'] == 'show') {
            $user->showNotificationsTray();
            $_SESSION['user']->showNotificationsTray();
        } else {
            $user->hideNotificationsTray();
            $_SESSION['user']->hideNotificationsTray();
        }

        /* Save setting
         */
        $userMapper->save($user);
    } catch (\Exception $e) {
    }
}

exit();
