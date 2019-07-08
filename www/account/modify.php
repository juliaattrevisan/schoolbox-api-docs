<?php
/**
 * Redirect to the new Change Password controller
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Alaress Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\Application\WebRequest\Kernel;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new Kernel();

SitePage::localRedir('/passwordChange', false, 301);
