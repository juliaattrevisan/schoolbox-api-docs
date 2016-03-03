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
        if ($_POST['state'] == 'show') {
            $_SESSION['user']->showNotificationsTray();
        }
        else {
            $_SESSION['user']->hideNotificationsTray();
        }

        /* Save setting
         */
        $userMapper = new UserMapper();
        $userMapper->save($_SESSION['user']);
    }
    catch (\Exception $e) {
    }
}

exit();