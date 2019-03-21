<?php

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

$page = new SitePage;

if (is_numeric($_POST['resourcesID'])) {
    $_SESSION['resourcesID'] = $_POST['resourcesID'];
}

/* Couldn't find any usages for what was just removed from here.
 * Nothing seemed to access ajax/tree.php with $_POST['relationID']
 * as args, and ThreePanel still seems to work okay?
 */
