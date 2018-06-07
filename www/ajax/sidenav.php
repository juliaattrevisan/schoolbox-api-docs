<?php
/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2018, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

require_once dirname(__FILE__) . '/../../library/Bootstrap.php';

use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

if (\in_array($_POST['state'], ['show', 'hide'], true)) {
    try {
        $userMapper = UserMapper::getInstance();
        $user = $userMapper->findById($_SESSION['user']->getId());


        if ($_POST['state'] == 'show') {
            $user->showSidenav();
            $_SESSION['user']->showSidenav();
        }
        else {
            $user->hideSidenav();
            $_SESSION['user']->hideSidenav();
        }

        /* Save setting
         */
        $userMapper->save($user);
    }
    catch (Exception $e) {
    }
}

exit();
