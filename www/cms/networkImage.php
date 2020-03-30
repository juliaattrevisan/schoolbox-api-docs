<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\BasicRequest\Kernel();

header('Cache-Control: max-age=10800, private');

// a bit heavy-handed, but I guess we're not implementing file previews for network??
SitePage::redirectToBrokenImageOfSize($_GET['width'], $_GET['height'], 404);
