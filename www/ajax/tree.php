<?php

require_once dirname(__FILE__) . '/../../include/Bootstrap.php';

$page = new SitePage;

if (is_numeric($_POST['resourcesID'])) {
    $_SESSION['resourcesID'] = $_POST['resourcesID'];
}

/* Couldn't find any usages for what was just removed from here.
 * Nothing seemed to access ajax/tree.php with $_POST['relationID']
 * as args, and ThreePanel still seems to work okay?
 */
