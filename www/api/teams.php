<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

use Schoolbox\Resource\Attachment\Internal\Teams\ValidTeamsMeetingUrl;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/* This takes a hacked Microsoft Teams Meeting Creator app, hijacking its last screen
 * Whatever meeting url was just created gets put into a querystring param and redirected
 * to a location that's within schoolbox's domain, which allows us to raid its content
 * from within our schoolbox instance
 *
 * We then have more control over how to process the url from then on
 */
$url = $_GET['meetingUrl'];

/* Check that the meeting url var is set and contains an actual Microsoft Teams meeting url
 * ...just in case
 */
if ((new ValidTeamsMeetingUrl())->isSatisifiedBy($url)) {
    echo "<div id='meetingUrl' style='display:none'>{$url}</div>";
}
