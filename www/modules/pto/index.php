<?php
/**
 * Display PTO website in an iframe with SSO
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2011, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\PTO\Loader as PTOLoader;
use Custom\Dcc\PTO\Loader as DccPTOLoader;
use Schoolbox\Config\Loader as ConfigLoader;

if (\Schoolbox\Config\ConfigMapper::getInstance()->get('site_school_code') === 'dcc') {
    $ptoLoader = new DccPTOLoader(new ConfigLoader(), $_SESSION["user"]);
} else {
    $ptoLoader = new PTOLoader(new ConfigLoader(), $_SESSION["user"]);
}

header('Location: '. $ptoLoader->generateRemoteUrl());
exit;