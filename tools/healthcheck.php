<?php
/**
 * Perform Schoolbox User/Synergetic synchronisations
 *
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2012, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 * @version     SVN: $Id$
 */

echo "[".date('Y-m-d H:i:s')."] Running health check script\n";

require_once(dirname(__FILE__) . '/../library/Bootstrap.php');

$sync = new Healthcheck();

echo $sync->run();
