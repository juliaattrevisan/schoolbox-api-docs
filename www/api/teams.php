<?php
/**
 * @copyright   Copyright 2020, Schoolbox Pty Ltd
 * @link        https://schoolbox.com.au/
 */
declare(strict_types=1);

/* This takes a hacked Microsoft Teams Meeting Creator app, hijacking its last screen
 * Whatever meeting url was just created gets put into a querystring param and redirected
 * to a location that's within schoolbox's domain, which allows us to raid its content
 * from within our schoolbox instance
 *
 * We then have more control over how to process the url from then on
 */
echo sprintf( '<div id="meetingUrl" style="display:none">%s</div>', $_GET['meetingUrl']);
