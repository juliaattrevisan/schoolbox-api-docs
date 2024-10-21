<?php

/**
 * Save side menu dock / undock
 */

use Schoolbox\Mapper\Exception;
use Schoolbox\UserMapper;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();
$kernel->setupSentryTrace(__FILE__);
/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

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
