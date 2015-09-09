<?php
/**
 * CIS canteen SSO
 *
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
$mapFile = '/home/cispl/parent_map.csv';

if(!file_exists($mapFile) || !is_readable($mapFile)) {
    $this->error(2);
}

if (($handle = fopen($mapFile, 'r')) !== FALSE) {
    while (($row = fgetcsv($handle, 1000, ',')) !== FALSE) {
            $map[$row[0]] = $row[1];
    }
    fclose($handle);
}


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



$isamsID = str_replace('P-','',$_SESSION['user']->getExternalId());

$externalUserId = $map[$isamsID];
$secret         = Config::get('remote_secret');
$time           = time();


if(!$externalUserId) {
    $this->error(2);
}

$key = hash('sha256', $externalUserId.$time.$secret);

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
