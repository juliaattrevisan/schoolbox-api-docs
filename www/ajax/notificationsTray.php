<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

use Schoolbox\UserMapper;

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