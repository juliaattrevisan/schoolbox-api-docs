<?php

/**
 * Redirect for links to MIME icon images created prior to the introduction of
 * the CDN.
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
$cdnPrefixGenerator->redirectPermanent('/images/mime/' . implode('/', $this->arg));
exit;
