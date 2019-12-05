<?php
/**
 * Display PTO website in an iframe with SSO
 *
 * @author      Steve Chan <steve.chan@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

use Schoolbox\IConfigLoader;
use Schoolbox\PTO\Loader as PTOLoader;
use Custom\Dcc\PTO\Loader as DccPTOLoader;

// Redir if $this doesn't exist (a.k.a this was accessed with a .php extension)
if (!isset($this)) {
    require_once __DIR__ . '/../../../conf/core/require.php';
    $kernel = new \Schoolbox\Application\WebRequest\Kernel();
    SitePage::localRedir('/modules/pto');
}

/** @var SitePage $this */
/** @var IConfigLoader $cfg */
$cfg = $this->getDi()->get('config');

if ($cfg->get('site_school_code') === 'DCC') {
    $ptoLoader = new DccPTOLoader($cfg, $_SESSION["user"]);
} else {
    $ptoLoader = new PTOLoader($cfg, $_SESSION["user"]);
}

header('Location: '. $ptoLoader->generateRemoteUrl());
exit;
