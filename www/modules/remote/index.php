<?php

$this->setFile(TEMPLATE_content, 'modules/remote/frame.html');

$remoteUrl = base64_decode($this->arg[0]);

if ( !filter_var($remoteUrl, FILTER_VALIDATE_URL) ) {
    $this->error(2);
}

$secret = Config::get('remote_secret');
$time = time();
$externalUserId = $_SESSION['sid'];

$key = sha1($secret . $time . $externalUserId);

/* Check if the $remoteUrl already has a query string in it. If so,
 * just appened the $key, $time and $id parameters
 */
if (strpos($remoteUrl, '?') === false) {
	$glue = '?';
}
else {
	$glue = '&';
}

$remoteUrl = $remoteUrl . $glue . "key=$key&time=$time&id=$externalUserId";

$this->setVar('REMOTE_URL', $remoteUrl);