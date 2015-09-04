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
require_once dirname(__FILE__) . '/../include/Bootstrap.php';

$page = new SitePage(false);

if (is_readable('images/' . $page->site . '/favicon.ico')) {
    header('Location: /images/' . $page->site . '/favicon.ico',TRUE,301);
    exit;
}
else {
    header('Location: /images/logo.php?logo=skin_logo_square&size=favicon',TRUE,302);
    exit;
}