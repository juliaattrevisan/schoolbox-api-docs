<?php
/**
 * Display PTO website in an iframe with SSO
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\PTO\Loader as PTOLoader;
use Custom\Dcc\PTO\Loader as DccPTOLoader;
use Schoolbox\Config\Loader as ConfigLoader;

$cfg = new ConfigLoader();

if ($cfg->get('site_school_code') === 'DCC') {
    $ptoLoader = new DccPTOLoader($cfg, $_SESSION["user"]);
} else {
    $ptoLoader = new PTOLoader($cfg, $_SESSION["user"]);
}

header('Location: '. $ptoLoader->generateRemoteUrl());
exit;
