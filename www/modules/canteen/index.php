<?php
/**
 * CIS canteen SSO
 *
 * Currently in test mode - only one parent is set up.
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

//if ($this->arg[1] == 'window') {
//    $this->setFile(TEMPLATE_content, 'modules/remote/nwindow.html');
//} else {
//    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
//}
$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
$remoteUrl = 'http://campusonline.cis.edu.sg/signon.asp';

// is this specifying a height for the parent frame?
$height = '460px';
if(strpos($remoteUrl, 'zx_h=')) {
    preg_match("#zx_h=([0-9]*(?:%|px|&))#", $remoteUrl, $matches);
    if(isset($matches[1])) {
        $height = str_replace('&', '', $matches[1]);
    }
}
//
//if (!filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
//    $this->error(2);
//}

$externalUserId = 'CIS000041-02';
$secret         = Config::get('remote_secret');
$time           = time();

$key = hash('sha256', $secret . $time . $externalUserId);

/* Check if the $remoteUrl already has a query string in it. If so,
 * just appened the $key, $time and $id parameters
 */
if (strpos($remoteUrl, '?') === false) {
    $glue = '?';
} else {
    $glue = '&';
}

$remoteUrl = $remoteUrl . $glue . http_build_query([
    'p1' => $externalUserId,
    'p2' => $time,
    'p3' => $key,
]);

$this->setVar([
    'REMOTE_URL'   => Scrub::text($remoteUrl),
    'FRAME_HEIGHT' => $height,
]);
