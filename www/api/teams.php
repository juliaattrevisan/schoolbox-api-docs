<?php

/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */

declare(strict_types=1);

use Schoolbox\Resource\Attachment\Filesystem\Teams\ValidTeamsMeetingUrl;

require_once __DIR__ . '/../../conf/core/require.php';
$kernel = new \Schoolbox\Application\WebRequest\Kernel();

/* This takes a hacked Microsoft Teams Meeting Creator app, hijacking its last screen
 * Whatever meeting url was just created gets put into a querystring param and redirected
 * to a location that's within schoolbox's domain, which allows us to raid its content
 * from within our schoolbox instance
 *
 * We then have more control over how to process the url from then on
 */
$url = $_GET['meetingUrl'] ?? '';

/* Check that the meeting url var is set and contains an actual Microsoft Teams meeting url
 * ...just in case
 *
 * I know this is going to look ugly, but I'd have to set up all the frontend stuff here
 * This is an iframe document and doesn't inherit styles from the schoolbox instance
 *
 * For the vast majority of the time you shouldn't see it at all and would only manifest
 * if Microsoft Teams died, or if they moved their meeting urls off the microsoft domain
 * entirely - both scenarios indicate bigger problems than error styling in this modal?
 */
if ((new ValidTeamsMeetingUrl())->isSatisifiedBy($url)) {
    echo "<div id='meetingUrl' style='display:none'>{$url}</div>";
} else {
    echo "
        <p>The meeting url <strong>{$url}</strong> is not in the expected Microsoft Teams Meeting format.</p>
        <p>Please contact your system administrator.</p>
    ";
}
