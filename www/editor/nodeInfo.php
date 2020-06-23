<?php

/**
 * ${NAME}
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

// disallow SitePage context, this is an ajax script
if (isset($this)) {
    SitePage::localRedir('/editor/nodeInfo.php');
    exit;
}

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// kickstart a session
/** @var SitePage $page */
$page = $kernel->get('sitePage');
$page->initSession();

$factory = new \Schoolbox\Editor\FileFactory($_GET['identifier']);

header('Content-Type: application/json');
echo $factory->produce();
exit();
