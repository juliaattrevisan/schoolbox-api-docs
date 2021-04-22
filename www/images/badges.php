<?php

/**
 * Redirect for badge icon images prior to the introduction of the CDN.
 *
 * Prior to the CDN (and the `/static/` folder when not using the CDN), badge
 * icons were accessible via `/images/badges/$category/$id.svg`. This script
 * simply redirects requests for the old URL to the new URL.
 *
 * The redirect exists as content may have already been generated which points
 * to the old URL (i.e. notification emails for badges prior to the creation of
 * the CDN).
 *
 * @copyright Copyright 2021, Schoolbox Pty Ltd
 * @link      https://schoolbox.com.au/
 */

use Schoolbox\Cdn\PrefixGenerator;

if (!isset($this)) {
    http_response_code(404);
    die('No direct access permitted');
}

/** @var SitePage $this */
/** @var PrefixGenerator $cdnPrefixGenerator */
$cdnPrefixGenerator = $this->getDi()->get('cdnPrefixGenerator');
$cdnPrefixGenerator->redirectPermanent('/images/badges/' . implode('/', $this->arg));
exit;
