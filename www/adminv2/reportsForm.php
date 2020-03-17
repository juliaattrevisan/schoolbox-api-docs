<?php
/**
 * @author      Matt Day <matt.day@alaress.com.au>
 * @copyright   Copyright 2019, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
require_once __DIR__ . '/../../conf/core/require.php';

$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/* This is a hidden input field; its absence causes this to exit early
 * Its value is 'activity' for the Activity Report
 *
 * Anyway, there are three reports: activity group login, as per Report_Factory
 * If you're not one of these reports...just die
 */
if (!Report_Factory::isValidReport($_POST['bounce'])) {
    error(2);
}

/* Parse their filter inputs and redirect back to the page
 */
$bounce = "Location: /adminv2/reports/{$_POST['bounce']}/";
$params = [];

foreach ($_POST as $post => $val) {

    /* We don't do anything with this hidden input
     */
    if ($post === 'bounce') {
        continue;
    }

    /* Replace slashes in search terms with a hyphen - for some reason
     * Probably to disambiguate search terms from arg[] elements
     * ...or dates
    */
    $val = str_replace('/', '-', $val);

    if ($val) {
        $params[] = "{$post}={$val}";
    }
}

$vars = implode(Report_Factory::PARAM_SEPARATOR, $params);

if ($vars === '') {
    $vars = 'all';
}

header($bounce . urlencode($vars));
