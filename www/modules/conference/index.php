<?php
/**
 * CIS conference SSO
 *
 *
 * @author    Amri Sannang <amri.sannang@alaress.com.au>
 * @copyright Copyright 2015, Alaress Pty Ltd
 * @link      http://www.alaress.com.au/
 */

if ($this->arg[0] == 'window') {
    $this->setFile(TEMPLATE_content, 'modules/remote/nwindow.html');
} else {
    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
}
$remoteUrl = 'https://bookings.parentteacheronline.com.au';

// Map file will depend on whether current user is a parent or a teacher.
/** @var Schoolbox\User $user */
$user = $_SESSION['user'];
if ($user->getRole()->isParent()) {
    $mapFile = '/home/cispl/parent_map.csv';
} elseif ($user->getRole()->isStaff()) {
    $mapFile = '/home/cispl/staff_map.csv';
} else {
    $this->error(2);
}

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

// The teacher CSV doesn't have the T- prefix removed
$isamsID        = str_replace('P-', '', $user->getExternalId());
$externalUserId = $map[$isamsID];
$schoolId       = Config::get('external_pto_conference_school_id');
$secret         = Config::get('external_pto_conference_school_remote_secret');
$time           = time();


if(!$externalUserId) {
    $this->error(2);
}

$key = hash('sha256', $schoolId.$externalUserId.$time.$secret);

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
    'REMOTE_URL'   => $remoteUrl,
    'FRAME_HEIGHT' => $height,
]);
