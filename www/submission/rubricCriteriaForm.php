<?php
/**
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */


require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

// This page no longer exists
SitePage::localRedir('/', null, 410);
exit;
