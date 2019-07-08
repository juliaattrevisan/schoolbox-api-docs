<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

if (in_array($_POST['state'], ['show', 'hide'])) {
    try {
        $userMapper = new UserMapper();
        $user = $userMapper->findById($_SESSION['user']->getId());


        if ($_POST['state'] == 'show') {
            $user->showNotificationsTray();
            $_SESSION['user']->showNotificationsTray();
        }
        else {
            $user->hideNotificationsTray();
            $_SESSION['user']->hideNotificationsTray();
        }

        /* Save setting
         */
        $userMapper->save($user);
    }
    catch (\Exception $e) {
    }
}

exit();
