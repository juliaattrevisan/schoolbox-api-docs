<?php
/**
 * Display PTO website in an iframe with SSO
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\PTO\Loader as PTOLoader;
use Schoolbox\Config\Loader as ConfigLoader;

$ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"]);
header('Location: '. $ptoLoader->generateRemoteUrl());
exit;