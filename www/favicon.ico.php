<?php
/**
 * Favicon redirector
 *
 * Checks if the skin supplied has a favicon and redirects to it.
 * Otherwise shows the default favicon from the 'core' skin.
 *
 * @author      Sam Bailey <sam.bailey@alaress.com.au>
 * @copyright   Copyright 2014, Alaress Pty Ltd
 * @link        http://www.alaress.com.au/
 */

use Schoolbox\Application\BasicRequest;
use Schoolbox\IConfigLoader;

require_once __DIR__ . '/../conf/core/require.php';
$kernel = new BasicRequest\Kernel();

/** @var IConfigLoader $config */
$config       = $kernel->get('config');
$siteLocation = $config->get('site_location');

if (is_readable("images/$siteLocation/favicon.ico")) {
    header("Location: /images/$siteLocation/favicon.ico", true, 301);
    exit;
}
header('Location: /images/logo.php?logo=skin_logo_square&size=favicon', true, 302);
exit;
