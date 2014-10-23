<?php

if ($this->arg[1] == 'window') {
    $this->setFile(TEMPLATE_content, 'modules/remote/nwindow.html');
} else {
    $this->setFile(TEMPLATE_content, 'modules/remote/frame.html');
}
$remoteUrl = base64_decode($this->arg[0]);

// is this specifying a height for the parent frame?
$height = '460px';
if(strpos($remoteUrl, 'zx_h=')) {
    preg_match("#zx_h=([0-9]*(?:%|px|&))#", $remoteUrl, $matches);
    if(isset($matches[1])) {
        $height = str_replace('&', '', $matches[1]);
    }
}

if (!filter_var($remoteUrl, FILTER_VALIDATE_URL)) {
    $this->error(2);
}

$secret = Config::get('remote_secret');
$time = time();
$externalUserId = $_SESSION['sid'];
$username = urlencode($_SESSION['username']);

$key = sha1($secret . $time . $externalUserId);

/* Check if the $remoteUrl already has a query string in it. If so,
 * just appened the $key, $time and $id parameters
 */
if (strpos($remoteUrl, '?') === false) {
    $glue = '?';
} else {
    $glue = '&';
}

$remoteUrl = $remoteUrl . $glue . "key=$key&time=$time&id=$externalUserId&user=$username";

$this->setVar(array(
    'REMOTE_URL' => addslashes($remoteUrl),
    'FRAME_HEIGHT' => $height,
));